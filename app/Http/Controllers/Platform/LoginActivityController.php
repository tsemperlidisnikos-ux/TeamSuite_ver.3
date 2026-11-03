<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\LoginActivity;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class LoginActivityController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->is_platform_admin, 403);

        $filters = [
            'club_id' => (string) $request->string('club_id')->trim(),
            'q' => (string) $request->string('q')->trim(),
        ];

        return Inertia::render('Platform/LoginHistory', [
            'rows' => $this->mappedRows($filters),
            'clubs' => $this->clubOptions(),
            'filters' => $filters,
        ]);
    }

    public function destroy(Request $request, LoginActivity $login): RedirectResponse
    {
        abort_unless($request->user()?->is_platform_admin, 403);

        $login->delete();

        return back()->with('success', 'Η καταγραφή διαγράφηκε.');
    }

    public function destroyAll(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->is_platform_admin, 403);

        LoginActivity::query()->delete();

        return back()->with('success', 'Το ιστορικό εισόδων διαγράφηκε.');
    }

    /**
     * @param  array{club_id?: string, q?: string}  $filters
     * @return list<array<string, mixed>>
     */
    public static function mappedRows(array $filters = []): array
    {
        $clubId = (string) ($filters['club_id'] ?? '');
        $query = (string) ($filters['q'] ?? '');

        $rows = LoginActivity::query()
            ->with(['user:id,name,email,is_platform_admin,active_club_id', 'club:id,name'])
            ->latest('logged_in_at')
            ->limit(200)
            ->when($clubId === 'platform', fn ($builder) => $builder->whereNull('club_id'))
            ->when(
                $clubId !== '' && $clubId !== 'platform',
                fn ($builder) => $builder->where('club_id', $clubId)
            )
            ->when($query !== '', function ($builder) use ($query) {
                $builder->whereHas('user', function ($userQuery) use ($query) {
                    $like = '%'.$query.'%';
                    $userQuery
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->get();

        $roleByUserClub = self::roleLookup($rows);

        return $rows
            ->map(function (LoginActivity $row) use ($roleByUserClub) {
                $role = self::resolveRole($row, $roleByUserClub);

                return [
                    'id' => $row->id,
                    'user' => $row->user?->name,
                    'email' => $row->user?->email,
                    'club_id' => $row->club_id,
                    'club_name' => $row->club?->name ?? ($row->club_id ? '—' : 'Πλατφόρμα'),
                    'role' => $role,
                    'role_label' => Roles::label($role) ?: '—',
                    'type' => 'login',
                    'type_label' => 'Σύνδεση',
                    'ip_address' => $row->ip_address,
                    'logged_in_at' => optional($row->logged_in_at)?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public static function clubOptions(): array
    {
        return Club::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Club $club) => [
                'id' => $club->id,
                'name' => $club->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, LoginActivity>  $rows
     * @return array<string, string>
     */
    private static function roleLookup(Collection $rows): array
    {
        $pairs = $rows
            ->filter(fn (LoginActivity $row) => $row->user_id && $row->club_id)
            ->map(fn (LoginActivity $row) => [$row->user_id, $row->club_id])
            ->unique(fn (array $pair) => $pair[0].':'.$pair[1])
            ->values();

        if ($pairs->isEmpty()) {
            return [];
        }

        $userIds = $pairs->pluck(0)->unique()->values()->all();
        $clubIds = $pairs->pluck(1)->unique()->values()->all();

        $lookup = [];

        User::query()
            ->whereIn('id', $userIds)
            ->with(['clubs' => fn ($relation) => $relation->whereIn('clubs.id', $clubIds)])
            ->get()
            ->each(function (User $user) use (&$lookup) {
                foreach ($user->clubs as $club) {
                    $lookup[$user->id.':'.$club->id] = (string) ($club->pivot->role ?? '');
                }
            });

        return $lookup;
    }

    /**
     * @param  array<string, string>  $roleByUserClub
     */
    private static function resolveRole(LoginActivity $row, array $roleByUserClub): ?string
    {
        if (! $row->club_id) {
            return $row->user?->is_platform_admin ? Roles::PLATFORM_ADMIN : null;
        }

        $key = $row->user_id.':'.$row->club_id;

        if (($roleByUserClub[$key] ?? '') !== '') {
            return $roleByUserClub[$key];
        }

        if ($row->user?->is_platform_admin) {
            return Roles::PLATFORM_ADMIN;
        }

        return null;
    }
}
