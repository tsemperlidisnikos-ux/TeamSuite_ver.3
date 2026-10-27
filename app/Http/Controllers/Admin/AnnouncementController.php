<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\InteractsWithClubResource;
use App\Http\Controllers\Controller;
use App\Jobs\SendAnnouncementEmailsJob;
use App\Jobs\SendAnnouncementPushJob;
use App\Jobs\SendAnnouncementSmsJob;
use App\Models\AcademyClass;
use App\Models\Announcement;
use App\Models\Athlete;
use App\Models\Coach;
use App\Models\ParentAthleteLink;
use App\Models\Sport;
use App\Models\StaffMember;
use App\Models\User;
use App\Services\Audit\ClubActivityLogger;
use App\Support\PreferredNotifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    use InteractsWithClubResource;

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $role = (string) $request->query('role', '');

        $club = $request->attributes->get('currentClub') ?? $request->user()?->activeClub;
        $clubName = trim((string) ($club?->name ?? ''));

        $items = $this->clubQuery(Announcement::class, $request)
            ->with(['creator:id,name'])
            ->when($q !== '', function ($builder) use ($q) {
                $like = '%'.$q.'%';
                $op = config('database.default') === 'pgsql' ? 'ilike' : 'like';
                $builder->where(function ($w) use ($like, $op) {
                    $w->where('title', $op, $like)->orWhere('body', $op, $like);
                });
            })
            ->when($status === 'published', fn ($b) => $b->whereNotNull('published_at'))
            ->when($status === 'draft', fn ($b) => $b->whereNull('published_at'))
            ->when($role !== '', function ($builder) use ($role) {
                $builder->where(function ($w) use ($role) {
                    $w->where('audience', 'like', '%"'.$role.'"%')
                        ->orWhereNull('audience');
                });
            })
            ->orderByRaw('COALESCE(published_at, created_at) DESC')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(5)
            ->withQueryString()
            ->through(function (Announcement $a) use ($clubName) {
                $body = $a->displayBody();
                $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($body)) ?? '');
                $audience = $a->displayAudience();
                $status = strtolower((string) ($a->meta['status'] ?? ''));
                $isPublished = $a->published_at !== null;
                if (! $isPublished && $status === 'published') {
                    $isPublished = true;
                }
                if ($status === 'draft') {
                    $isPublished = false;
                }

                $publishedAt = optional($a->published_at)->toIso8601String();
                if (! $publishedAt && $isPublished) {
                    $visibleFrom = $a->meta['visibleFrom'] ?? null;
                    $publishedAt = is_string($visibleFrom) && $visibleFrom !== ''
                        ? $visibleFrom
                        : optional($a->created_at)->toIso8601String();
                }

                return [
                    'id' => $a->id,
                    'title' => $a->title,
                    'body' => $body,
                    'body_preview' => mb_strlen($plain) > 90 ? mb_substr($plain, 0, 90).'…' : $plain,
                    'audience' => $audience,
                    'published_at' => $publishedAt,
                    'is_published' => $isPublished,
                    'updated_at' => optional($a->updated_at)->toIso8601String(),
                    'email_stats' => $a->emailStats(),
                    'image_url' => $a->imageUrl(),
                    'created_by_name' => $a->creatorDisplayName($clubName),
                    'priority' => $a->meta['priority']
                        ?? (! empty($a->meta['highPriority']) ? 'high' : 'normal'),
                ];
            });

        return Inertia::render('Admin/Announcements/Index', [
            'items' => $items,
            'classes' => $this->clubQuery(AcademyClass::class, $request)
                ->orderBy('name')
                ->get(['id', 'name', 'sport']),
            'sports' => $this->clubQuery(Sport::class, $request)
                ->orderBy('name')
                ->pluck('name')
                ->values(),
            'audienceOptions' => $this->audiencePickerOptions($request),
            'clubName' => $clubName,
            'filters' => [
                'q' => $q,
                'status' => $status,
                'role' => $role,
            ],
            'roleOptions' => [
                ['value' => 'parent', 'label' => 'Γονείς'],
                ['value' => 'coach', 'label' => 'Προπονητές'],
                ['value' => 'athlete', 'label' => 'Αθλητές'],
                ['value' => 'staff', 'label' => 'Προσωπικό'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $audience = $this->audienceFrom($data);
        $emails = $this->resolveAudienceEmails($request, $audience);
        $phones = $this->resolveAudiencePhones($request, $audience);
        $pushUserIds = $this->resolveAudienceUserIds($request, $audience);
        $sendEmail = filter_var($data['send_email'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $sendSms = filter_var($data['send_sms'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $sendPush = filter_var($data['send_push'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $publish = filter_var($data['publish'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($publish && $sendEmail && $emails === []) {
            return back()->withErrors([
                'send_email' => 'Ενεργή «Αποστολή και με email», αλλά δεν βρέθηκαν παραλήπτες με email στο ακροατήριο.',
            ])->withInput();
        }
        if ($publish && $sendSms && $phones === []) {
            return back()->withErrors([
                'send_sms' => 'Ενεργή «Αποστολή και με SMS», αλλά δεν βρέθηκαν τηλέφωνα στο ακροατήριο.',
            ])->withInput();
        }

        $meta = [
            'send_email' => $sendEmail,
            'send_sms' => $sendSms,
            'send_push' => $sendPush,
        ];
        $meta = $this->applyImageUpload($request, $meta);

        $row = Announcement::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $this->clubId($request),
            'created_by' => $request->user()?->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'audience' => $audience,
            'published_at' => $publish ? now() : null,
            'meta' => $meta,
        ]);

        $clubId = $this->clubId($request);
        $statusLabel = $publish ? 'δημοσιεύτηκε' : 'αποθηκεύτηκε ως πρόχειρο';
        app(ClubActivityLogger::class)->record(
            $clubId,
            'announcement.create',
            'Δημιούργησε ανακοίνωση',
            sprintf(
                '«%s» · %s%s%s%s',
                $row->title,
                $statusLabel,
                $sendEmail && $emails !== [] ? ' · email σε '.count($emails).' παραλήπτες' : '',
                $sendSms && $phones !== [] ? ' · SMS σε '.count($phones) : '',
                $sendPush && $pushUserIds !== [] ? ' · push σε '.count($pushUserIds) : ''
            ),
            'announcement',
            $row->id,
        );

        $queued = [];
        if ($publish && $sendEmail && $emails !== []) {
            SendAnnouncementEmailsJob::dispatch(
                $clubId,
                $row->id,
                $data['title'],
                $data['body'],
                $emails,
                $meta['image_path'] ?? null,
            );
            $queued[] = 'email '.count($emails);
        }
        if ($publish && $sendSms && $phones !== []) {
            SendAnnouncementSmsJob::dispatch($clubId, $row->id, $data['title'], $data['body'], $phones);
            $queued[] = 'SMS '.count($phones);
        }
        if ($publish && $sendPush && $pushUserIds !== []) {
            SendAnnouncementPushJob::dispatch(
                $clubId,
                $row->id,
                $data['title'],
                $data['body'],
                $pushUserIds,
                route('admin.announcements.index'),
            );
            $queued[] = 'push '.count($pushUserIds);
        }

        if ($queued !== []) {
            return back()->with(
                'success',
                'Η ανακοίνωση δημοσιεύτηκε. Ουρά: '.implode(', ', $queued).'.'
            );
        }

        return back()->with(
            'success',
            $publish ? 'Η ανακοίνωση δημοσιεύτηκε.' : 'Η ανακοίνωση αποθηκεύτηκε ως πρόχειρο.'
        );
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $data = $this->validated($request);
        $row = $this->clubQuery(Announcement::class, $request)->findOrFail($id);
        $audience = $this->audienceFrom($data);
        $sendEmail = filter_var($data['send_email'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $sendSms = filter_var($data['send_sms'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $sendPush = filter_var($data['send_push'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $publish = array_key_exists('publish', $data)
            ? filter_var($data['publish'], FILTER_VALIDATE_BOOLEAN)
            : ($row->published_at !== null);

        $emails = $this->resolveAudienceEmails($request, $audience);
        $phones = $this->resolveAudiencePhones($request, $audience);
        $pushUserIds = $this->resolveAudienceUserIds($request, $audience);

        if ($publish && $sendEmail && $emails === []) {
            return back()->withErrors([
                'send_email' => 'Ενεργή «Αποστολή και με email», αλλά δεν βρέθηκαν παραλήπτες με email στο ακροατήριο.',
            ])->withInput();
        }
        if ($publish && $sendSms && $phones === []) {
            return back()->withErrors([
                'send_sms' => 'Ενεργή «Αποστολή και με SMS», αλλά δεν βρέθηκαν τηλέφωνα στο ακροατήριο.',
            ])->withInput();
        }

        $meta = array_merge($row->meta ?? [], [
            'send_email' => $sendEmail,
            'send_sms' => $sendSms,
            'send_push' => $sendPush,
        ]);
        $meta = $this->applyImageUpload($request, $meta);

        $row->fill([
            'title' => $data['title'],
            'body' => $data['body'],
            'audience' => $audience,
            'meta' => $meta,
        ]);

        if (array_key_exists('publish', $data)) {
            if ($publish && ! $row->published_at) {
                $row->published_at = now();
            } elseif (! $publish) {
                $row->published_at = null;
            }
        }

        $row->save();

        app(ClubActivityLogger::class)->record(
            $this->clubId($request),
            'announcement.update',
            'Ενημέρωσε ανακοίνωση',
            sprintf(
                '«%s» · %s%s',
                $row->title,
                $publish ? 'δημοσιευμένη' : 'πρόχειρο',
                $sendEmail && $emails !== [] ? ' · email σε '.count($emails).' παραλήπτες' : ''
            ),
            'announcement',
            $row->id,
        );

        $clubId = $this->clubId($request);
        $queued = [];
        if ($publish && $sendEmail && $emails !== []) {
            SendAnnouncementEmailsJob::dispatch(
                $clubId,
                $row->id,
                $data['title'],
                $data['body'],
                $emails,
                $meta['image_path'] ?? null,
            );
            $queued[] = 'email '.count($emails);
        }
        if ($publish && $sendSms && $phones !== []) {
            SendAnnouncementSmsJob::dispatch($clubId, $row->id, $data['title'], $data['body'], $phones);
            $queued[] = 'SMS '.count($phones);
        }
        if ($publish && $sendPush && $pushUserIds !== []) {
            SendAnnouncementPushJob::dispatch(
                $clubId,
                $row->id,
                $data['title'],
                $data['body'],
                $pushUserIds,
                route('admin.announcements.index'),
            );
            $queued[] = 'push '.count($pushUserIds);
        }
        if ($queued !== []) {
            return back()->with(
                'success',
                'Η ανακοίνωση ενημερώθηκε. Ουρά: '.implode(', ', $queued).'.'
            );
        }

        return back()->with('success', 'Η ανακοίνωση ενημερώθηκε.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $row = $this->clubQuery(Announcement::class, $request)->findOrFail($id);
        $title = $row->title;
        $this->deleteStoredImage($row->meta['image_path'] ?? null);
        $row->delete();

        app(ClubActivityLogger::class)->record(
            $this->clubId($request),
            'announcement.delete',
            'Διέγραψε ανακοίνωση',
            '«'.$title.'»',
            'announcement',
            $id,
        );

        return back()->with('success', 'Η ανακοίνωση διαγράφηκε.');
    }

    public function resendFailed(Request $request, string $id): RedirectResponse
    {
        $row = $this->clubQuery(Announcement::class, $request)->findOrFail($id);
        $stats = $row->emailStats();
        $failedEmails = [];
        foreach ($stats['recipients'] ?? [] as $recipient) {
            if (! is_array($recipient)) {
                continue;
            }
            if (($recipient['status'] ?? '') !== 'failed') {
                continue;
            }
            $email = trim((string) ($recipient['email'] ?? ''));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $failedEmails[$email] = true;
            }
        }

        $emails = array_keys($failedEmails);
        if ($emails === []) {
            return back()->with('error', 'Δεν υπάρχουν αποτυχημένες αποστολές για επαναποστολή.');
        }

        $meta = is_array($row->meta) ? $row->meta : [];
        SendAnnouncementEmailsJob::dispatch(
            $this->clubId($request),
            $row->id,
            $row->title,
            $row->displayBody(),
            $emails,
            is_string($meta['image_path'] ?? null) ? $meta['image_path'] : null,
            true,
        );

        $count = count($emails);

        app(ClubActivityLogger::class)->record(
            $this->clubId($request),
            'announcement.resend',
            'Επαναποστολή αποτυχημένων email ανακοίνωσης',
            sprintf('«%s» · %d παραλήπτες', $row->title, $count),
            'announcement',
            $row->id,
        );

        return back()->with(
            'success',
            "Επαναποστολή σε ουρά για {$count} αποτυχημένο/ους παραλήπτη/ες."
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['in:parent,athlete,coach,staff,member'],
            'class_ids' => ['nullable', 'array'],
            'class_ids.*' => ['uuid'],
            'parent_ids' => ['nullable', 'array'],
            'parent_ids.*' => ['uuid'],
            'coach_ids' => ['nullable', 'array'],
            'coach_ids.*' => ['uuid'],
            'athlete_ids' => ['nullable', 'array'],
            'athlete_ids.*' => ['uuid'],
            'staff_ids' => ['nullable', 'array'],
            'staff_ids.*' => ['uuid'],
            'sport' => ['nullable', 'string', 'max:120'],
            'publish' => ['boolean'],
            'send_email' => ['boolean'],
            'send_sms' => ['boolean'],
            'send_push' => ['boolean'],
            'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_image' => ['sometimes', 'nullable'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function applyImageUpload(Request $request, array $meta): array
    {
        if ($request->boolean('remove_image') && ! empty($meta['image_path'])) {
            $this->deleteStoredImage($meta['image_path']);
            unset($meta['image_path']);
        }

        $image = $request->file('image');
        if ($image !== null && $image->isValid()) {
            if (! empty($meta['image_path'])) {
                $this->deleteStoredImage($meta['image_path']);
            }
            $meta['image_path'] = $image->store('announcements', 'public');
        }

        return $meta;
    }

    private function deleteStoredImage(mixed $path): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     roles: list<string>,
     *     class_ids: list<string>,
     *     sport: string,
     *     parent_ids: list<string>,
     *     coach_ids: list<string>,
     *     athlete_ids: list<string>,
     *     staff_ids: list<string>
     * }
     */
    private function audienceFrom(array $data): array
    {
        return [
            'roles' => array_values($data['roles'] ?? []),
            'class_ids' => array_values($data['class_ids'] ?? []),
            'sport' => trim((string) ($data['sport'] ?? '')),
            'parent_ids' => array_values($data['parent_ids'] ?? []),
            'coach_ids' => array_values($data['coach_ids'] ?? []),
            'athlete_ids' => array_values($data['athlete_ids'] ?? []),
            'staff_ids' => array_values($data['staff_ids'] ?? []),
        ];
    }

    /**
     * @return array{
     *     parents: list<array{id: string, label: string, hint: string}>,
     *     coaches: list<array{id: string, label: string, hint: string}>,
     *     athletes: list<array{id: string, label: string, hint: string, sport: string, class_ids: list<string>}>,
     *     staff: list<array{id: string, label: string, hint: string}>
     * }
     */
    private function audiencePickerOptions(Request $request): array
    {
        $parents = $this->clubQuery(ParentAthleteLink::class, $request)
            ->with('parentUser:id,name,email')
            ->whereNotNull('parent_user_id')
            ->get(['id', 'parent_user_id'])
            ->groupBy('parent_user_id')
            ->map(function ($group) {
                /** @var ParentAthleteLink $first */
                $first = $group->first();
                $user = $first->parentUser;
                $email = trim((string) ($user?->email ?? ''));

                return [
                    'id' => (string) $first->parent_user_id,
                    'label' => $this->formatFullNameAsSurnameFirst($user?->name)
                        ?: ($email !== '' ? $email : 'Γονέας'),
                    'hint' => $email,
                ];
            })
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        $coaches = $this->clubQuery(Coach::class, $request)
            ->where('is_active', true)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email', 'sport'])
            ->map(fn (Coach $c) => [
                'id' => $c->id,
                'label' => $this->formatSurnameFirstName($c->last_name, $c->first_name),
                'hint' => trim((string) ($c->email ?: $c->sport ?: '')),
                'sport' => (string) ($c->sport ?? ''),
            ])
            ->values()
            ->all();

        $athletes = $this->clubQuery(Athlete::class, $request)
            ->with('classes:id,name,sport')
            ->where('status', '!=', 'inactive')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(5_000)
            ->get(['id', 'first_name', 'last_name', 'sport', 'email'])
            ->map(function (Athlete $a) {
                $classNames = $a->classes->pluck('name')->filter()->implode(', ');
                $sport = (string) ($a->sport ?: $a->classes->pluck('sport')->filter()->first() ?: '');

                return [
                    'id' => $a->id,
                    'label' => $this->formatSurnameFirstName($a->last_name, $a->first_name),
                    'hint' => trim(implode(' · ', array_filter([$classNames, $sport]))),
                    'sport' => $sport,
                    'class_ids' => $a->classes->pluck('id')->values()->all(),
                ];
            })
            ->values()
            ->all();

        $staff = $this->clubQuery(StaffMember::class, $request)
            ->where('is_active', true)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email', 'role_title'])
            ->map(fn (StaffMember $s) => [
                'id' => $s->id,
                'label' => $this->formatSurnameFirstName($s->last_name, $s->first_name)
                    ?: (string) ($s->email ?? '—'),
                'hint' => trim((string) ($s->email ?: $s->role_title ?: '')),
            ])
            ->values()
            ->all();

        return [
            'parents' => $parents,
            'coaches' => $coaches,
            'athletes' => $athletes,
            'staff' => $staff,
        ];
    }

    private function formatSurnameFirstName(?string $lastName, ?string $firstName): string
    {
        $last = trim((string) $lastName);
        $first = trim((string) $firstName);

        if ($last !== '' && $first !== '') {
            return $last.', '.$first;
        }

        return trim($last.' '.$first);
    }

    /**
     * Convert a single "Όνομα Επώνυμο" (or already "Επώνυμο, Όνομα") string.
     */
    private function formatFullNameAsSurnameFirst(?string $fullName): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) $fullName) ?? '');
        if ($name === '') {
            return '';
        }

        if (str_contains($name, ',')) {
            return $name;
        }

        $parts = preg_split('/\s+/u', $name) ?: [];
        if (count($parts) < 2) {
            return $name;
        }

        $last = array_pop($parts);

        return trim((string) $last).', '.implode(' ', $parts);
    }

    /**
     * @param  array{
     *     roles: list<string>,
     *     class_ids: list<string>,
     *     sport: string,
     *     parent_ids?: list<string>,
     *     coach_ids?: list<string>,
     *     athlete_ids?: list<string>,
     *     staff_ids?: list<string>
     * }  $audience
     * @return list<string>
     */
    private function resolveAudienceEmails(Request $request, array $audience): array
    {
        $roles = $audience['roles'] ?? [];
        $emails = [];

        $add = function (?string $email) use (&$emails): void {
            $email = trim((string) $email);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[$email] = true;
            }
        };

        if ($roles === [] || in_array('parent', $roles, true)) {
            $parentIds = array_values($audience['parent_ids'] ?? []);
            if ($parentIds !== []) {
                User::query()
                    ->whereIn('id', $parentIds)
                    ->pluck('email')
                    ->each(fn ($email) => $add(is_string($email) ? $email : null));
            } else {
                foreach ($this->scopedAthletes($request, $audience)->get(['id', 'email', 'meta']) as $athlete) {
                    $add($this->resolveNotifyEmail($athlete));
                }
            }
        }

        if (in_array('athlete', $roles, true)) {
            $athleteIds = array_values($audience['athlete_ids'] ?? []);
            $athletes = $this->scopedAthletes($request, $audience)
                ->when($athleteIds !== [], fn ($q) => $q->whereIn('id', $athleteIds))
                ->get(['id', 'email', 'meta']);
            foreach ($athletes as $athlete) {
                $add($this->resolveNotifyEmail($athlete));
            }
        }

        if (in_array('coach', $roles, true)) {
            $coachIds = array_values($audience['coach_ids'] ?? []);
            $this->clubQuery(Coach::class, $request)
                ->where('is_active', true)
                ->when($coachIds !== [], fn ($q) => $q->whereIn('id', $coachIds))
                ->pluck('email')
                ->each(fn ($email) => $add(is_string($email) ? $email : null));
        }

        if (in_array('staff', $roles, true)) {
            $staffIds = array_values($audience['staff_ids'] ?? []);
            $this->clubQuery(StaffMember::class, $request)
                ->where('is_active', true)
                ->when($staffIds !== [], fn ($q) => $q->whereIn('id', $staffIds))
                ->pluck('email')
                ->each(fn ($email) => $add(is_string($email) ? $email : null));
        }

        return array_keys($emails);
    }

    /**
     * @param  array{roles?: list<string>, class_ids?: list<string>, sport?: string, athlete_ids?: list<string>}  $audience
     * @return list<string>
     */
    private function resolveAudiencePhones(Request $request, array $audience): array
    {
        $phones = [];
        $add = function (?string $phone) use (&$phones): void {
            $phone = preg_replace('/\s+/', '', trim((string) $phone)) ?? '';
            if (strlen($phone) >= 8) {
                $phones[$phone] = true;
            }
        };

        $roles = $audience['roles'] ?? [];
        if ($roles === [] || in_array('parent', $roles, true) || in_array('athlete', $roles, true)) {
            foreach ($this->scopedAthletes($request, $audience)->get(['phone', 'guardian_phone', 'mother_phone', 'emergency_phone']) as $athlete) {
                $add($athlete->phone);
                $add($athlete->guardian_phone);
                $add($athlete->mother_phone);
                $add($athlete->emergency_phone);
            }
        }
        if (in_array('coach', $roles, true)) {
            $this->clubQuery(Coach::class, $request)->where('is_active', true)->pluck('phone')
                ->each(fn ($phone) => $add(is_string($phone) ? $phone : null));
        }
        if (in_array('staff', $roles, true)) {
            $this->clubQuery(StaffMember::class, $request)->where('is_active', true)->pluck('phone')
                ->each(fn ($phone) => $add(is_string($phone) ? $phone : null));
        }

        return array_keys($phones);
    }

    /**
     * @param  array{roles?: list<string>, parent_ids?: list<string>}  $audience
     * @return list<int>
     */
    private function resolveAudienceUserIds(Request $request, array $audience): array
    {
        $roles = $audience['roles'] ?? [];
        $ids = [];

        if ($roles === [] || in_array('parent', $roles, true)) {
            $parentIds = array_values($audience['parent_ids'] ?? []);
            if ($parentIds !== []) {
                foreach ($parentIds as $id) {
                    $ids[(int) $id] = true;
                }
            } else {
                ParentAthleteLink::query()
                    ->withoutGlobalScopes()
                    ->where('club_id', $this->clubId($request))
                    ->where('receives_push', true)
                    ->pluck('parent_user_id')
                    ->each(function ($id) use (&$ids) {
                        if ($id) {
                            $ids[(int) $id] = true;
                        }
                    });
            }
        }

        return array_map('intval', array_keys($ids));
    }

    /**
     * @param  array{class_ids?: list<string>, sport?: string}  $audience
     * @return Builder<Athlete>
     */
    private function scopedAthletes(Request $request, array $audience)
    {
        $classIds = array_values($audience['class_ids'] ?? []);
        $sport = trim((string) ($audience['sport'] ?? ''));

        return $this->clubQuery(Athlete::class, $request)
            ->where('status', '!=', 'inactive')
            ->when($sport !== '', function ($q) use ($sport) {
                $q->where(function ($w) use ($sport) {
                    $w->where('sport', $sport)
                        ->orWhereHas('classes', fn ($c) => $c->where('sport', $sport));
                });
            })
            ->when($classIds !== [], function ($q) use ($classIds) {
                $q->whereHas('classes', fn ($c) => $c->whereIn('academy_classes.id', $classIds));
            });
    }

    private function resolveNotifyEmail(Athlete $athlete): ?string
    {
        return PreferredNotifyEmail::resolvePrimary($athlete);
    }
}
