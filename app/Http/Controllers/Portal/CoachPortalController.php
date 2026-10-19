<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AcademyClass;
use App\Models\Announcement;
use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\Coach;
use App\Models\Facility;
use App\Models\Training;
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
            ? Announcement::query()->withoutGlobalScopes()
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

    private function resolveCoach($user, ?string $clubId): ?Coach
    {
        if (! $clubId) {
            return null;
        }

        return Coach::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhere('email', $user->email);
            })
            ->where('is_active', true)
            ->first();
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
