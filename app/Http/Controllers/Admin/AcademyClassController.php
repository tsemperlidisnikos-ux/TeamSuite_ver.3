<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesClubResources;
use App\Http\Controllers\Admin\Concerns\InteractsWithClubResource;
use App\Http\Controllers\Controller;
use App\Models\AcademyClass;
use App\Models\Athlete;
use App\Models\AthleteTransaction;
use App\Models\Attendance;
use App\Models\Coach;
use App\Models\ScheduleSlot;
use App\Models\Training;
use App\Services\Trainings\MissingAttendanceTrainings;
use App\Support\AdminClubResources;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AcademyClassController extends Controller
{
    use HandlesClubResources;
    use InteractsWithClubResource;

    public function __construct(
        private readonly MissingAttendanceTrainings $missingAttendance,
    ) {}

    protected function config(): array
    {
        return AdminClubResources::get('classes');
    }

    public function index(Request $request): Response
    {
        $config = $this->config();
        $q = trim((string) $request->query('q', ''));
        $sport = trim((string) $request->query('sport', ''));
        $tab = $request->query('tab', 'active') === 'inactive' ? 'inactive' : 'active';

        $items = $this->clubQuery(AcademyClass::class, $request)
            ->withCount('athletes')
            ->with(['coach:id,first_name,last_name', 'season:id,name'])
            ->when($q !== '', function ($builder) use ($q) {
                $like = '%'.$q.'%';
                $op = config('database.default') === 'pgsql' ? 'ilike' : 'like';
                $builder->where(function ($w) use ($like, $op) {
                    $w->where('name', $op, $like)
                        ->orWhere('sport', $op, $like)
                        ->orWhere('age_group', $op, $like);
                });
            })
            ->when($sport !== '', fn ($b) => $b->where('sport', 'like', '%'.$sport.'%'))
            ->when($tab === 'inactive', function ($builder) {
                $builder->where(function ($w) {
                    $w->where('meta->manual_inactive', true)
                        ->orWhere('meta->manualInactive', true);
                });
            })
            ->when($tab === 'active', function ($builder) {
                $builder->where(function ($w) {
                    $w->whereNull('meta->manual_inactive')
                        ->orWhere('meta->manual_inactive', false)
                        ->orWhere('meta->manual_inactive', '0');
                })->where(function ($w) {
                    $w->whereNull('meta->manualInactive')
                        ->orWhere('meta->manualInactive', false)
                        ->orWhere('meta->manualInactive', '0');
                });
            })
            ->orderBy('name')
            ->paginate(10_000)
            ->withQueryString()
            ->through(fn (AcademyClass $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'sport' => $c->sport,
                'age_group' => $c->age_group,
                'gender' => $c->gender,
                'monthly_fee' => $c->monthly_fee,
                'athletes_count' => $c->athletes_count,
                'season_name' => $c->season?->name,
                'coach_name' => $c->coach
                    ? trim($c->coach->last_name.' '.$c->coach->first_name)
                    : null,
                'manual_inactive' => (bool) (($c->meta['manual_inactive'] ?? $c->meta['manualInactive'] ?? false)),
            ]);

        $missing = $this->missingAttendance->forClub($this->clubId($request));
        $firstMissing = $missing[0] ?? null;

        return Inertia::render('Admin/Classes/Index', [
            'title' => $config['title'],
            'items' => $items,
            'fields' => $config['fields'],
            'routes' => [
                'index' => 'admin.classes.index',
                'store' => 'admin.classes.store',
                'update' => 'admin.classes.update',
                'destroy' => 'admin.classes.destroy',
                'show' => 'admin.classes.show',
            ],
            'filters' => ['q' => $q, 'sport' => $sport, 'tab' => $tab],
            'coaches' => $this->clubQuery(Coach::class, $request)
                ->where('is_active', true)
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name']),
            'missingAttendanceCount' => count($missing),
            'missingAttendanceHref' => $firstMissing
                ? route('admin.attendance.index', [
                    'class_id' => $firstMissing['class_id'],
                    'date' => $firstMissing['date'],
                ])
                : route('admin.trainings.index'),
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $class = $this->clubQuery(AcademyClass::class, $request)
            ->with(['coach:id,first_name,last_name'])
            ->findOrFail($id);

        $athletes = $class->athletes()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get([
                'athletes.id',
                'first_name',
                'last_name',
                'status',
                'email',
                'phone',
                'monthly_fee',
                'birth_date',
                'registration_number',
                'health_card',
            ]);

        $available = $this->clubQuery(Athlete::class, $request)
            ->where('status', '!=', 'inactive')
            ->whereDoesntHave('classes', fn ($q) => $q->where('academy_classes.id', $class->id))
            ->orderBy('last_name')
            ->limit(200)
            ->get(['id', 'first_name', 'last_name', 'status']);

        $athleteIds = $athletes->pluck('id');

        $present = Attendance::query()->withoutGlobalScopes()
            ->where('club_id', $class->club_id)
            ->whereIn('athlete_id', $athleteIds)
            ->where('status', 'present')
            ->count();
        $totalAtt = Attendance::query()->withoutGlobalScopes()
            ->where('club_id', $class->club_id)
            ->whereIn('athlete_id', $athleteIds)
            ->count();

        $charges = (float) AthleteTransaction::query()->withoutGlobalScopes()
            ->where('club_id', $class->club_id)
            ->whereIn('athlete_id', $athleteIds)
            ->where('type', 'charge')
            ->sum('amount');
        $payments = (float) AthleteTransaction::query()->withoutGlobalScopes()
            ->where('club_id', $class->club_id)
            ->whereIn('athlete_id', $athleteIds)
            ->where('type', 'payment')
            ->sum('amount');

        $weekday = (int) now()->dayOfWeek; // 0=Sun
        $scheduleSlots = ScheduleSlot::query()->withoutGlobalScopes()
            ->where('club_id', $class->club_id)
            ->where('academy_class_id', $class->id)
            ->orderBy('weekday')
            ->orderBy('starts_at')
            ->get(['id', 'weekday', 'starts_at', 'ends_at', 'title', 'meta'])
            ->map(fn (ScheduleSlot $s) => [
                'id' => $s->id,
                'weekday' => $s->weekday,
                'starts_at' => $s->starts_at,
                'ends_at' => $s->ends_at,
                'title' => $s->title,
                'location' => is_array($s->meta) ? ($s->meta['location'] ?? null) : null,
            ]);

        $todayTrainings = Training::query()->withoutGlobalScopes()
            ->where('club_id', $class->club_id)
            ->where('academy_class_id', $class->id)
            ->whereDate('starts_at', now()->toDateString())
            ->orderBy('starts_at')
            ->get(['id', 'title', 'starts_at', 'ends_at', 'notes', 'status'])
            ->map(fn (Training $t) => [
                'id' => $t->id,
                'title' => $t->title ?: 'Προπόνηση',
                'starts_at' => optional($t->starts_at)->format('H:i'),
                'ends_at' => optional($t->ends_at)->format('H:i'),
                'notes' => $t->notes,
                'status' => $t->status,
                'type' => 'Προπόνηση',
            ]);

        $seats = $class->max_students
            ? $athletes->count().' / '.$class->max_students
            : (string) $athletes->count();

        return Inertia::render('Admin/Classes/Show', [
            'classItem' => [
                'id' => $class->id,
                'name' => $class->name,
                'sport' => $class->sport,
                'age_group' => $class->age_group,
                'gender' => $class->gender,
                'monthly_fee' => $class->monthly_fee,
                'max_students' => $class->max_students,
                'schedule_summary' => $class->schedule_summary,
                'coach_id' => $class->coach_id,
                'coach_name' => $class->coach
                    ? trim($class->coach->last_name.' '.$class->coach->first_name)
                    : null,
            ],
            'athletes' => $athletes->map(function (Athlete $a) {
                $birth = $a->birth_date;
                $age = $birth
                    ? (int) floor((now()->getTimestamp() - $birth->getTimestamp()) / (365.25 * 24 * 60 * 60))
                    : null;

                return [
                    'id' => $a->id,
                    'first_name' => $a->first_name,
                    'last_name' => $a->last_name,
                    'status' => $a->status,
                    'email' => $a->email,
                    'phone' => $a->phone,
                    'monthly_fee' => $a->monthly_fee,
                    'registration_number' => $a->registration_number,
                    'birth_date' => optional($a->birth_date)->format('Y-m-d'),
                    'birth_year' => optional($a->birth_date)->format('Y'),
                    'age' => $age,
                    'health_card' => (bool) $a->health_card,
                    'is_primary' => (bool) $a->pivot->is_primary,
                ];
            }),
            'availableAthletes' => $available,
            'coaches' => $this->clubQuery(Coach::class, $request)
                ->where('is_active', true)
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name']),
            'classStats' => [
                'attendance_pct' => $totalAtt > 0 ? (int) round(($present / $totalAtt) * 100) : null,
                'owed' => round(max(0, $charges - $payments), 2),
                'seats' => $seats,
                'club_seats' => null,
                'roster_count' => $athletes->count(),
            ],
            'scheduleSlots' => $scheduleSlots,
            'todayEvents' => $todayTrainings,
            'todayWeekday' => $weekday,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $config = $this->config();
        $validated = $request->validate(array_merge($config['rules'], [
            'coach_id' => 'nullable|uuid',
            'gender' => 'nullable|string|max:40',
            'max_students' => 'nullable|integer|min:0',
            'schedule_summary' => 'nullable|string|max:500',
        ]));

        $class = $this->clubResourceStore($request, AcademyClass::class, $validated);

        return redirect()
            ->route('admin.classes.show', $class->id)
            ->with('success', 'Το τμήμα δημιουργήθηκε.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $config = $this->config();
        $validated = $request->validate(array_merge($config['rules'], [
            'coach_id' => 'nullable|uuid',
            'gender' => 'nullable|string|max:40',
            'max_students' => 'nullable|integer|min:0',
            'schedule_summary' => 'nullable|string|max:500',
        ]));

        $this->clubResourceUpdate($request, AcademyClass::class, $id, $validated);

        return redirect()
            ->route('admin.classes.show', $id)
            ->with('success', 'Το τμήμα ενημερώθηκε.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->clubResourceDestroy($request, AcademyClass::class, $id);

        return redirect()
            ->route('admin.classes.index')
            ->with('success', 'Το τμήμα διαγράφηκε.');
    }

    public function attachAthlete(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'athlete_id' => 'required|uuid',
            'is_primary' => 'boolean',
        ]);

        $class = $this->clubQuery(AcademyClass::class, $request)->findOrFail($id);
        $athlete = $this->clubQuery(Athlete::class, $request)->findOrFail($data['athlete_id']);

        $class->athletes()->syncWithoutDetaching([
            $athlete->id => ['is_primary' => filter_var($data['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN)],
        ]);

        return back()->with('success', 'Ο αθλητής προστέθηκε στο τμήμα.');
    }

    public function detachAthlete(Request $request, string $id, string $athleteId): RedirectResponse
    {
        $class = $this->clubQuery(AcademyClass::class, $request)->findOrFail($id);
        $class->athletes()->detach($athleteId);

        return back()->with('success', 'Ο αθλητής αφαιρέθηκε από το τμήμα.');
    }
}
