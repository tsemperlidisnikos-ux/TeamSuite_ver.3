<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubActivityLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ClubActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->is_platform_admin, 403);

        $clubId = trim((string) $request->query('club_id', ''));
        $userId = trim((string) $request->query('user_id', ''));
        $action = trim((string) $request->query('action', ''));
        $q = trim((string) $request->query('q', ''));
        $date = trim((string) $request->query('date', ''));

        $clubs = Club::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Club $c) => ['id' => $c->id, 'name' => $c->name])
            ->values();

        if ($clubId === '' && $clubs->isNotEmpty()) {
            $active = $request->user()?->active_club_id;
            $clubId = $active && $clubs->contains('id', $active)
                ? (string) $active
                : (string) $clubs->first()['id'];
        }

        $users = $clubId !== ''
            ? User::query()
                ->whereHas('clubs', fn ($q) => $q->where('clubs.id', $clubId))
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $u) => [
                    'id' => (string) $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                ])
                ->values()
            : collect();

        $query = ClubActivityLog::query()
            ->with('user:id,name,email')
            ->when($clubId !== '', fn ($q) => $q->where('club_id', $clubId))
            ->when($userId !== '', fn ($q) => $q->where('user_id', $userId))
            ->when($action !== '', fn ($q) => $q->where('action', $action))
            ->when($date !== '', function ($q) use ($date) {
                $q->whereDate('occurred_at', $date);
            })
            ->when($q !== '', function ($builder) use ($q) {
                $like = '%'.$q.'%';
                $op = config('database.default') === 'pgsql' ? 'ilike' : 'like';
                $builder->where(function ($w) use ($like, $op) {
                    $w->where('summary', $op, $like)
                        ->orWhere('description', $op, $like);
                });
            })
            ->orderByDesc('occurred_at')
            ->limit(300);

        $rows = $query->get()->map(fn (ClubActivityLog $row) => [
            'id' => $row->id,
            'occurred_at' => optional($row->occurred_at)->toIso8601String(),
            'date' => optional($row->occurred_at)?->timezone(config('app.timezone'))->format('Y-m-d'),
            'time' => optional($row->occurred_at)?->timezone(config('app.timezone'))->format('H:i:s'),
            'user_name' => $row->user?->name ?? 'Σύστημα',
            'user_email' => $row->user?->email,
            'action' => $row->action,
            'summary' => $row->summary,
            'description' => $row->description,
            'ip_address' => $row->ip_address,
            'impersonator_name' => is_array($row->meta) ? ($row->meta['impersonator_name'] ?? null) : null,
        ]);

        $groups = $this->groupByDate($rows);

        $actions = ClubActivityLog::query()
            ->when($clubId !== '', fn ($q) => $q->where('club_id', $clubId))
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->values();

        return Inertia::render('Platform/ClubActivityLog', [
            'groups' => $groups,
            'clubs' => $clubs,
            'users' => $users,
            'actions' => $actions,
            'filters' => [
                'club_id' => $clubId,
                'user_id' => $userId,
                'action' => $action,
                'date' => $date,
                'q' => $q,
            ],
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array{date: string, label: string, items: list<array<string, mixed>>}>
     */
    private function groupByDate(Collection $rows): array
    {
        return $rows
            ->groupBy(fn (array $row) => $row['date'] ?? 'unknown')
            ->map(function (Collection $items, string $date) {
                $label = $date;
                try {
                    $label = Carbon::parse($date)
                        ->locale('el')
                        ->translatedFormat('l d/m/Y');
                } catch (\Throwable) {
                    // keep raw date
                }

                return [
                    'date' => $date,
                    'label' => $label,
                    'items' => $items->values()->all(),
                ];
            })
            ->values()
            ->all();
    }
}
