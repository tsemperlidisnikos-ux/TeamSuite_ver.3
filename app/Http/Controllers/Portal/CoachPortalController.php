<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Jobs\SendAnnouncementEmailsJob;
use App\Jobs\SendAnnouncementPushJob;
use App\Jobs\SendAnnouncementSmsJob;
use App\Models\AcademyClass;
use App\Models\Announcement;
use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\Coach;
use App\Models\Facility;
use App\Models\ParentAthleteLink;
use App\Models\Scopes\ClubScope;
use App\Models\StaffMember;
use App\Models\Training;
use App\Services\Audit\ClubActivityLogger;
use App\Support\CoachClassScope;
use App\Support\PreferredNotifyEmail;
use App\Support\Roles;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CoachPortalController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $club = $request->attributes->get('currentClub') ?? $user?->activeClub;
        $role = $user?->is_platform_admin
            ? Roles::PLATFORM_ADMIN
            : $user?->roleInClub($club);

        if ($role !== Roles::COACH) {
            return Inertia::render('Portals/WrongApp', [
                'audience' => 'coach',
                'roleLabel' => Roles::label($role) ?: 'χρήστης',
            ]);
        }

        $clubId = $user->active_club_id;
        $today = now()->toDateString();
        $date = $this->resolveDate($request->query('date'), $today);

        $coach = $this->resolveCoach($user, $clubId);

        $myClasses = $coach && $clubId
            ? AcademyClass::query()->withoutGlobalScopes()
                ->where('club_id', $clubId)
                ->where('coach_id', $coach->id)
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        $classIds = $myClasses->pluck('id');

        $facilityNames = $clubId
            ? Facility::query()->withoutGlobalScopes()
                ->where('club_id', $clubId)
                ->pluck('name', 'id')
            : collect();

        $trainings = $this->coachTrainings($clubId, $classIds, $myClasses, $facilityNames);

        $announcements = $clubId
            ? Announcement::query()
                ->withoutGlobalScope(ClubScope::class)
                ->where('club_id', $clubId)
                ->latest('published_at')
                ->limit(20)
                ->get(['id', 'title', 'body', 'published_at', 'meta'])
            : collect();

        $athleteCount = $classIds->isEmpty()
            ? 0
            : Athlete::query()->withoutGlobalScopes()
                ->where('club_id', $clubId)
                ->where('status', '!=', 'inactive')
                ->whereHas('classes', fn ($q) => $q->whereIn('academy_classes.id', $classIds))
                ->count();

        $rosterByClass = $this->rosterByClass($clubId, $myClasses);
        $attendanceByClass = $this->attendanceForDate($clubId, $classIds, $date);
        $audienceOptions = $this->announcementAudienceOptions($clubId, $classIds);

        return Inertia::render('Portals/CoachHome', [
            'coach' => $coach ? [
                'id' => $coach->id,
                'first_name' => $coach->first_name,
                'last_name' => $coach->last_name,
            ] : null,
            'classes' => $myClasses->map(fn (AcademyClass $c) => [
                'id' => $c->id,
                'name' => $c->name,
            ])->values(),
            'audience_options' => $audienceOptions,
            'trainings' => $trainings->values(),
            'announcements' => $announcements->map(fn (Announcement $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'body' => $a->body,
                'published_at' => optional($a->published_at)?->toIso8601String(),
                'image_url' => $a->imageUrl(),
            ])->values(),
            'classes_count' => $myClasses->count(),
            'athlete_count' => $athleteCount,
            'roster_by_class' => $rosterByClass,
            'attendance_today' => $attendanceByClass,
            'attendance_date' => $date,
            'today' => $today,
        ]);
    }

    public function saveAttendance(Request $request): RedirectResponse
    {
        $user = $request->user();
        $club = $request->attributes->get('currentClub') ?? $user?->activeClub;
        $role = $user?->is_platform_admin
            ? Roles::PLATFORM_ADMIN
            : $user?->roleInClub($club);

        if ($role !== Roles::COACH) {
            abort(403);
        }

        $data = $request->validate([
            'class_id' => ['required', 'uuid'],
            'date' => ['required', 'date'],
            'marks' => ['required', 'array'],
            'marks.*.athlete_id' => ['required', 'uuid'],
            'marks.*.status' => ['required', 'in:present,absent'],
            'notify_absence' => ['sometimes', 'boolean'],
        ]);

        $clubId = $user->active_club_id;
        $coach = $this->resolveCoach($user, $clubId);

        if (! $coach || ! $clubId) {
            return back()->with('error', 'Δεν βρέθηκε σύνδεση προπονητή.');
        }

        $class = AcademyClass::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->where('coach_id', $coach->id)
            ->whereKey($data['class_id'])
            ->firstOrFail();

        $allowedAthleteIds = Athlete::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->where('status', '!=', 'inactive')
            ->whereHas('classes', fn ($q) => $q->where('academy_classes.id', $class->id))
            ->pluck('id')
            ->all();

        $allowed = array_flip($allowedAthleteIds);

        foreach ($data['marks'] as $mark) {
            if (! isset($allowed[$mark['athlete_id']])) {
                continue;
            }

            $this->upsertMark(
                $clubId,
                $class->id,
                $mark['athlete_id'],
                $data['date'],
                $mark['status'],
            );
        }

        return back()->with('success', 'Οι παρουσίες αποθηκεύτηκαν.');
    }

    public function storeAnnouncement(Request $request): RedirectResponse
    {
        $user = $request->user();
        $club = $request->attributes->get('currentClub') ?? $user?->activeClub;
        $role = $user?->is_platform_admin
            ? Roles::PLATFORM_ADMIN
            : $user?->roleInClub($club);

        if ($role !== Roles::COACH) {
            abort(403);
        }

        $clubId = $user?->active_club_id;
        $coach = $this->resolveCoach($user, $clubId);
        if (! $coach || ! $clubId) {
            return back()->with('error', 'Δεν βρέθηκε σύνδεση προπονητή.');
        }

        $ownClassIds = AcademyClass::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->where('coach_id', $coach->id)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $ownAthleteIds = $ownClassIds === []
            ? []
            : Athlete::query()->withoutGlobalScopes()
                ->where('club_id', $clubId)
                ->where('status', '!=', 'inactive')
                ->whereHas('classes', fn ($q) => $q->whereIn('academy_classes.id', $ownClassIds))
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->all();

        $clubCoachIds = Coach::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $clubStaffIds = StaffMember::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string'],
            'class_ids' => ['nullable', 'array'],
            'class_ids.*' => ['uuid'],
            'athlete_ids' => ['nullable', 'array'],
            'athlete_ids.*' => ['uuid'],
            'coach_ids' => ['nullable', 'array'],
            'coach_ids.*' => ['uuid'],
            'staff_ids' => ['nullable', 'array'],
            'staff_ids.*' => ['uuid'],
            'send_email' => ['sometimes', 'boolean'],
            'send_sms' => ['sometimes', 'boolean'],
            'send_push' => ['sometimes', 'boolean'],
        ]);

        $selectedClassIds = array_values(array_intersect(
            array_map('strval', $data['class_ids'] ?? []),
            $ownClassIds,
        ));
        $selectedAthleteIds = array_values(array_intersect(
            array_map('strval', $data['athlete_ids'] ?? []),
            $ownAthleteIds,
        ));
        $selectedCoachIds = array_values(array_intersect(
            array_map('strval', $data['coach_ids'] ?? []),
            $clubCoachIds,
        ));
        $selectedStaffIds = array_values(array_intersect(
            array_map('strval', $data['staff_ids'] ?? []),
            $clubStaffIds,
        ));

        if (
            $selectedClassIds === []
            && $selectedAthleteIds === []
            && $selectedCoachIds === []
            && $selectedStaffIds === []
        ) {
            return back()->withErrors([
                'class_ids' => 'Επιλέξτε τουλάχιστον ένα τμήμα, αθλητή, προπονητή ή μέλος προσωπικού.',
            ])->withInput();
        }

        $roles = [];
        if ($selectedClassIds !== []) {
            $roles[] = 'parent';
        }
        if ($selectedAthleteIds !== []) {
            $roles[] = 'athlete';
        }
        if ($selectedCoachIds !== []) {
            $roles[] = 'coach';
        }
        if ($selectedStaffIds !== []) {
            $roles[] = 'staff';
        }

        $sendEmail = array_key_exists('send_email', $data)
            ? filter_var($data['send_email'], FILTER_VALIDATE_BOOLEAN)
            : true;
        $sendSms = filter_var($data['send_sms'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $sendPush = filter_var($data['send_push'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $audience = [
            'roles' => array_values($roles),
            'class_ids' => $selectedClassIds,
            'sport' => '',
            'parent_ids' => [],
            'coach_ids' => $selectedCoachIds,
            'athlete_ids' => $selectedAthleteIds,
            'staff_ids' => $selectedStaffIds,
        ];

        $emails = [];
        $phones = [];
        $addEmail = function (?string $email) use (&$emails): void {
            $email = trim((string) $email);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[$email] = true;
            }
        };
        $addPhone = function (?string $phone) use (&$phones): void {
            $phone = preg_replace('/\s+/', '', trim((string) $phone)) ?? '';
            if (strlen($phone) >= 8) {
                $phones[$phone] = true;
            }
        };

        $pushAthleteIds = [];

        if ($selectedClassIds !== []) {
            $classAthletes = Athlete::query()->withoutGlobalScopes()
                ->where('club_id', $clubId)
                ->where('status', '!=', 'inactive')
                ->whereHas('classes', fn ($q) => $q->whereIn('academy_classes.id', $selectedClassIds))
                ->get(['id', 'email', 'phone', 'guardian_phone', 'mother_phone', 'emergency_phone', 'meta']);

            foreach ($classAthletes as $athlete) {
                $addEmail(PreferredNotifyEmail::resolvePrimary($athlete));
                $addPhone(is_string($athlete->phone ?? null) ? $athlete->phone : null);
                $addPhone(is_string($athlete->guardian_phone ?? null) ? $athlete->guardian_phone : null);
                $addPhone(is_string($athlete->mother_phone ?? null) ? $athlete->mother_phone : null);
                $addPhone(is_string($athlete->emergency_phone ?? null) ? $athlete->emergency_phone : null);
                $pushAthleteIds[] = (string) $athlete->id;
            }
        }

        if ($selectedAthleteIds !== []) {
            $pickedAthletes = Athlete::query()->withoutGlobalScopes()
                ->where('club_id', $clubId)
                ->whereIn('id', $selectedAthleteIds)
                ->get(['id', 'email', 'phone', 'meta']);

            foreach ($pickedAthletes as $athlete) {
                $addEmail(PreferredNotifyEmail::resolvePrimary($athlete));
                $addPhone(is_string($athlete->phone ?? null) ? $athlete->phone : null);
                $pushAthleteIds[] = (string) $athlete->id;
            }
        }

        if ($selectedCoachIds !== []) {
            $pickedCoaches = Coach::query()->withoutGlobalScopes()
                ->where('club_id', $clubId)
                ->whereIn('id', $selectedCoachIds)
                ->get(['email', 'phone']);

            foreach ($pickedCoaches as $pickedCoach) {
                $addEmail(is_string($pickedCoach->email ?? null) ? $pickedCoach->email : null);
                $addPhone(is_string($pickedCoach->phone ?? null) ? $pickedCoach->phone : null);
            }
        }

        if ($selectedStaffIds !== []) {
            $pickedStaff = StaffMember::query()->withoutGlobalScopes()
                ->where('club_id', $clubId)
                ->whereIn('id', $selectedStaffIds)
                ->get(['email', 'phone']);

            foreach ($pickedStaff as $member) {
                $addEmail(is_string($member->email ?? null) ? $member->email : null);
                $addPhone(is_string($member->phone ?? null) ? $member->phone : null);
            }
        }

        $emailList = array_keys($emails);
        $phoneList = array_keys($phones);
        $pushAthleteIds = array_values(array_unique($pushAthleteIds));

        $pushUserIds = $pushAthleteIds === []
            ? []
            : ParentAthleteLink::query()
                ->withoutGlobalScopes()
                ->where('club_id', $clubId)
                ->whereIn('athlete_id', $pushAthleteIds)
                ->where('receives_push', true)
                ->pluck('parent_user_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

        if ($sendEmail && $emailList === []) {
            return back()->withErrors([
                'send_email' => 'Ενεργή αποστολή email, αλλά δεν βρέθηκαν παραλήπτες με email.',
            ])->withInput();
        }
        if ($sendSms && $phoneList === []) {
            return back()->withErrors([
                'send_sms' => 'Ενεργή αποστολή SMS, αλλά δεν βρέθηκαν τηλέφωνα.',
            ])->withInput();
        }

        $row = Announcement::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $clubId,
            'created_by' => $user?->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'audience' => $audience,
            'published_at' => now(),
            'meta' => [
                'send_email' => $sendEmail,
                'send_sms' => $sendSms,
                'send_push' => $sendPush,
                'source' => 'coach_portal',
            ],
        ]);

        app(ClubActivityLogger::class)->record(
            $clubId,
            'announcement.create',
            'Δημιούργησε ανακοίνωση (προπονητής)',
            sprintf(
                '«%s» · τμήματα: %d · αθλητές: %d · προπονητές: %d · προσωπικό: %d',
                $row->title,
                count($selectedClassIds),
                count($selectedAthleteIds),
                count($selectedCoachIds),
                count($selectedStaffIds),
            ),
            'announcement',
            $row->id,
        );

        $channels = [];
        if ($sendEmail && $emailList !== []) {
            SendAnnouncementEmailsJob::dispatch(
                $clubId,
                $row->id,
                $data['title'],
                $data['body'],
                $emailList,
                null,
            );
            $channels[] = 'email σε '.count($emailList).' παραλήπτες';
        }
        if ($sendSms && $phoneList !== []) {
            SendAnnouncementSmsJob::dispatch($clubId, $row->id, $data['title'], $data['body'], $phoneList);
            $channels[] = 'SMS σε '.count($phoneList);
        }
        if ($sendPush && $pushUserIds !== []) {
            SendAnnouncementPushJob::dispatch(
                $clubId,
                $row->id,
                $data['title'],
                $data['body'],
                $pushUserIds,
                route('portal.coach'),
            );
            $channels[] = 'push σε '.count($pushUserIds);
        }

        return back()->with(
            'success',
            $channels !== []
                ? 'Η ανακοίνωση δημοσιεύτηκε. Στάλθηκε: '.implode(', ', $channels).'.'
                : 'Η ανακοίνωση δημοσιεύτηκε.'
        );
    }

    /**
     * @param  Collection<int, string>  $ownClassIds
     * @return array{
     *     athletes: list<array{id: string, label: string, hint: string, class_ids: list<string>}>,
     *     coaches: list<array{id: string, label: string, hint: string}>,
     *     staff: list<array{id: string, label: string, hint: string}>
     * }
     */
    private function announcementAudienceOptions(?string $clubId, Collection $ownClassIds): array
    {
        if (! $clubId) {
            return [
                'athletes' => [],
                'coaches' => [],
                'staff' => [],
            ];
        }

        $athletes = $ownClassIds->isEmpty()
            ? collect()
            : Athlete::query()->withoutGlobalScopes()
                ->with('classes:id,name')
                ->where('club_id', $clubId)
                ->where('status', '!=', 'inactive')
                ->whereHas('classes', fn ($q) => $q->whereIn('academy_classes.id', $ownClassIds))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->limit(2_000)
                ->get(['id', 'first_name', 'last_name', 'email'])
                ->map(function (Athlete $a) use ($ownClassIds) {
                    $classIds = $a->classes
                        ->pluck('id')
                        ->map(fn ($id) => (string) $id)
                        ->intersect($ownClassIds->map(fn ($id) => (string) $id))
                        ->values()
                        ->all();
                    $classNames = $a->classes
                        ->filter(fn ($c) => in_array((string) $c->id, $classIds, true))
                        ->pluck('name')
                        ->filter()
                        ->implode(', ');

                    return [
                        'id' => (string) $a->id,
                        'label' => $this->formatSurnameFirstName($a->last_name, $a->first_name),
                        'hint' => $classNames,
                        'class_ids' => $classIds,
                    ];
                });

        $coaches = Coach::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->where('is_active', true)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email', 'sport'])
            ->map(fn (Coach $c) => [
                'id' => (string) $c->id,
                'label' => $this->formatSurnameFirstName($c->last_name, $c->first_name),
                'hint' => trim((string) ($c->email ?: $c->sport ?: '')),
            ]);

        $staff = StaffMember::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->where('is_active', true)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email', 'role_title'])
            ->map(fn (StaffMember $s) => [
                'id' => (string) $s->id,
                'label' => $this->formatSurnameFirstName($s->last_name, $s->first_name)
                    ?: (string) ($s->email ?? '—'),
                'hint' => trim((string) ($s->email ?: $s->role_title ?: '')),
            ]);

        return [
            'athletes' => $athletes->values()->all(),
            'coaches' => $coaches->values()->all(),
            'staff' => $staff->values()->all(),
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

    private function resolveCoach($user, ?string $clubId): ?Coach
    {
        if (! $user || ! $clubId) {
            return null;
        }

        return CoachClassScope::resolveCoach($user, $clubId);
    }

    private function resolveDate(mixed $raw, string $fallback): string
    {
        if (! is_string($raw) || $raw === '') {
            return $fallback;
        }

        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private function upsertMark(
        string $clubId,
        string $classId,
        string $athleteId,
        string $date,
        string $status,
    ): void {
        $existing = Attendance::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->where('athlete_id', $athleteId)
            ->whereDate('attended_on', $date)
            ->where(function ($q) use ($classId) {
                $q->whereNull('training_id')
                    ->where(function ($inner) use ($classId) {
                        $inner->where('meta->class_id', $classId)
                            ->orWhereNull('meta');
                    });
            })
            ->first();

        if ($existing) {
            $existing->update([
                'status' => $status,
                'meta' => array_merge($existing->meta ?? [], ['class_id' => $classId]),
            ]);

            return;
        }

        Attendance::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $clubId,
            'athlete_id' => $athleteId,
            'training_id' => null,
            'status' => $status,
            'attended_on' => $date,
            'meta' => ['class_id' => $classId],
        ]);
    }

    /**
     * @param  Collection<int, string>  $classIds
     * @param  Collection<int, AcademyClass>  $myClasses
     * @return Collection<int, array<string, mixed>>
     */
    private function coachTrainings(
        ?string $clubId,
        Collection $classIds,
        Collection $myClasses,
        Collection $facilityNames,
    ): Collection {
        if (! $clubId || $classIds->isEmpty()) {
            return collect();
        }

        $classNameById = $myClasses->pluck('name', 'id');

        return Training::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->where('starts_at', '>=', now()->subDay())
            ->whereIn('academy_class_id', $classIds)
            ->orderBy('starts_at')
            ->limit(30)
            ->get(['id', 'title', 'starts_at', 'ends_at', 'academy_class_id', 'facility_id', 'meta'])
            ->map(fn (Training $t) => [
                'id' => $t->id,
                'title' => $t->title,
                'starts_at' => $t->starts_at?->toIso8601String(),
                'ends_at' => $t->ends_at?->toIso8601String(),
                'class_name' => $classNameById[$t->academy_class_id] ?? ($t->title ?: 'Προπόνηση'),
                'location' => $t->facility_id
                    ? ($facilityNames[$t->facility_id] ?? null)
                    : data_get($t->meta, 'location'),
            ]);
    }

    /**
     * @param  Collection<int, AcademyClass>  $myClasses
     * @return array<string, list<array<string, mixed>>>
     */
    private function rosterByClass(?string $clubId, Collection $myClasses): array
    {
        if (! $clubId || $myClasses->isEmpty()) {
            return [];
        }

        $out = [];
        foreach ($myClasses as $class) {
            $out[$class->id] = Athlete::query()->withoutGlobalScopes()
                ->where('club_id', $clubId)
                ->where('status', '!=', 'inactive')
                ->whereHas('classes', fn ($q) => $q->where('academy_classes.id', $class->id))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name'])
                ->map(fn (Athlete $a) => [
                    'id' => $a->id,
                    'first_name' => $a->first_name,
                    'last_name' => $a->last_name,
                ])
                ->all();
        }

        return $out;
    }

    /**
     * @param  Collection<int, string>  $classIds
     * @return array<string, array<string, string>>
     */
    private function attendanceForDate(?string $clubId, Collection $classIds, string $date): array
    {
        if (! $clubId || $classIds->isEmpty()) {
            return [];
        }

        $rows = Attendance::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->whereDate('attended_on', $date)
            ->get(['athlete_id', 'status', 'meta']);

        $out = [];
        foreach ($rows as $row) {
            $classId = (string) data_get($row->meta, 'class_id', '');
            if ($classId === '' || ! $classIds->contains($classId)) {
                continue;
            }
            $out[$classId][$row->athlete_id] = $row->status;
        }

        return $out;
    }
}
