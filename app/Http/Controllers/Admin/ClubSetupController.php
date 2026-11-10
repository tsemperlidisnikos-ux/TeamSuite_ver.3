<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\InteractsWithClubResource;
use App\Http\Controllers\Controller;
use App\Models\AcademyClass;
use App\Models\Club;
use App\Models\ClubSeason;
use App\Models\ClubSetting;
use App\Models\Sport;
use App\Models\User;
use App\Services\Platform\PlatformConfigService;
use App\Support\ClubSetupWizard;
use App\Support\Navigation;
use App\Support\Roles;
use App\Support\UserPasswordVault;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClubSetupController extends Controller
{
    use InteractsWithClubResource;

    public function index(Request $request): Response
    {
        $club = $this->clubModel($request);
        $this->authorizeSetup($request, $club);

        $settings = $this->settingsFor($club);
        $progress = ClubSetupWizard::progress($club, $settings);
        $profile = is_array($settings->profile) ? $settings->profile : [];

        $activeSeason = ClubSeason::query()
            ->withoutGlobalScopes()
            ->where('club_id', $club->id)
            ->where('is_active', true)
            ->first(['id', 'name', 'starts_on', 'ends_on']);

        return Inertia::render('Admin/Setup/Wizard', [
            'progress' => $progress,
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
                'profile' => [
                    'address' => $profile['address'] ?? ($profile['city'] ?? ''),
                    'phone' => $profile['phone'] ?? '',
                    'email' => $profile['email'] ?? '',
                ],
            ],
            'publicJoin' => [
                'enabled' => (bool) ($settings->public_registration['enabled'] ?? $settings->public_join_enabled),
                'dpa_accepted' => (bool) $settings->dpa_accepted_at,
                'join_url' => url('/join/'.$club->slug),
            ],
            'payments' => [
                'viva_enabled' => (bool) (($settings->viva['enabled'] ?? false)),
                'stripe_enabled' => (bool) (($settings->stripe['enabled'] ?? false)),
                'eurobank_enabled' => (bool) (($settings->eurobank['enabled'] ?? false)),
                'configured' => ClubSetupWizard::hasOnlinePaymentsConfigured($settings),
            ],
            'sports' => Sport::query()
                ->withoutGlobalScopes()
                ->where('club_id', $club->id)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Sport $sport) => [
                    'id' => $sport->id,
                    'name' => $sport->name,
                ])
                ->values()
                ->all(),
            'seasons' => ClubSeason::query()
                ->withoutGlobalScopes()
                ->where('club_id', $club->id)
                ->orderByDesc('starts_on')
                ->get(['id', 'name', 'starts_on', 'ends_on', 'is_active'])
                ->map(fn (ClubSeason $season) => [
                    'id' => $season->id,
                    'name' => $season->name,
                    'starts_on' => optional($season->starts_on)->format('Y-m-d'),
                    'ends_on' => optional($season->ends_on)->format('Y-m-d'),
                    'is_active' => (bool) $season->is_active,
                ])
                ->values()
                ->all(),
            'activeSeasonId' => $activeSeason?->id,
            'sessionEmail' => $request->user()?->email,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $club = $this->clubModel($request);
        $this->authorizeSetup($request, $club);
        $settings = $this->settingsFor($club);

        $data = $request->validate([
            'action' => ['required', 'string', Rule::in([
                'profile',
                'classes',
                'publicJoin',
                'payments',
                'secretariat',
                'skip',
                'dismiss',
                'reopen',
            ])],
            'step' => ['nullable', 'string', Rule::in(ClubSetupWizard::STEP_IDS)],
            'name' => ['nullable', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'],
            'class_name' => ['nullable', 'string', 'max:160'],
            'sport' => ['nullable', 'string', 'max:80'],
            'club_season_id' => ['nullable', 'uuid'],
            'attendance_required' => ['sometimes', 'nullable'],
            'join_enabled' => ['sometimes', 'nullable'],
            'accept_dpa' => ['sometimes', 'nullable'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'first_name' => ['nullable', 'string', 'max:80'],
            'sec_email' => ['nullable', 'email', 'max:160'],
            'password' => ['nullable', 'string', 'min:6', 'max:120'],
            'provider' => ['nullable', 'in:viva,stripe,eurobank'],
            'enabled' => ['sometimes', 'nullable'],
            'clientId' => ['nullable', 'string', 'max:255'],
            'clientSecret' => ['nullable', 'string', 'max:255'],
            'merchantId' => ['nullable', 'string', 'max:255'],
            'sourceCode' => ['nullable', 'string', 'max:16'],
            'demo' => ['sometimes', 'nullable'],
            'publishableKey' => ['nullable', 'string', 'max:255'],
            'secretKey' => ['nullable', 'string', 'max:255'],
            'secret' => ['nullable', 'string', 'max:255'],
        ]);

        return match ($data['action']) {
            'profile' => $this->saveProfile($club, $settings, $data),
            'classes' => $this->saveClass($request, $club, $data),
            'publicJoin' => $this->savePublicJoin($settings, $club, $data),
            'payments' => $this->savePayments($settings, $data),
            'secretariat' => $this->saveSecretariat($club, $data),
            'skip' => $this->skipStep($settings, $data['step'] ?? null),
            'dismiss' => $this->setDismissed($settings, true),
            'reopen' => $this->setDismissed($settings, false),
            default => redirect()->route('admin.setup.wizard')->with('error', 'Άγνωστη ενέργεια.'),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveProfile(Club $club, ClubSetting $settings, array $data): RedirectResponse
    {
        $validated = validator($data, [
            'name' => 'required|string|max:160',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:160',
        ])->validate();

        $club->name = $validated['name'];
        $club->save();

        $profile = is_array($settings->profile) ? $settings->profile : [];
        $settings->profile = array_merge($profile, [
            'address' => $validated['address'] ?? '',
            'phone' => $validated['phone'] ?? '',
            'email' => $validated['email'] ?? '',
        ]);
        $settings->save();

        return redirect()
            ->route('admin.setup.wizard')
            ->with('success', 'Το προφίλ αποθηκεύτηκε.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveClass(Request $request, Club $club, array $data): RedirectResponse
    {
        $validated = validator($data, [
            'class_name' => 'required|string|max:160',
            'sport' => 'nullable|string|max:80',
            'club_season_id' => [
                'nullable',
                'uuid',
                Rule::exists('club_seasons', 'id')->where(fn ($q) => $q->where('club_id', $club->id)),
            ],
            'attendance_required' => ['sometimes', 'nullable'],
        ])->validate();

        AcademyClass::query()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'name' => $validated['class_name'],
            'sport' => $validated['sport'] ?? null,
            'club_season_id' => $validated['club_season_id'] ?? null,
            'meta' => [
                'attendanceRequired' => $request->boolean('attendance_required', true),
            ],
        ]);

        return redirect()
            ->route('admin.setup.wizard')
            ->with('success', 'Δημιουργήθηκε το τμήμα «'.$validated['class_name'].'».');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function savePublicJoin(ClubSetting $settings, Club $club, array $data): RedirectResponse
    {
        $enabled = filter_var($data['join_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $acceptDpa = filter_var($data['accept_dpa'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($enabled && ! $settings->dpa_accepted_at) {
            if (! $acceptDpa) {
                return redirect()
                    ->route('admin.setup.wizard')
                    ->with('error', 'Απαιτείται αποδοχή DPA για δημόσια εγγραφή.');
            }
            $settings->dpa_accepted_at = now();
        }

        $settings->public_registration = array_merge($settings->public_registration ?? [], [
            'enabled' => $enabled,
            'slug' => $club->slug,
            'autoApprove' => false,
        ]);
        $settings->public_join_enabled = $enabled;
        $settings->save();

        return redirect()
            ->route('admin.setup.wizard')
            ->with('success', $enabled
                ? 'Η δημόσια φόρμα ενεργοποιήθηκε.'
                : 'Η δημόσια φόρμα αποθηκεύτηκε.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function savePayments(ClubSetting $settings, array $data): RedirectResponse
    {
        $provider = $data['provider'] ?? null;
        if (! in_array($provider, ['viva', 'stripe', 'eurobank'], true)) {
            return redirect()
                ->route('admin.setup.wizard')
                ->with('error', 'Επιλέξτε πάροχο πληρωμών ή παραλείψτε το βήμα.');
        }

        $incoming = [
            'enabled' => filter_var($data['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'clientId' => $data['clientId'] ?? '',
            'clientSecret' => $data['clientSecret'] ?? '',
            'merchantId' => $data['merchantId'] ?? '',
            'sourceCode' => $data['sourceCode'] ?? '',
            'demo' => filter_var($data['demo'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'publishableKey' => $data['publishableKey'] ?? '',
            'secretKey' => $data['secretKey'] ?? '',
            'secret' => $data['secret'] ?? '',
        ];

        $secretKeys = match ($provider) {
            'viva' => ['clientSecret'],
            'stripe' => ['secretKey'],
            'eurobank' => ['secret'],
        };

        $existing = is_array($settings->{$provider}) ? $settings->{$provider} : [];
        foreach ($secretKeys as $key) {
            $value = $incoming[$key] ?? null;
            if ($value === null || $value === '' || $value === '••••••••') {
                $incoming[$key] = $existing[$key] ?? '';
            }
        }

        $settings->{$provider} = array_merge($existing, $incoming);
        $settings->save();

        return redirect()
            ->route('admin.setup.wizard')
            ->with('success', 'Οι ρυθμίσεις πληρωμών αποθηκεύτηκαν.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveSecretariat(Club $club, array $data): RedirectResponse
    {
        $validated = validator($data, [
            'last_name' => 'required|string|max:80',
            'first_name' => 'required|string|max:80',
            'sec_email' => 'required|email|max:160',
            'password' => 'required|string|min:6|max:120',
        ])->validate();

        $fullName = trim($validated['last_name'].' '.$validated['first_name']);
        $email = $validated['sec_email'];

        $permissions = app(PlatformConfigService::class)->defaultRolePermissions()[Roles::SECRETARIAT]
            ?? array_column(Navigation::academyModules(), 'id');

        $user = User::query()->where('email', $email)->first();
        if (! $user) {
            $user = User::query()->create([
                'name' => $fullName,
                'email' => $email,
                'password' => $validated['password'],
                'password_reveal' => UserPasswordVault::seal($validated['password']),
                'email_verified_at' => now(),
                'active_club_id' => $club->id,
            ]);
        } else {
            $user->fill(['name' => $fullName])->save();
            UserPasswordVault::setPassword($user, $validated['password']);
        }

        $club->users()->syncWithoutDetaching([
            $user->id => [
                'role' => Roles::SECRETARIAT,
                'permissions' => json_encode(array_values($permissions)),
                'finance_own_entries_only' => false,
                'is_active' => true,
            ],
        ]);

        return redirect()
            ->route('admin.setup.wizard')
            ->with('success', 'Ο λογαριασμός γραμματείας δημιουργήθηκε.');
    }

    private function skipStep(ClubSetting $settings, ?string $step): RedirectResponse
    {
        if (! $step || ! in_array($step, ClubSetupWizard::STEP_IDS, true)) {
            return redirect()
                ->route('admin.setup.wizard')
                ->with('error', 'Άκυρο βήμα για παράλειψη.');
        }

        if ($step === ClubSetupWizard::STEP_PROFILE) {
            return redirect()
                ->route('admin.setup.wizard')
                ->with('error', 'Το προφίλ δεν παραλείπεται.');
        }

        ClubSetupWizard::writeState($settings, [
            'skipped' => [$step => true],
        ]);

        return redirect()
            ->route('admin.setup.wizard')
            ->with('success', 'Το βήμα παραλείφθηκε. Μπορείτε να το ρυθμίσετε αργότερα.');
    }

    private function setDismissed(ClubSetting $settings, bool $dismissed): RedirectResponse
    {
        ClubSetupWizard::writeState($settings, ['dismissed' => $dismissed]);

        if ($dismissed) {
            return redirect()
                ->route('dashboard')
                ->with('success', 'Ο οδηγός αποκρύφθηκε. Μπορείτε να τον ανοίξετε από τις ρυθμίσεις.');
        }

        return redirect()->route('admin.setup.wizard');
    }

    private function authorizeSetup(Request $request, Club $club): void
    {
        $user = $request->user();
        abort_unless(
            Roles::canRunClubSetup(
                (bool) $user?->is_platform_admin,
                $user?->roleInClub($club),
            ),
            403,
            'Μόνο διαχειριστής συλλόγου, γραμματεία ή διαχειριστής πλατφόρμας μπορούν να τρέξουν τον οδηγό.',
        );
    }

    private function clubModel(Request $request): Club
    {
        $club = $request->attributes->get('currentClub') ?? $request->user()?->activeClub;
        abort_unless($club instanceof Club, 403);

        return $club;
    }

    private function settingsFor(Club $club): ClubSetting
    {
        return $club->settings()->firstOrCreate(
            ['club_id' => $club->id],
            ['id' => (string) Str::uuid()]
        );
    }
}
