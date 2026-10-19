<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\Club;
use App\Models\LoginActivity;
use App\Models\User;
use App\Services\Audit\ClubActivityLogger;
use App\Services\Platform\PlatformConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PlatformUserController extends Controller
{
    /**
     * @var list<string>
     */
    private const CLUB_ROLES = [
        'admin',
        'doctor',
        'secretariat',
        'coach',
        'staff',
        'athlete',
        'parent',
    ];

    public function index(Request $request, PlatformConfigService $config): Response
    {
        abort_unless($request->user()?->is_platform_admin, 403);

        $role = (string) $request->query('role', 'platform_admin');
        $labels = $config->roleLabels() + ['platform_admin' => 'Διαχειριστής πλατφόρμας'];
        $counts = $this->roleCounts();

        if ($role === 'platform_admin') {
            $rows = User::query()
                ->where('is_platform_admin', true)
                ->with('activeClub:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'is_platform_admin', 'active_club_id', 'created_at'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'user_id' => $user->id,
                    'athlete_id' => null,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => 'platform_admin',
                    'role_label' => $labels['platform_admin'],
                    'club_id' => $user->active_club_id,
                    'club_name' => $user->activeClub?->name,
                    'is_active' => true,
                    'source' => 'user',
                    'can_delete' => $user->id !== $request->user()->id,
                    'can_enter' => $user->id !== $request->user()->id,
                ]);
        } elseif (in_array($role, self::CLUB_ROLES, true)) {
            $rows = User::query()
                ->whereHas('clubs', fn ($q) => $q->where('club_user.role', $role))
                ->with(['clubs' => fn ($q) => $q->wherePivot('role', $role)])
                ->orderBy('name')
                ->get()
                ->flatMap(function (User $user) use ($role, $labels, $request) {
                    return $user->clubs->map(fn (Club $club) => [
                        'id' => $user->id.'|'.$club->id,
                        'user_id' => $user->id,
                        'athlete_id' => null,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $role,
                        'role_label' => $club->name
                            ? $club->name.' '.($labels[$role] ?? $role)
                            : ($labels[$role] ?? $role),
                        'club_id' => $club->id,
                        'club_name' => $club->name,
                        'is_active' => (bool) $club->pivot->is_active,
                        'source' => 'user',
                        'can_delete' => true,
                        'can_enter' => $user->id !== $request->user()->id,
                    ]);
                })
                ->values();

            if ($role === 'athlete') {
                $athleteRows = Athlete::query()->withoutGlobalScopes()
                    ->with('club:id,name')
                    ->orderBy('last_name')
                    ->orderBy('first_name')
                    ->limit(500)
                    ->get(['id', 'club_id', 'first_name', 'last_name', 'email', 'status'])
                    ->map(fn (Athlete $athlete) => [
                        'id' => 'athlete:'.$athlete->id,
                        'user_id' => null,
                        'athlete_id' => $athlete->id,
                        'name' => trim($athlete->last_name.' '.$athlete->first_name),
                        'email' => $athlete->email,
                        'role' => 'athlete',
                        'role_label' => $athlete->club?->name
                            ? $athlete->club->name.' '.($labels['athlete'] ?? 'Αθλητής')
                            : ($labels['athlete'] ?? 'Αθλητής'),
                        'club_id' => $athlete->club_id,
                        'club_name' => $athlete->club?->name,
                        'is_active' => $athlete->status === 'active',
                        'source' => 'athlete',
                        'can_delete' => false,
                        'can_enter' => true,
                    ]);
                $rows = $rows->concat($athleteRows)->values();
            }
        } else {
            $role = 'platform_admin';
            $rows = collect();
        }

        return Inertia::render('Platform/Users', [
            'role' => $role,
            'roleLabels' => $labels,
            'rows' => $rows,
            'clubs' => Club::query()->orderBy('name')->get(['id', 'name']),
            'drill' => [
                [
                    'id' => 'manage',
                    'label' => 'Διαχείριση',
                    'items' => [
                        ['id' => 'platform_admin', 'label' => 'Διαχειριστές πλατφόρμας', 'count' => $counts['platform_admin']],
                        ['id' => 'admin', 'label' => 'Διαχειριστές συλλόγων', 'count' => $counts['admin']],
                        ['id' => 'secretariat', 'label' => 'Γραμματεία', 'count' => $counts['secretariat']],
                        ['id' => 'doctor', 'label' => 'Ιατροί', 'count' => $counts['doctor']],
                    ],
                ],
                [
                    'id' => 'team',
                    'label' => 'Ομάδα',
                    'items' => [
                        ['id' => 'coach', 'label' => 'Προπονητές', 'count' => $counts['coach']],
                        ['id' => 'staff', 'label' => 'Προσωπικό', 'count' => $counts['staff']],
                    ],
                ],
                [
                    'id' => 'members',
                    'label' => 'Μέλη',
                    'items' => [
                        ['id' => 'athlete', 'label' => 'Αθλητές', 'count' => $counts['athlete']],
                        ['id' => 'parent', 'label' => 'Γονείς', 'count' => $counts['parent']],
                    ],
                ],
            ],
        ]);
    }

    public function enter(Request $request): RedirectResponse|SymfonyResponse
    {
        abort_unless($request->user()?->is_platform_admin, 403);

        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'athlete_id' => ['nullable', 'string', 'max:64'],
            'club_id' => ['nullable', 'string', 'max:64', 'exists:clubs,id'],
        ]);

        $actor = $request->user();

        if (! empty($data['athlete_id']) && empty($data['user_id'])) {
            $athlete = Athlete::query()->withoutGlobalScopes()->findOrFail($data['athlete_id']);
            $clubId = $data['club_id'] ?? $athlete->club_id;
            abort_unless($clubId, 422, 'Δεν υπάρχει συνδεδεμένος σύλλογος.');

            $this->attachPlatformAdminToClub($actor, $clubId);
            $actor->forceFill(['active_club_id' => $clubId])->save();

            return redirect()
                ->route('admin.athletes.show', $athlete->id)
                ->with('success', 'Προβολή αθλητή: '.$athlete->last_name.' '.$athlete->first_name);
        }

        $target = User::query()->findOrFail($data['user_id']);

        if ($target->id === $actor->id) {
            return back()->with('error', 'Είστε ήδη συνδεδεμένοι ως αυτός ο χρήστης.');
        }

        $clubId = $data['club_id'] ?? $target->active_club_id;
        if (! $clubId && ! $target->is_platform_admin) {
            $clubId = $target->clubs()->wherePivot('is_active', true)->value('clubs.id');
        }

        if ($clubId) {
            $target->forceFill(['active_club_id' => $clubId])->save();
        }

        if ($target->active_club_id) {
            app(ClubActivityLogger::class)->record(
                $target->active_club_id,
                'auth.impersonate',
                'Είσοδος ως άλλος χρήστης',
                sprintf(
                    '%s εισήλθε ως %s (%s)',
                    $actor->name,
                    $target->name,
                    $target->email
                ),
                'user',
                (string) $target->id,
                ['target_user_id' => $target->id, 'target_email' => $target->email],
                $actor,
            );
        }

        $request->session()->put('impersonator_id', $actor->id);
        Auth::login($target);
        $request->session()->regenerate();

        LoginActivity::query()->create([
            'user_id' => $target->id,
            'club_id' => $target->active_club_id,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500),
            'logged_in_at' => now(),
        ]);

        $destination = $target->is_platform_admin
            ? route('platform.dashboard')
            : route('dashboard');

        $request->session()->flash('success', 'Είσοδος ως '.$target->name);

        // Full page visit so auth/session swap is applied cleanly in Inertia.
        return Inertia::location($destination);
    }

    public function leave(Request $request): RedirectResponse|SymfonyResponse
    {
        $impersonatorId = $request->session()->pull('impersonator_id');
        abort_unless($impersonatorId, 403);

        $admin = User::query()->findOrFail($impersonatorId);
        abort_unless($admin->is_platform_admin, 403);

        Auth::login($admin);
        $request->session()->regenerate();

        $request->session()->flash('success', 'Επιστροφή στη διαχείριση πλατφόρμας.');

        return Inertia::location(route('platform.users.index'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->is_platform_admin, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', Rule::in(array_merge(['platform_admin'], self::CLUB_ROLES))],
            'club_id' => ['nullable', 'string', 'max:64', 'exists:clubs,id'],
        ]);

        if ($data['role'] !== 'platform_admin' && empty($data['club_id'])) {
            return back()->with('error', 'Επιλέξτε σύλλογο για ρόλο συλλόγου.');
        }

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(),
            'is_platform_admin' => $data['role'] === 'platform_admin',
            'active_club_id' => $data['role'] === 'platform_admin' ? null : $data['club_id'],
        ]);

        if ($data['role'] !== 'platform_admin') {
            $user->clubs()->attach($data['club_id'], [
                'role' => $data['role'],
                'permissions' => json_encode([]),
                'finance_own_entries_only' => false,
                'is_active' => true,
            ]);
        }

        return back()->with('success', 'Ο χρήστης δημιουργήθηκε.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()?->is_platform_admin, 403);

        $user = User::query()->findOrFail($id);
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Δεν μπορείτε να διαγράψετε τον εαυτό σας.');
        }

        $user->clubs()->detach();
        $user->delete();

        return back()->with('success', 'Ο χρήστης διαγράφηκε.');
    }

    /**
     * @return array<string, int>
     */
    private function roleCounts(): array
    {
        $counts = [
            'platform_admin' => User::query()->where('is_platform_admin', true)->count(),
            'admin' => 0,
            'doctor' => 0,
            'secretariat' => 0,
            'coach' => 0,
            'staff' => 0,
            'athlete' => 0,
            'parent' => 0,
        ];

        foreach (self::CLUB_ROLES as $clubRole) {
            $counts[$clubRole] = User::query()
                ->whereHas('clubs', fn ($q) => $q->where('club_user.role', $clubRole))
                ->count();
        }

        $counts['athlete'] += Athlete::query()->withoutGlobalScopes()->count();

        return $counts;
    }

    private function attachPlatformAdminToClub(User $admin, string $clubId): void
    {
        $admin->clubs()->syncWithoutDetaching([
            $clubId => [
                'role' => 'admin',
                'permissions' => json_encode([]),
                'finance_own_entries_only' => false,
                'is_active' => true,
            ],
        ]);
    }
}
