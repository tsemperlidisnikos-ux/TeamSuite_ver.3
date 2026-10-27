<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\InteractsWithClubResource;
use App\Http\Controllers\Controller;
use App\Jobs\SendHealthCardNoticeEmailsJob;
use App\Models\AcademyClass;
use App\Models\Announcement;
use App\Models\Association;
use App\Models\Athlete;
use App\Models\AthleteChangeLog;
use App\Models\AthleteTransaction;
use App\Models\Attendance;
use App\Models\ClothingPackage;
use App\Models\Club;
use App\Models\ProgressReport;
use App\Models\RegistrationApplication;
use App\Models\Scopes\ClubScope;
use App\Models\SizeChart;
use App\Models\Sport;
use App\Models\Training;
use App\Services\Athletes\AthleteCsvService;
use App\Services\Audit\ClubActivityLogger;
use App\Services\Gdpr\AmkaService;
use App\Support\GreekGivenName;
use App\Support\PreferredNotifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AthleteController extends Controller
{
    use InteractsWithClubResource;

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');
        $sport = trim((string) $request->query('sport', ''));
        $classId = $request->query('class_id');

        $query = $this->clubQuery(Athlete::class, $request)
            ->with(['classes:id,name']);

        if ($q !== '') {
            $like = '%'.$q.'%';
            $op = config('database.default') === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($w) use ($like, $op) {
                $w->where('first_name', $op, $like)
                    ->orWhere('last_name', $op, $like)
                    ->orWhere('email', $op, $like)
                    ->orWhere('phone', $op, $like)
                    ->orWhere('guardian_name', $op, $like);
            });
        }

        if (in_array($status, ['active', 'inactive', 'trial'], true)) {
            $query->where('status', $status);
        }

        if ($sport !== '') {
            $query->where('sport', 'like', '%'.$sport.'%');
        }

        if ($classId === 'none') {
            $query->whereDoesntHave('classes');
        } elseif (is_string($classId) && $classId !== '') {
            $query->whereHas('classes', fn ($c) => $c->where('academy_classes.id', $classId));
        }

        // Full list with scroll in the UI (no page splits).
        $rows = $query->orderBy('last_name')->orderBy('first_name')
            ->paginate(10_000)
            ->withQueryString()
            ->through(fn (Athlete $a) => [
                'id' => $a->id,
                'first_name' => $a->first_name,
                'last_name' => $a->last_name,
                'email' => $a->email,
                'phone' => $a->phone,
                'guardian_name' => $a->guardian_name,
                'guardian_phone' => $a->guardian_phone,
                'photo_url' => $a->photo_url ?? data_get($a->meta, 'photo_url'),
                'status' => $a->status,
                'sport' => $a->sport,
                'monthly_fee' => $a->monthly_fee,
                'health_card' => $a->health_card,
                'health_card_expires' => optional($a->health_card_expires)->format('Y-m-d'),
                'class_names' => $a->classes->pluck('name')->implode(', '),
            ]);

        $clubId = $this->clubId($request);
        $today = Carbon::today();
        $soon = $today->copy()->addDays(30);

        $healthNoticeRows = $this->clubQuery(Athlete::class, $request)
            ->whereNotNull('health_card_expires')
            ->whereDate('health_card_expires', '<=', $soon)
            ->orderBy('health_card_expires')
            ->limit(100)
            ->get(['id', 'first_name', 'last_name', 'health_card_expires'])
            ->map(function (Athlete $a) use ($today) {
                $expires = $a->health_card_expires;
                $status = $expires->lt($today) ? 'expired' : 'soon';

                return [
                    'id' => $a->id,
                    'name' => trim($a->last_name.' '.$a->first_name),
                    'expires' => $expires->format('Y-m-d'),
                    'status' => $status,
                ];
            })
            ->values();

        $pendingApplications = RegistrationApplication::query()
            ->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->whereIn('status', ['pending', 'new', 'waiting'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get([
                'id',
                'type',
                'status',
                'first_name',
                'last_name',
                'email',
                'phone',
                'form_snapshot',
                'created_at',
            ])
            ->map(function (RegistrationApplication $app) {
                $snapshot = is_array($app->form_snapshot) ? $app->form_snapshot : [];

                return [
                    'id' => $app->id,
                    'type' => $app->type,
                    'status' => $app->status,
                    'first_name' => $app->first_name,
                    'last_name' => $app->last_name,
                    'email' => $app->email,
                    'phone' => $app->phone,
                    'guardian_name' => $snapshot['guardian_name']
                        ?? $snapshot['guardianName']
                        ?? null,
                    'guardian_phone' => $snapshot['guardian_phone']
                        ?? $snapshot['guardianPhone']
                        ?? $app->phone,
                    'class_id' => $snapshot['class_id']
                        ?? $snapshot['classId']
                        ?? null,
                    'notes' => $snapshot['notes'] ?? $snapshot['comments'] ?? null,
                    'created_at' => optional($app->created_at)->toIso8601String(),
                ];
            });

        return Inertia::render('Admin/Athletes/Index', [
            'rows' => $rows,
            'filters' => [
                'q' => $q,
                'status' => $status ?: '',
                'sport' => $sport,
                'class_id' => $classId ?: '',
            ],
            'classes' => $this->clubQuery(AcademyClass::class, $request)
                ->orderBy('name')
                ->get(['id', 'name']),
            'stats' => [
                'active' => $this->clubQuery(Athlete::class, $request)->where('status', 'active')->count(),
                'trial' => $this->clubQuery(Athlete::class, $request)->where('status', 'trial')->count(),
                'inactive' => $this->clubQuery(Athlete::class, $request)->where('status', 'inactive')->count(),
            ],
            'healthNoticeRows' => $healthNoticeRows,
            'pendingApplications' => $pendingApplications,
        ]);
    }

    public function bulkUpdate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'uuid',
            'status' => 'nullable|in:active,inactive,trial',
            'sport' => 'nullable|string|max:120',
            'gender' => 'nullable|in:male,female,other',
            'class_id' => 'nullable|string',
            'health_card' => 'nullable|in:0,1,true,false',
        ]);

        $clubId = $this->clubId($request);
        $athletes = Athlete::query()
            ->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->whereIn('id', $data['ids'])
            ->get();

        foreach ($athletes as $athlete) {
            $patch = [];
            if (! empty($data['status'])) {
                $patch['status'] = $data['status'];
            }
            if (array_key_exists('sport', $data) && $data['sport'] !== null && $data['sport'] !== '') {
                $patch['sport'] = $data['sport'];
            }
            if (! empty($data['gender'])) {
                $patch['gender'] = $data['gender'];
            }
            if (array_key_exists('health_card', $data) && $data['health_card'] !== null && $data['health_card'] !== '') {
                $patch['health_card'] = filter_var($data['health_card'], FILTER_VALIDATE_BOOLEAN);
            }
            if ($patch !== []) {
                $athlete->update($patch);
            }

            if (! empty($data['class_id']) && $data['class_id'] !== 'none') {
                $athlete->classes()->syncWithoutDetaching([
                    $data['class_id'] => ['is_primary' => false],
                ]);
            } elseif (($data['class_id'] ?? null) === 'none') {
                $athlete->classes()->detach();
            }
        }

        return back()->with('success', 'Μαζική αλλαγή: ενημερώθηκαν '.$athletes->count().' αθλητές.');
    }

    public function approveApplication(Request $request, string $id): RedirectResponse
    {
        $clubId = $this->clubId($request);
        $app = RegistrationApplication::query()
            ->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->findOrFail($id);

        $status = ($app->type === 'trial') ? 'trial' : 'active';
        $snapshot = is_array($app->form_snapshot) ? $app->form_snapshot : [];

        $athlete = Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $clubId,
            'first_name' => $app->first_name,
            'last_name' => $app->last_name,
            'email' => $app->email,
            'phone' => $app->phone,
            'guardian_name' => $snapshot['guardian_name'] ?? $snapshot['guardianName'] ?? null,
            'guardian_phone' => $snapshot['guardian_phone'] ?? $snapshot['guardianPhone'] ?? $app->phone,
            'status' => $status,
            'sport' => $snapshot['sport'] ?? null,
            'gdpr_consent' => $app->gdpr_accepted ? 'accepted' : 'pending',
            'meta' => [
                'registration_application_id' => $app->id,
                'registration_form_snapshot' => $snapshot,
            ],
        ]);

        $classId = $snapshot['class_id'] ?? $snapshot['classId'] ?? null;
        if (is_string($classId) && $classId !== '') {
            $ownsClass = AcademyClass::query()->withoutGlobalScopes()
                ->where('club_id', $clubId)
                ->whereKey($classId)
                ->exists();
            if ($ownsClass) {
                $athlete->classes()->syncWithoutDetaching([
                    $classId => ['is_primary' => true],
                ]);
            }
        }

        $app->update(['status' => 'approved']);

        $this->logChange($request, $athlete, 'created', null, 'Έγκριση αίτησης εγγραφής');

        return redirect()
            ->route('admin.athletes.show', $athlete->id)
            ->with('success', 'Η αίτηση εγκρίθηκε και καταχωρήθηκε στους αθλητές.');
    }

    public function updateApplication(Request $request, string $id): RedirectResponse
    {
        $clubId = $this->clubId($request);
        $app = RegistrationApplication::query()
            ->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->findOrFail($id);

        $data = $request->validate([
            'first_name' => 'required|string|max:120',
            'last_name' => 'required|string|max:120',
            'email' => 'nullable|email|max:190',
            'phone' => 'nullable|string|max:40',
            'guardian_name' => 'nullable|string|max:160',
            'guardian_phone' => 'nullable|string|max:40',
            'class_id' => 'nullable|uuid',
            'type' => 'nullable|in:full,trial,waitlist',
            'notes' => 'nullable|string|max:2000',
        ]);

        $snapshot = is_array($app->form_snapshot) ? $app->form_snapshot : [];
        $snapshot['guardian_name'] = $data['guardian_name'] ?? null;
        $snapshot['guardianName'] = $data['guardian_name'] ?? null;
        $snapshot['guardian_phone'] = $data['guardian_phone'] ?? null;
        $snapshot['guardianPhone'] = $data['guardian_phone'] ?? null;
        $snapshot['class_id'] = $data['class_id'] ?? null;
        $snapshot['classId'] = $data['class_id'] ?? null;
        $snapshot['notes'] = $data['notes'] ?? null;

        $app->fill([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? ($data['guardian_phone'] ?? $app->phone),
            'type' => $data['type'] ?? $app->type,
            'form_snapshot' => $snapshot,
        ])->save();

        return back()->with('success', 'Η αίτηση ενημερώθηκε.');
    }

    public function remindHealthCards(Request $request): RedirectResponse
    {
        $clubId = $this->clubId($request);
        $today = Carbon::today();
        $soon = $today->copy()->addDays(30);

        $athletes = $this->clubQuery(Athlete::class, $request)
            ->whereNotNull('health_card_expires')
            ->whereDate('health_card_expires', '<=', $soon)
            ->orderBy('health_card_expires')
            ->limit(200)
            ->get(['id', 'first_name', 'last_name', 'email', 'phone', 'guardian_phone', 'meta', 'health_card_expires']);

        $recipients = [];
        foreach ($athletes as $athlete) {
            $email = $this->resolveNotifyEmail($athlete);
            if (! $email) {
                continue;
            }
            $expires = optional($athlete->health_card_expires)->format('Y-m-d');
            $recipients[] = [
                'athlete_id' => $athlete->id,
                'name' => trim($athlete->last_name.' '.$athlete->first_name),
                'email' => $email,
                'expires' => $expires ?: '',
                'status' => $athlete->health_card_expires && $athlete->health_card_expires->lt($today) ? 'expired' : 'soon',
            ];
        }

        if ($recipients === []) {
            return back()->with('success', 'Δεν υπάρχουν λήξεις καρτών με email προς υπενθύμιση.');
        }

        $clubName = (string) (Club::query()->whereKey($clubId)->value('name') ?: 'TeamSuite');
        $staffEmails = $request->user()?->email
            ? [(string) $request->user()->email]
            : [];

        SendHealthCardNoticeEmailsJob::dispatch($clubId, $clubName, $recipients, $staffEmails);

        $count = count($recipients);

        return back()->with(
            'success',
            "Υπενθυμίσεις καρτών: στάλθηκαν στην ουρά για {$count} αθλητές (το πολύ μία φορά την ημέρα ανά αθλητή).",
        );
    }

    public function rejectApplication(Request $request, string $id): RedirectResponse
    {
        $clubId = $this->clubId($request);
        $app = RegistrationApplication::query()
            ->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->findOrFail($id);

        $app->update(['status' => 'rejected']);

        return back()->with('success', 'Η αίτηση απορρίφθηκε.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:120',
            'last_name' => 'required|string|max:120',
            'email' => 'nullable|email|max:190',
            'phone' => 'nullable|string|max:40',
            'status' => 'required|in:active,inactive,trial',
            'monthly_fee' => 'nullable|numeric|min:0',
            'sport' => 'nullable|string|max:120',
        ]);

        $athlete = Athlete::query()->withoutGlobalScopes()->create([
            ...$data,
            'club_id' => $this->clubId($request),
            'id' => (string) Str::uuid(),
            'gdpr_consent' => 'pending',
        ]);

        $this->logChange($request, $athlete, 'created', null, $athlete->first_name.' '.$athlete->last_name);

        $name = trim($athlete->last_name.' '.$athlete->first_name);
        app(ClubActivityLogger::class)->record(
            $athlete->club_id,
            'athlete.create',
            'Δημιούργησε αθλητή',
            $name.($athlete->sport ? ' · '.$athlete->sport : '').' · κατάσταση '.$athlete->status,
            'athlete',
            $athlete->id,
        );

        return redirect()
            ->route('admin.athletes.show', $athlete->id)
            ->with('success', 'Ο αθλητής δημιουργήθηκε.');
    }

    public function show(Request $request, string $id, AmkaService $amka): Response
    {
        $athlete = $this->clubQuery(Athlete::class, $request)
            ->with(['classes:id,name'])
            ->findOrFail($id);

        $canAmka = $this->canAccessAmka($request);

        $transactions = AthleteTransaction::query()->withoutGlobalScopes()
            ->where('club_id', $athlete->club_id)
            ->where('athlete_id', $athlete->id)
            ->latest()
            ->limit(40)
            ->get(['id', 'type', 'amount', 'month', 'year', 'payment_method', 'comments', 'created_at']);

        $attendances = Attendance::query()->withoutGlobalScopes()
            ->where('club_id', $athlete->club_id)
            ->where('athlete_id', $athlete->id)
            ->latest('attended_on')
            ->limit(40)
            ->get(['id', 'status', 'attended_on']);

        $attendanceTotals = [
            'present' => Attendance::query()->withoutGlobalScopes()
                ->where('athlete_id', $athlete->id)->where('status', 'present')->count(),
            'absent' => Attendance::query()->withoutGlobalScopes()
                ->where('athlete_id', $athlete->id)->where('status', 'absent')->count(),
            'total' => Attendance::query()->withoutGlobalScopes()
                ->where('athlete_id', $athlete->id)->count(),
        ];

        $progress = ProgressReport::query()->withoutGlobalScopes()
            ->where('athlete_id', $athlete->id)
            ->latest('report_date')
            ->limit(20)
            ->get(['id', 'report_date', 'body', 'meta']);

        $history = AthleteChangeLog::query()->withoutGlobalScopes()
            ->where('athlete_id', $athlete->id)
            ->latest()
            ->limit(80)
            ->get(['id', 'field', 'old_value', 'new_value', 'created_at']);

        $announcements = Announcement::query()
            ->withoutGlobalScope(ClubScope::class)
            ->where('club_id', $athlete->club_id)
            ->latest('published_at')
            ->limit(20)
            ->get(['id', 'title', 'body', 'published_at']);

        $classes = $this->clubQuery(AcademyClass::class, $request)
            ->orderBy('name')
            ->get(['id', 'name']);

        $sports = $this->clubQuery(Sport::class, $request)
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->values();

        $associations = $this->clubQuery(Association::class, $request)
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->values();

        $sizeChart = $this->clubQuery(SizeChart::class, $request)->first();
        $chart = is_array($sizeChart?->chart) ? $sizeChart->chart : [];
        $sizes = collect([
            ...($chart['kids'] ?? []),
            ...($chart['men'] ?? []),
            ...($chart['women'] ?? []),
            ...(is_array($chart) && array_is_list($chart) ? $chart : []),
        ])->map(fn ($s) => trim((string) $s))->filter()->unique()->values();

        $clothingPackages = $this->clubQuery(ClothingPackage::class, $request)
            ->orderBy('name')
            ->get(['id', 'name']);

        $neighbors = $this->clubQuery(Athlete::class, $request)
            ->orderBy('last_name')->orderBy('first_name')
            ->pluck('id');
        $idx = $neighbors->search($athlete->id);
        $prevId = $idx !== false && $idx > 0 ? $neighbors[$idx - 1] : null;
        $nextId = $idx !== false && $idx < $neighbors->count() - 1 ? $neighbors[$idx + 1] : null;

        return Inertia::render('Admin/Athletes/Show', [
            'athlete' => $athlete->toProfileArray($canAmka),
            'canAmka' => $canAmka,
            'classes' => $classes,
            'sportOptions' => $sports,
            'associationOptions' => $associations,
            'sizeOptions' => $sizes,
            'clothingPackages' => $clothingPackages,
            'transactions' => $transactions,
            'attendances' => $attendances,
            'attendanceTotals' => $attendanceTotals,
            'progressReports' => $progress,
            'changeLogs' => $history,
            'announcements' => $announcements,
            'prevId' => $prevId,
            'nextId' => $nextId,
            'balance' => [
                'charges' => (float) AthleteTransaction::query()->withoutGlobalScopes()
                    ->where('athlete_id', $athlete->id)->where('type', 'charge')->sum('amount'),
                'payments' => (float) AthleteTransaction::query()->withoutGlobalScopes()
                    ->where('athlete_id', $athlete->id)->where('type', 'payment')->sum('amount'),
            ],
            'lastPayment' => ($lastPayment = AthleteTransaction::query()->withoutGlobalScopes()
                ->where('athlete_id', $athlete->id)
                ->where('type', 'payment')
                ->latest()
                ->first(['amount', 'created_at', 'payment_method']))
                ? [
                    'amount' => (float) $lastPayment->amount,
                    'created_at' => optional($lastPayment->created_at)->toIso8601String(),
                    'payment_method' => $lastPayment->payment_method,
                ]
                : null,
            'nextTraining' => ($nextTraining = Training::query()->withoutGlobalScopes()
                ->where('club_id', $athlete->club_id)
                ->where('starts_at', '>=', now())
                ->when(
                    $athlete->classes->isNotEmpty(),
                    fn ($q) => $q->whereIn('academy_class_id', $athlete->classes->pluck('id')),
                )
                ->orderBy('starts_at')
                ->first(['id', 'title', 'starts_at', 'academy_class_id']))
                ? [
                    'id' => $nextTraining->id,
                    'title' => $nextTraining->title,
                    'starts_at' => optional($nextTraining->starts_at)->toIso8601String(),
                    'academy_class_id' => $nextTraining->academy_class_id,
                ]
                : null,
            'joinUrl' => ($slug = Club::query()->whereKey($athlete->club_id)->value('slug'))
                ? url('/join/'.$slug)
                : null,
        ]);
    }

    public function update(Request $request, string $id, AmkaService $amka): RedirectResponse
    {
        $athlete = $this->clubQuery(Athlete::class, $request)->findOrFail($id);

        $data = $request->validate([
            'first_name' => 'required|string|max:120',
            'last_name' => 'required|string|max:120',
            'email' => 'nullable|email|max:190',
            'phone' => 'nullable|string|max:40',
            'birth_date' => 'nullable|date',
            'status' => 'required|in:active,inactive,trial',
            'monthly_fee' => 'nullable|numeric|min:0',
            'gender' => 'nullable|string|max:16',
            'guardian_name' => 'nullable|string|max:160',
            'guardian_phone' => 'nullable|string|max:40',
            'sport' => 'nullable|string|max:120',
            'health_card' => 'boolean',
            'health_card_expires' => 'nullable|date',
            'gdpr_consent' => 'nullable|in:full,pending,locked',
            'amka' => 'nullable|string|max:20',
            'clear_amka' => 'boolean',
            'class_ids' => 'nullable|array',
            'class_ids.*' => 'uuid',
            'primary_class_id' => 'nullable|uuid',
            // meta / extended
            'adt' => 'nullable|string|max:40',
            'father_first_name' => 'nullable|string|max:120',
            'mother_first_name' => 'nullable|string|max:120',
            'father_email' => 'nullable|email|max:190',
            'mother_email' => 'nullable|email|max:190',
            'preferred_parent_email' => ['nullable', 'string', 'max:80', 'regex:/^(father|mother|both|athlete)([,\s]+(father|mother|athlete))*$/'],
            'mother_phone' => 'nullable|string|max:40',
            'address' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:120',
            'county' => 'nullable|string|max:120',
            'registration_number' => 'nullable|string|max:80',
            'club_name' => 'nullable|string|max:160',
            'sports' => 'nullable|array',
            'uniform_received' => 'boolean',
            'uniform_size' => 'nullable|string|max:40',
            'clothing_package_ids' => 'nullable|array',
            'clothing_package_ids.*' => 'uuid',
            'blood_type' => 'nullable|string|max:8',
            'allergies' => 'nullable|string|max:2000',
            'chronic_conditions' => 'nullable|string|max:2000',
            'medication' => 'nullable|string|max:2000',
            'doctor_name' => 'nullable|string|max:160',
            'doctor_phone' => 'nullable|string|max:40',
            'emergency_name' => 'nullable|string|max:160',
            'emergency_phone' => 'nullable|string|max:40',
            'emergency_relation' => 'nullable|string|max:80',
            'registration_expires' => 'nullable|date',
            'auto_renewal' => 'boolean',
            'consent_expires' => 'nullable|date',
            'comments' => 'nullable|string|max:5000',
            'photo_url' => 'nullable|string|max:500',
            'gdpr_items' => 'nullable|array',
            'registration_charge' => 'boolean',
            'monthly_charge' => 'boolean',
            'custom_charge' => 'boolean',
            'season_ticket' => 'boolean',
            'subscription_discount' => 'boolean',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_reason' => 'nullable|string|max:255',
            'jersey_number' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:80',
            'athletic_level' => 'nullable|string|max:80',
            'place_of_birth' => 'nullable|string|max:120',
            'nationality' => 'nullable|string|max:80',
        ]);

        $data = GreekGivenName::migrateGuardianNameIntoParentFields($data);
        // Το πεδίο «Όνομα κηδεμόνα» αφαιρέθηκε από τη φόρμα· καθαρίζουμε τυχόν υπόλοιπο.
        if (($data['guardian_name'] ?? null) === '' || ($data['guardian_name'] ?? null) === null) {
            $data['guardian_name'] = null;
        }

        $before = $athlete->only(['first_name', 'last_name', 'status', 'email', 'phone', 'sport']);

        DB::transaction(function () use ($request, $athlete, $data, $amka) {
            $athlete->fill([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'birth_date' => $data['birth_date'] ?? null,
                'status' => $data['status'],
                'monthly_fee' => $data['monthly_fee'] ?? 0,
                'gender' => $data['gender'] ?? null,
                'guardian_name' => $data['guardian_name'] ?? null,
                'guardian_phone' => $data['guardian_phone'] ?? null,
                'sport' => $data['sport'] ?? null,
                'health_card' => filter_var($data['health_card'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'health_card_expires' => $data['health_card_expires'] ?? null,
                'gdpr_consent' => $data['gdpr_consent'] ?? $athlete->gdpr_consent,
            ]);

            $meta = [];
            foreach (Athlete::META_KEYS as $key) {
                if (array_key_exists($key, $data)) {
                    $meta[$key] = $data[$key];
                }
            }
            if (isset($data['class_ids'])) {
                $meta['class_ids'] = $data['class_ids'];
            }
            if (array_key_exists('primary_class_id', $data)) {
                $meta['primary_class_id'] = $data['primary_class_id'];
            }
            $athlete->mergeMeta($meta);
            $athlete->save();

            if (! empty($data['clear_amka']) && $this->canAccessAmka($request)) {
                $amka->storeOnAthlete($athlete, null, $request->user());
            } elseif ($this->canAccessAmka($request) && array_key_exists('amka', $data) && $data['amka'] !== null && $data['amka'] !== '' && $data['amka'] !== '********') {
                $amka->storeOnAthlete($athlete, $data['amka'], $request->user());
            }

            if (isset($data['class_ids']) && is_array($data['class_ids'])) {
                $sync = [];
                foreach ($data['class_ids'] as $cid) {
                    $sync[$cid] = [
                        'is_primary' => ($data['primary_class_id'] ?? null) === $cid,
                    ];
                }
                $athlete->classes()->sync($sync);
            }
        });

        $changed = [];
        foreach ($before as $field => $old) {
            $new = $athlete->{$field};
            if ((string) $old !== (string) $new) {
                $this->logChange($request, $athlete, $field, (string) $old, (string) $new);
                $changed[] = $field.': «'.((string) $old).'» → «'.((string) $new).'»';
            }
        }

        if ($changed !== []) {
            $name = trim($athlete->last_name.' '.$athlete->first_name);
            app(ClubActivityLogger::class)->record(
                $athlete->club_id,
                'athlete.update',
                'Ενημέρωσε στοιχείο αθλητή',
                $name.' · '.implode(' · ', array_slice($changed, 0, 8))
                    .(count($changed) > 8 ? ' · +'.(count($changed) - 8).' ακόμη' : ''),
                'athlete',
                $athlete->id,
                ['fields' => array_keys($before)],
            );
        }

        return redirect()
            ->route('admin.athletes.show', $athlete->id)
            ->with('success', 'Το προφίλ αποθηκεύτηκε.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $athlete = $this->clubQuery(Athlete::class, $request)->findOrFail($id);
        $name = trim($athlete->last_name.' '.$athlete->first_name);
        $clubId = $athlete->club_id;
        $athleteId = $athlete->id;
        $athlete->delete();

        app(ClubActivityLogger::class)->record(
            $clubId,
            'athlete.delete',
            'Διέγραψε αθλητή',
            $name !== '' ? $name : $athleteId,
            'athlete',
            $athleteId,
        );

        return redirect()
            ->route('admin.athletes.index')
            ->with('success', 'Ο αθλητής διαγράφηκε.');
    }

    public function storeProgress(Request $request, string $id): RedirectResponse
    {
        $athlete = $this->clubQuery(Athlete::class, $request)->findOrFail($id);
        $data = $request->validate([
            'report_date' => 'nullable|date',
            'body' => 'required|string|max:5000',
            'rating' => 'nullable|integer|min:1|max:5',
            'title' => 'nullable|string|max:200',
        ]);

        ProgressReport::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $athlete->club_id,
            'athlete_id' => $athlete->id,
            'report_date' => $data['report_date'] ?? now()->toDateString(),
            'body' => $data['body'],
            'meta' => [
                'rating' => $data['rating'] ?? null,
                'title' => $data['title'] ?? null,
            ],
        ]);

        return back()->with('success', 'Η αναφορά προόδου προστέθηκε.');
    }

    public function export(Request $request, AthleteCsvService $csv): StreamedResponse
    {
        $status = $request->query('status');
        $query = $this->clubQuery(Athlete::class, $request)->orderBy('last_name')->orderBy('first_name');
        if (in_array($status, ['active', 'inactive', 'trial'], true)) {
            $query->where('status', $status);
        }

        $content = $csv->export($query->get());

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, 'athletes-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function import(Request $request, AthleteCsvService $csv): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,text/csv|max:5120',
        ]);

        $result = $csv->import($this->clubId($request), $request->file('file'));

        $msg = "Εισαγωγή: {$result['created']} νέοι, {$result['updated']} ενημερώσεις";
        if ($result['skipped'] > 0) {
            $msg .= ", {$result['skipped']} παραλείψεις";
        }

        return redirect()
            ->route('admin.athletes.index')
            ->with('success', $msg)
            ->with('import_errors', array_slice($result['errors'], 0, 20));
    }

    private function resolveNotifyEmail(Athlete $athlete): ?string
    {
        return PreferredNotifyEmail::resolvePrimary($athlete);
    }

    private function canAccessAmka(Request $request): bool
    {
        // AMKA is visible/editable to every authenticated club user in the app.
        return $request->user() !== null;
    }

    private function logChange(Request $request, Athlete $athlete, string $field, ?string $old, ?string $new): void
    {
        AthleteChangeLog::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $athlete->club_id,
            'athlete_id' => $athlete->id,
            'user_id' => $request->user()?->id,
            'field' => $field,
            'old_value' => $old,
            'new_value' => $new,
        ]);
    }
}
