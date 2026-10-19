<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Athlete;
use App\Models\AthleteTransaction;
use App\Models\Attendance;
use App\Models\Facility;
use App\Models\ParentAthleteLink;
use App\Models\SportMatch;
use App\Models\Training;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ParentPortalController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $club = $request->attributes->get('currentClub') ?? $user?->activeClub;
        $role = $user?->is_platform_admin
            ? Roles::PLATFORM_ADMIN
            : $user?->roleInClub($club);

        if ($role !== Roles::PARENT) {
            return Inertia::render('Portals/WrongApp', [
                'audience' => 'parent',
                'roleLabel' => Roles::label($role) ?: 'χρήστης',
            ]);
        }

        $clubId = $user->active_club_id;
        $today = now()->toDateString();
        $tab = $this->resolveTab($request->query('tab'));

        $links = ParentAthleteLink::query()
            ->withoutGlobalScopes()
            ->where('parent_user_id', $user->id)
            ->when($clubId, fn ($q) => $q->where('club_id', $clubId))
            ->get(['athlete_id']);

        $athleteIds = $links->pluck('athlete_id')->filter()->unique()->values();

        $athletes = $athleteIds->isEmpty()
            ? collect()
            : Athlete::query()->withoutGlobalScopes()
                ->with(['classes:id,name'])
                ->whereIn('id', $athleteIds)
                ->where('status', '!=', 'inactive')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();

        $classIds = $athletes
            ->flatMap(fn (Athlete $a) => $a->classes->pluck('id'))
            ->unique()
            ->values();

        $facilityNames = $clubId
            ? Facility::query()->withoutGlobalScopes()
                ->where('club_id', $clubId)
                ->pluck('name', 'id')
            : collect();

        $trainings = $this->upcomingTrainings($clubId, $classIds, $athletes, $facilityNames);
        $matches = $this->upcomingMatches($clubId, $classIds, $athletes);
        $balances = $this->athleteBalances($athletes);
        $totalBalance = $balances->sum(fn (array $row) => max(0, (float) $row['balance']));
        $paymentHistory = $this->paymentHistory($athleteIds);
        $recentAttendance = $this->recentAttendance($athleteIds, $athletes);

        $announcements = $clubId
            ? Announcement::query()->withoutGlobalScopes()
                ->where('club_id', $clubId)
                ->latest('published_at')
                ->limit(20)
                ->get(['id', 'title', 'body', 'published_at', 'meta'])
            : collect();

        return Inertia::render('Portals/ParentHome', [
            'athletes' => $athletes->map(fn (Athlete $a) => [
                'id' => $a->id,
                'first_name' => $a->first_name,
                'last_name' => $a->last_name,
                'class_names' => $a->classes->pluck('name')->filter()->join(', ') ?: 'Χωρίς τμήμα',
                'class_ids' => $a->classes->pluck('id')->values()->all(),
                'health_card' => (bool) $a->health_card,
                'health_card_expires' => optional($a->health_card_expires)->format('Y-m-d'),
                'health_card_status' => $a->metaGet('health_card_status'),
                'gdpr_consent' => $a->gdpr_consent,
                'gdpr_items' => $a->metaGet('gdpr_items', []),
            ])->values(),
            'announcements' => $announcements->map(fn (Announcement $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'body' => $a->body,
                'published_at' => optional($a->published_at)?->toIso8601String(),
                'image_url' => $a->imageUrl(),
            ])->values(),
            'summary' => [
                'athlete_count' => $athletes->count(),
                'total_balance' => round($totalBalance, 2),
                'next_training' => $trainings->first(),
                'next_match' => $matches->first(),
            ],
            'balances' => $balances->values(),
            'trainings' => $trainings->values(),
            'matches' => $matches->values(),
            'recent_attendance' => $recentAttendance->values(),
            'payment_history' => $paymentHistory->values(),
            'today' => $today,
            'tab' => $tab,
        ]);
    }

    public function submitAbsence(Request $request): RedirectResponse
    {
        $user = $request->user();
        $club = $request->attributes->get('currentClub') ?? $user?->activeClub;
        $role = $user?->is_platform_admin
            ? Roles::PLATFORM_ADMIN
            : $user?->roleInClub($club);

        if ($role !== Roles::PARENT) {
            abort(403);
        }

        $data = $request->validate([
            'athlete_id' => ['required', 'uuid'],
            'date' => ['required', 'date'],
            'reason' => ['required', 'in:sick,leave'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $clubId = $user->active_club_id;

        $linked = ParentAthleteLink::query()
            ->withoutGlobalScopes()
            ->where('parent_user_id', $user->id)
            ->when($clubId, fn ($q) => $q->where('club_id', $clubId))
            ->where('athlete_id', $data['athlete_id'])
            ->exists();

        if (! $linked) {
            return back()->with('error', 'Ο αθλητής δεν είναι συνδεδεμένος με τον λογαριασμό σας.');
        }

        $athlete = Athlete::query()->withoutGlobalScopes()
            ->with(['classes:id'])
            ->whereKey($data['athlete_id'])
            ->when($clubId, fn ($q) => $q->where('club_id', $clubId))
            ->firstOrFail();

        $classIds = $athlete->classes->pluck('id')->filter()->unique()->values();

        if ($classIds->isEmpty()) {
            return back()->with('error', 'Δεν βρέθηκε τμήμα για καταχώρηση απουσίας.');
        }

        $trainingClassIds = Training::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->whereDate('starts_at', $data['date'])
            ->whereIn('academy_class_id', $classIds)
            ->pluck('academy_class_id')
            ->unique()
            ->values();

        $targetClassIds = $trainingClassIds->isNotEmpty() ? $trainingClassIds : $classIds;

        foreach ($targetClassIds as $classId) {
            $this->upsertAbsenceMark(
                (string) $clubId,
                (string) $classId,
                $athlete->id,
                $data['date'],
                $data['reason'],
                $data['notes'] ?? null,
            );
        }

        return back()->with(
            'success',
            'Η αιτιολόγηση απουσίας καταχωρήθηκε και είναι ορατή στον προπονητή.',
        );
    }

    private function resolveTab(mixed $raw): string
    {
        $allowed = ['overview', 'schedule', 'payments', 'documents'];

        return is_string($raw) && in_array($raw, $allowed, true) ? $raw : 'overview';
    }

    private function upsertAbsenceMark(
        string $clubId,
        string $classId,
        string $athleteId,
        string $date,
        string $reason,
        ?string $notes,
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

        $meta = [
            'class_id' => $classId,
            'absence_reason' => $reason,
            'absence_notes' => $notes,
            'source' => 'parent_portal',
        ];

        if ($existing) {
            $existing->update([
                'status' => 'excused',
                'meta' => array_merge($existing->meta ?? [], $meta),
            ]);

            return;
        }

        Attendance::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $clubId,
            'athlete_id' => $athleteId,
            'training_id' => null,
            'status' => 'excused',
            'attended_on' => $date,
            'meta' => $meta,
        ]);
    }

    /**
     * @param  Collection<int, string>  $classIds
     * @param  Collection<int, Athlete>  $athletes
     * @return Collection<int, array<string, mixed>>
     */
    private function upcomingTrainings(
        ?string $clubId,
        Collection $classIds,
        Collection $athletes,
        Collection $facilityNames,
    ): Collection {
        if (! $clubId || ($classIds->isEmpty() && $athletes->isEmpty())) {
            return collect();
        }

        $classNameById = $athletes
            ->flatMap(fn (Athlete $a) => $a->classes->mapWithKeys(fn ($c) => [$c->id => $c->name]))
            ->all();

        return Training::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->where('starts_at', '>=', now()->startOfDay())
            ->when(
                $classIds->isNotEmpty(),
                fn ($q) => $q->whereIn('academy_class_id', $classIds),
            )
            ->orderBy('starts_at')
            ->limit(40)
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
     * @param  Collection<int, string>  $classIds
     * @param  Collection<int, Athlete>  $athletes
     * @return Collection<int, array<string, mixed>>
     */
    private function upcomingMatches(?string $clubId, Collection $classIds, Collection $athletes): Collection
    {
        if (! $clubId || $classIds->isEmpty()) {
            return collect();
        }

        return SportMatch::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->where('starts_at', '>=', now()->startOfDay())
            ->whereIn('academy_class_id', $classIds)
            ->where('status', '!=', 'cancelled')
            ->orderBy('starts_at')
            ->limit(20)
            ->get(['id', 'starts_at', 'opponent', 'venue', 'home_away', 'meta'])
            ->map(fn (SportMatch $m) => [
                'id' => $m->id,
                'starts_at' => $m->starts_at?->toIso8601String(),
                'opponent' => $m->opponent,
                'venue' => $m->venue,
                'home_away' => $m->home_away,
            ]);
    }

    /**
     * @param  Collection<int, Athlete>  $athletes
     * @return Collection<int, array<string, mixed>>
     */
    private function athleteBalances(Collection $athletes): Collection
    {
        if ($athletes->isEmpty()) {
            return collect();
        }

        $ids = $athletes->pluck('id');
        $txns = AthleteTransaction::query()->withoutGlobalScopes()
            ->whereIn('athlete_id', $ids)
            ->get(['athlete_id', 'type', 'amount']);

        return $athletes->map(function (Athlete $athlete) use ($txns) {
            $balance = $txns
                ->where('athlete_id', $athlete->id)
                ->reduce(
                    fn (float $sum, AthleteTransaction $row) => $sum + ($row->type === 'charge'
                        ? (float) $row->amount
                        : -(float) $row->amount),
                    0.0,
                );

            return [
                'athlete_id' => $athlete->id,
                'name' => trim("{$athlete->last_name} {$athlete->first_name}"),
                'balance' => round($balance, 2),
            ];
        });
    }

    /**
     * @param  Collection<int, string>  $athleteIds
     * @return Collection<int, array<string, mixed>>
     */
    private function paymentHistory(Collection $athleteIds): Collection
    {
        if ($athleteIds->isEmpty()) {
            return collect();
        }

        return AthleteTransaction::query()->withoutGlobalScopes()
            ->whereIn('athlete_id', $athleteIds)
            ->where('type', 'payment')
            ->orderByDesc('created_at')
            ->limit(24)
            ->get(['id', 'athlete_id', 'amount', 'comments', 'payment_method', 'created_at'])
            ->map(fn (AthleteTransaction $row) => [
                'id' => $row->id,
                'athlete_id' => $row->athlete_id,
                'amount' => (float) $row->amount,
                'comments' => $row->comments ?: $row->payment_method,
                'created_at' => $row->created_at?->toIso8601String(),
            ]);
    }

    /**
     * @param  Collection<int, string>  $athleteIds
     * @param  Collection<int, Athlete>  $athletes
     * @return Collection<int, array<string, mixed>>
     */
    private function recentAttendance(Collection $athleteIds, Collection $athletes): Collection
    {
        if ($athleteIds->isEmpty()) {
            return collect();
        }

        $names = $athletes->mapWithKeys(
            fn (Athlete $a) => [$a->id => trim("{$a->last_name} {$a->first_name}")],
        );

        return Attendance::query()->withoutGlobalScopes()
            ->whereIn('athlete_id', $athleteIds)
            ->orderByDesc('attended_on')
            ->limit(12)
            ->get(['id', 'athlete_id', 'attended_on', 'status', 'meta'])
            ->map(fn (Attendance $row) => [
                'id' => $row->id,
                'athlete_id' => $row->athlete_id,
                'athlete_name' => $names[$row->athlete_id] ?? '—',
                'date' => optional($row->attended_on)?->format('Y-m-d'),
                'status' => $row->status,
                'absence_reason' => data_get($row->meta, 'absence_reason'),
            ]);
    }
}
