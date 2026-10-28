<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\InteractsWithClubResource;
use App\Http\Controllers\Controller;
use App\Models\Association;
use App\Models\Athlete;
use App\Models\ClothingPackage;
use App\Models\Club;
use App\Models\ClubSeason;
use App\Models\ClubSetting;
use App\Models\Coach;
use App\Models\DiscountReason;
use App\Models\Facility;
use App\Models\ReceiptSeries;
use App\Models\SizeChart;
use App\Models\Sport;
use App\Models\User;
use App\Services\Platform\PlatformConfigService;
use App\Support\Navigation;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    use InteractsWithClubResource;

    private const TABS = [
        'club',
        'users',
        'publicRegistration',
        'payments',
        'facilities',
        'email',
        'sms',
        'password',
        'associations',
        'sports',
        'seasons',
        'sizes',
        'clothing',
        'discounts',
        'receipts',
        'terms',
        'amka',
        'backup',
    ];

    private const ROLES = [
        'admin',
        'doctor',
        'secretariat',
        'coach',
        'staff',
        'athlete',
        'parent',
    ];

    public function index(Request $request): Response
    {
        $club = $request->attributes->get('currentClub') ?? $request->user()?->activeClub;
        abort_unless($club instanceof Club, 403);

        $tab = (string) $request->query('tab', 'club');
        if (! in_array($tab, self::TABS, true)) {
            $tab = 'club';
        }

        $settings = $club->settings()->firstOrCreate(
            ['club_id' => $club->id],
            ['id' => (string) Str::uuid()]
        );

        $club->loadMissing('licensePackage');
        $profile = $settings->profile ?? [];
        $activeAthletes = Athlete::query()->withoutGlobalScopes()
            ->where('club_id', $club->id)
            ->where('status', '!=', 'inactive')
            ->count();
        $athleteCap = (int) ($club->licensePackage?->athlete_cap ?? 0);

        return Inertia::render('Admin/Settings/Index', [
            'tab' => $tab,
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
                'logo_url' => $club->logo_path ? Storage::disk('public')->url($club->logo_path) : null,
                'is_active' => (bool) $club->is_active,
                'license' => $club->licensePackage ? [
                    'name' => $club->licensePackage->name,
                    'athlete_cap' => $club->licensePackage->athlete_cap,
                ] : null,
                'license_usage' => [
                    'active' => $activeAthletes,
                    'cap' => $athleteCap > 0 ? $athleteCap : null,
                    'percent' => $athleteCap > 0
                        ? min(100, (int) round(($activeAthletes / $athleteCap) * 100))
                        : null,
                ],
                'profile' => [
                    'vat_number' => $profile['vat_number'] ?? '',
                    'tax_office' => $profile['tax_office'] ?? '',
                    'address' => $profile['address'] ?? ($profile['city'] ?? ''),
                    'founded_year' => $profile['founded_year'] ?? '',
                    'website' => $profile['website'] ?? '',
                    'phone' => $profile['phone'] ?? '',
                    'email' => $profile['email'] ?? '',
                    'custom_charge_label' => $profile['custom_charge_label'] ?? '',
                ],
            ],
            'settings' => [
                'smtp' => $this->maskChannel($settings->smtp),
                'sms' => $this->maskChannel($settings->sms, ['apiKey']),
                'viva' => $this->maskChannel($settings->viva, ['clientSecret']),
                'stripe' => $this->maskChannel($settings->stripe, ['secretKey']),
                'eurobank' => $this->maskChannel($settings->eurobank, ['secret']),
                'public_join_enabled' => (bool) $settings->public_join_enabled,
                'public_rent_enabled' => (bool) $settings->public_rent_enabled,
                'public_registration' => array_merge([
                    'enabled' => (bool) $settings->public_join_enabled,
                    'slug' => $club->slug,
                    'notifyEmail' => '',
                    'allowTrial' => false,
                    'allowWaitlist' => false,
                    'autoApprove' => false,
                    'heroImageUrl' => null,
                ], $settings->public_registration ?? []),
                'terms_of_use_html' => $settings->terms_of_use_html ?? '',
                'dpa_html' => $settings->dpa_html ?? '',
                'retention_policy_html' => $settings->retention_policy_html ?? '',
                'data_retention_months' => (int) ($settings->data_retention_months ?? 24),
                'dpa_accepted_at' => optional($settings->dpa_accepted_at)?->toIso8601String(),
            ],
            'users' => $this->clubUsersPayload($club),
            'roles' => collect(self::ROLES)
                ->map(fn (string $role) => [
                    'id' => $role,
                    'label' => Roles::label($role),
                ])
                ->values()
                ->all(),
            'permissionModules' => collect(Navigation::academyModules())
                ->map(fn (array $module) => [
                    'id' => $module['id'],
                    'label' => $module['label'],
                ])
                ->values()
                ->all(),
            'roleDefaultPermissions' => app(PlatformConfigService::class)->get()['clubRolePermissions']
                ?? app(PlatformConfigService::class)->defaultRolePermissions(),
            'coaches' => Coach::query()
                ->withoutGlobalScopes()
                ->where('club_id', $club->id)
                ->where('is_active', true)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'user_id'])
                ->map(fn (Coach $coach) => [
                    'id' => $coach->id,
                    'first_name' => $coach->first_name,
                    'last_name' => $coach->last_name,
                    'user_id' => $coach->user_id,
                ])
                ->values()
                ->all(),
            'sports' => $this->clubQuery(Sport::class, $request)
                ->orderBy('name')
                ->get(['id', 'name', 'meta']),
            'facilities' => $this->clubQuery(Facility::class, $request)
                ->orderBy('name')
                ->get(['id', 'name', 'address', 'is_rentable', 'meta']),
            'seasons' => $this->clubQuery(ClubSeason::class, $request)
                ->orderByDesc('starts_on')
                ->get(['id', 'name', 'starts_on', 'ends_on', 'is_active'])
                ->map(fn (ClubSeason $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'starts_on' => optional($s->starts_on)->format('Y-m-d'),
                    'ends_on' => optional($s->ends_on)->format('Y-m-d'),
                    'is_active' => (bool) $s->is_active,
                ]),
            'associations' => $this->clubQuery(Association::class, $request)
                ->orderBy('name')
                ->get()
                ->map(fn (Association $a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'city' => $a->meta['city'] ?? '',
                    'phone' => $a->meta['phone'] ?? '',
                    'email' => $a->meta['email'] ?? '',
                    'address' => $a->meta['address'] ?? '',
                    'active' => (bool) ($a->meta['active'] ?? true),
                ]),
            'sizeChart' => optional(
                $this->clubQuery(SizeChart::class, $request)->first()
            )?->chart ?? ['kids' => [], 'men' => [], 'women' => []],
            'clothingPackages' => $this->clubQuery(ClothingPackage::class, $request)
                ->orderBy('name')
                ->get(['id', 'name', 'price']),
            'discountReasons' => $this->clubQuery(DiscountReason::class, $request)
                ->orderBy('name')
                ->get(['id', 'name', 'sport', 'default_amount']),
            'receiptSeries' => $this->clubQuery(ReceiptSeries::class, $request)
                ->orderBy('series')
                ->get(['id', 'series', 'range_from', 'range_to', 'next_number', 'is_active']),
        ]);
    }

    public function updateClub(Request $request): RedirectResponse
    {
        $club = $this->clubModel($request);
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'vat_number' => 'nullable|string|max:32',
            'tax_office' => 'nullable|string|max:120',
            'address' => 'nullable|string|max:255',
            'founded_year' => 'nullable|string|max:8',
            'website' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:160',
            'custom_charge_label' => 'nullable|string|max:120',
            // FormData may omit the file; accept common image types by extension + mime.
            'logo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['sometimes', 'nullable'],
        ]);

        $club->name = $data['name'];

        if ($request->boolean('remove_logo') && $club->logo_path) {
            Storage::disk('public')->delete($club->logo_path);
            $club->logo_path = null;
        }

        $logo = $request->file('logo');
        if ($logo !== null && $logo->isValid()) {
            if ($club->logo_path) {
                Storage::disk('public')->delete($club->logo_path);
            }
            $club->logo_path = $logo->store('club-logos', 'public');
        }

        $club->save();

        $settings = $this->settingsFor($club);
        $settings->profile = array_merge($settings->profile ?? [], [
            'vat_number' => $data['vat_number'] ?? '',
            'tax_office' => $data['tax_office'] ?? '',
            'address' => $data['address'] ?? '',
            'founded_year' => $data['founded_year'] ?? '',
            'website' => $data['website'] ?? '',
            'phone' => $data['phone'] ?? '',
            'email' => $data['email'] ?? '',
            'custom_charge_label' => $data['custom_charge_label'] ?? '',
        ]);
        $settings->save();

        return $this->backTab('club', 'Οι ρυθμίσεις συλλόγου αποθηκεύτηκαν.');
    }

    public function updateEmail(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => 'boolean',
            'provider' => 'nullable|string|max:40',
            'host' => 'nullable|string|max:160',
            'port' => 'nullable|string|max:10',
            'username' => 'nullable|string|max:160',
            'password' => 'nullable|string|max:255',
            'fromName' => 'nullable|string|max:160',
            'fromEmail' => 'nullable|email|max:160',
            'security' => 'nullable|string|max:40',
            'requireAuth' => 'boolean',
        ]);

        $settings = $this->settingsFor($this->clubModel($request));
        $existing = is_array($settings->smtp) ? $settings->smtp : [];
        $merged = $this->mergeSecretChannel($existing, $data, ['password']);

        if (filter_var($merged['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $errors = [];
            if (trim((string) ($merged['host'] ?? '')) === '') {
                $errors['host'] = 'Απαιτείται SMTP host όταν το email είναι ενεργό.';
            }
            if (trim((string) ($merged['fromEmail'] ?? '')) === '') {
                $errors['fromEmail'] = 'Απαιτείται From email όταν το email είναι ενεργό.';
            }
            $requireAuth = filter_var($merged['requireAuth'] ?? true, FILTER_VALIDATE_BOOLEAN);
            if ($requireAuth && trim((string) ($merged['username'] ?? '')) === '') {
                $errors['username'] = 'Απαιτείται username SMTP.';
            }
            if ($requireAuth && trim((string) ($merged['password'] ?? '')) === '') {
                $errors['password'] = 'Απαιτείται password SMTP (ξαναβάλε το αν δεν έχει αποθηκευτεί).';
            }
            if ($errors !== []) {
                return back()->withErrors($errors)->withInput();
            }
        }

        $settings->smtp = $merged;
        $settings->save();

        return $this->backTab('email', 'Οι ρυθμίσεις email αποθηκεύτηκαν.');
    }

    public function updateSms(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => 'boolean',
            'provider' => 'nullable|string|max:40',
            'apiKey' => 'nullable|string|max:255',
            'sender' => 'nullable|string|max:11',
            'httpUrl' => 'nullable|string|max:255',
        ]);

        $settings = $this->settingsFor($this->clubModel($request));
        $settings->sms = $this->mergeSecretChannel($settings->sms ?? [], $data, ['apiKey']);
        $settings->save();

        return $this->backTab('sms', 'Οι ρυθμίσεις SMS αποθηκεύτηκαν.');
    }

    public function updatePayments(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider' => 'required|in:viva,stripe,eurobank',
            'enabled' => 'boolean',
            'clientId' => 'nullable|string|max:255',
            'clientSecret' => 'nullable|string|max:255',
            'merchantId' => 'nullable|string|max:255',
            'sourceCode' => 'nullable|string|max:16',
            'demo' => 'boolean',
            'publishableKey' => 'nullable|string|max:255',
            'secretKey' => 'nullable|string|max:255',
            'secret' => 'nullable|string|max:255',
        ]);

        $settings = $this->settingsFor($this->clubModel($request));
        $provider = $data['provider'];
        unset($data['provider']);
        $secretKeys = match ($provider) {
            'viva' => ['clientSecret'],
            'stripe' => ['secretKey'],
            'eurobank' => ['secret'],
        };
        $settings->{$provider} = $this->mergeSecretChannel($settings->{$provider} ?? [], $data, $secretKeys);
        $settings->save();

        return $this->backTab('payments', 'Οι ρυθμίσεις πληρωμών αποθηκεύτηκαν.');
    }

    public function updatePublicRegistration(Request $request): RedirectResponse
    {
        $club = $this->clubModel($request);
        $settings = $this->settingsFor($club);
        $data = $request->validate([
            'enabled' => 'boolean',
            'slug' => 'nullable|string|max:80',
            'notifyEmail' => 'nullable|email|max:160',
            'allowTrial' => 'boolean',
            'allowWaitlist' => 'boolean',
            'public_rent_enabled' => 'boolean',
        ]);

        $enabled = filter_var($data['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($enabled && ! $settings->dpa_accepted_at) {
            return $this->backTab('publicRegistration', null, 'Απαιτείται αποδοχή DPA (καρτέλα GDPR) πριν ενεργοποιηθεί η δημόσια εγγραφή.');
        }

        $settings->public_registration = array_merge($settings->public_registration ?? [], [
            'enabled' => $enabled,
            'slug' => $data['slug'] ?? $club->slug,
            'notifyEmail' => $data['notifyEmail'] ?? '',
            'allowTrial' => filter_var($data['allowTrial'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'allowWaitlist' => filter_var($data['allowWaitlist'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'autoApprove' => false,
        ]);
        $settings->public_join_enabled = $enabled;
        $settings->public_rent_enabled = filter_var($data['public_rent_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $settings->save();

        return $this->backTab('publicRegistration', 'Οι ρυθμίσεις δημόσιας εγγραφής αποθηκεύτηκαν.');
    }

    public function updateTerms(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'terms_of_use_html' => 'nullable|string',
        ]);
        $settings = $this->settingsFor($this->clubModel($request));
        $settings->terms_of_use_html = $data['terms_of_use_html'] ?? '';
        $settings->save();

        return $this->backTab('terms', 'Οι όροι χρήσης αποθηκεύτηκαν.');
    }

    public function updateGdpr(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'dpa_html' => 'nullable|string',
            'retention_policy_html' => 'nullable|string',
            'data_retention_months' => 'nullable|integer|min:1|max:120',
            'accept_dpa' => 'boolean',
        ]);

        $settings = $this->settingsFor($this->clubModel($request));
        if (array_key_exists('dpa_html', $data)) {
            $settings->dpa_html = $data['dpa_html'];
        }
        if (array_key_exists('retention_policy_html', $data)) {
            $settings->retention_policy_html = $data['retention_policy_html'];
        }
        if (array_key_exists('data_retention_months', $data) && $data['data_retention_months'] !== null) {
            $settings->data_retention_months = (int) $data['data_retention_months'];
        }
        if (filter_var($data['accept_dpa'] ?? false, FILTER_VALIDATE_BOOLEAN) && ! $settings->dpa_accepted_at) {
            $settings->dpa_accepted_at = now();
        }
        $settings->save();

        return $this->backTab('amka', 'Οι ρυθμίσεις GDPR αποθηκεύτηκαν.');
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $club = $this->clubModel($request);
        $moduleIds = array_column(Navigation::academyModules(), 'id');
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'email' => 'required|email|max:160',
            'password' => 'required|string|min:6|max:120',
            'role' => ['required', Rule::in(self::ROLES)],
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in($moduleIds)],
            'finance_own_entries_only' => ['sometimes', 'nullable'],
            'is_active' => ['sometimes', 'nullable'],
            'coach_id' => ['nullable', 'string', Rule::exists('coaches', 'id')],
        ]);

        $user = User::query()->where('email', $data['email'])->first();
        if (! $user) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'email_verified_at' => now(),
                'active_club_id' => $club->id,
            ]);
        } else {
            $user->fill(['name' => $data['name']])->save();
            if (! empty($data['password'])) {
                $user->password = $data['password'];
                $user->save();
            }
        }

        $club->users()->syncWithoutDetaching([
            $user->id => [
                'role' => $data['role'],
                'permissions' => json_encode(array_values($data['permissions'] ?? [])),
                'finance_own_entries_only' => $request->boolean('finance_own_entries_only'),
                'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
            ],
        ]);

        $this->syncCoachUserLink($club, $user, $data['role'], $data['coach_id'] ?? null);

        return $this->backTab('users', 'Ο χρήστης αποθηκεύτηκε.');
    }

    public function updateUser(Request $request, int $id): RedirectResponse
    {
        $club = $this->clubModel($request);
        $moduleIds = array_column(Navigation::academyModules(), 'id');
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'role' => ['required', Rule::in(self::ROLES)],
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in($moduleIds)],
            'finance_own_entries_only' => ['sometimes', 'nullable'],
            'is_active' => ['sometimes', 'nullable'],
            'password' => 'nullable|string|min:6|max:120',
            'coach_id' => ['nullable', 'string', Rule::exists('coaches', 'id')],
        ]);

        $user = $club->users()->where('users.id', $id)->firstOrFail();
        $user->fill(['name' => $data['name']])->save();
        if (! empty($data['password'])) {
            $user->password = $data['password'];
            $user->save();
        }

        $club->users()->updateExistingPivot($user->id, [
            'role' => $data['role'],
            'permissions' => json_encode(array_values($data['permissions'] ?? [])),
            'finance_own_entries_only' => $request->boolean('finance_own_entries_only'),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : (bool) $user->pivot->is_active,
        ]);

        $this->syncCoachUserLink($club, $user, $data['role'], $data['coach_id'] ?? null);

        return $this->backTab('users', 'Ο χρήστης ενημερώθηκε.');
    }

    public function destroyUser(Request $request, int $id): RedirectResponse
    {
        $club = $this->clubModel($request);
        abort_if($request->user()?->id === $id, 422, 'Δεν μπορείτε να αφαιρέσετε τον εαυτό σας.');
        $club->users()->detach($id);

        return $this->backTab('users', 'Ο χρήστης αφαιρέθηκε από τον σύλλογο.');
    }

    public function storeSport(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:120']);

        Sport::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $this->clubId($request),
            'name' => $data['name'],
        ]);

        return $this->backTab('sports', 'Το άθλημα προστέθηκε.');
    }

    public function destroySport(Request $request, string $id): RedirectResponse
    {
        $this->clubQuery(Sport::class, $request)->findOrFail($id)->delete();

        return $this->backTab('sports', 'Το άθλημα διαγράφηκε.');
    }

    public function storeFacility(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'address' => 'nullable|string|max:255',
            'is_rentable' => 'boolean',
        ]);

        Facility::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $this->clubId($request),
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'is_rentable' => filter_var($data['is_rentable'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ]);

        return $this->backTab('facilities', 'Η εγκατάσταση προστέθηκε.');
    }

    public function destroyFacility(Request $request, string $id): RedirectResponse
    {
        $this->clubQuery(Facility::class, $request)->findOrFail($id)->delete();

        return $this->backTab('facilities', 'Η εγκατάσταση διαγράφηκε.');
    }

    public function storeSeason(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'starts_on' => 'nullable|date',
            'ends_on' => 'nullable|date|after_or_equal:starts_on',
            'is_active' => 'boolean',
        ]);

        $clubId = $this->clubId($request);
        $isActive = filter_var($data['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($isActive) {
            ClubSeason::query()->withoutGlobalScopes()
                ->where('club_id', $clubId)
                ->update(['is_active' => false]);
        }

        ClubSeason::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $clubId,
            'name' => $data['name'],
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
            'is_active' => $isActive,
        ]);

        return $this->backTab('seasons', 'Η σεζόν προστέθηκε.');
    }

    public function updateSeason(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'starts_on' => 'nullable|date',
            'ends_on' => 'nullable|date|after_or_equal:starts_on',
            'is_active' => 'boolean',
        ]);

        $season = $this->clubQuery(ClubSeason::class, $request)->findOrFail($id);
        $isActive = filter_var($data['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($isActive) {
            ClubSeason::query()->withoutGlobalScopes()
                ->where('club_id', $season->club_id)
                ->where('id', '!=', $season->id)
                ->update(['is_active' => false]);
        }

        $season->fill([
            'name' => $data['name'],
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
            'is_active' => $isActive,
        ])->save();

        return $this->backTab('seasons', 'Η σεζόν ενημερώθηκε.');
    }

    public function destroySeason(Request $request, string $id): RedirectResponse
    {
        $this->clubQuery(ClubSeason::class, $request)->findOrFail($id)->delete();

        return $this->backTab('seasons', 'Η σεζόν διαγράφηκε.');
    }

    public function storeAssociation(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'city' => 'nullable|string|max:120',
            'phone' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:160',
            'address' => 'nullable|string|max:255',
            'active' => 'boolean',
        ]);

        Association::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $this->clubId($request),
            'name' => $data['name'],
            'meta' => [
                'city' => $data['city'] ?? '',
                'phone' => $data['phone'] ?? '',
                'email' => $data['email'] ?? '',
                'address' => $data['address'] ?? '',
                'active' => filter_var($data['active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ],
        ]);

        return $this->backTab('associations', 'Το σωματείο προστέθηκε.');
    }

    public function destroyAssociation(Request $request, string $id): RedirectResponse
    {
        $this->clubQuery(Association::class, $request)->findOrFail($id)->delete();

        return $this->backTab('associations', 'Το σωματείο διαγράφηκε.');
    }

    public function updateSizeChart(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sizes' => 'nullable|array',
            'sizes.*' => 'string|max:40',
        ]);

        $sizes = array_values(array_filter(array_map('strval', $data['sizes'] ?? [])));
        $clubId = $this->clubId($request);
        $chart = $this->clubQuery(SizeChart::class, $request)->first();
        $payload = ['chart' => ['kids' => $sizes, 'men' => [], 'women' => []]];
        if ($chart) {
            $chart->fill($payload)->save();
        } else {
            SizeChart::query()->withoutGlobalScopes()->create([
                'id' => (string) Str::uuid(),
                'club_id' => $clubId,
                ...$payload,
            ]);
        }

        return $this->backTab('sizes', 'Το μεγεθολόγιο αποθηκεύτηκε.');
    }

    public function storeClothing(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'price' => 'nullable|numeric|min:0',
        ]);

        ClothingPackage::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $this->clubId($request),
            'name' => $data['name'],
            'price' => ($data['price'] ?? null) !== null && ($data['price'] ?? '') !== '' ? $data['price'] : 0,
        ]);

        return $this->backTab('clothing', 'Το πακέτο ρουχισμού προστέθηκε.');
    }

    public function destroyClothing(Request $request, string $id): RedirectResponse
    {
        $this->clubQuery(ClothingPackage::class, $request)->findOrFail($id)->delete();

        return $this->backTab('clothing', 'Το πακέτο διαγράφηκε.');
    }

    public function storeDiscount(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'sport' => 'nullable|string|max:120',
            'default_amount' => 'nullable|numeric|min:0',
        ]);

        DiscountReason::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $this->clubId($request),
            'name' => $data['name'],
            'sport' => $data['sport'] ?? null,
            'default_amount' => $data['default_amount'] ?? null,
        ]);

        return $this->backTab('discounts', 'Ο λόγος έκπτωσης προστέθηκε.');
    }

    public function destroyDiscount(Request $request, string $id): RedirectResponse
    {
        $this->clubQuery(DiscountReason::class, $request)->findOrFail($id)->delete();

        return $this->backTab('discounts', 'Ο λόγος έκπτωσης διαγράφηκε.');
    }

    public function storeReceiptSeries(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'series' => 'required|string|max:40',
            'range_from' => 'required|integer|min:1',
            'range_to' => 'required|integer|gte:range_from',
        ]);

        ReceiptSeries::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $this->clubId($request),
            'series' => $data['series'],
            'range_from' => $data['range_from'],
            'range_to' => $data['range_to'],
            'next_number' => $data['range_from'],
            'is_active' => true,
        ]);

        return redirect()
            ->route('admin.receipts.index', ['tab' => 'numbering'])
            ->with('success', 'Η σειρά αποδείξεων προστέθηκε.');
    }

    public function destroyReceiptSeries(Request $request, string $id): RedirectResponse
    {
        $this->clubQuery(ReceiptSeries::class, $request)->findOrFail($id)->delete();

        return redirect()
            ->route('admin.receipts.index', ['tab' => 'numbering'])
            ->with('success', 'Η σειρά αποδείξεων διαγράφηκε.');
    }

    private function clubModel(Request $request): Club
    {
        $club = $request->attributes->get('currentClub') ?? $request->user()?->activeClub;
        abort_unless($club instanceof Club, 403);

        return $club;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function clubUsersPayload(Club $club): array
    {
        $coachByUserId = Coach::query()
            ->withoutGlobalScopes()
            ->where('club_id', $club->id)
            ->whereNotNull('user_id')
            ->pluck('id', 'user_id');

        return $club->users()
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->pivot->role,
                'permissions' => is_string($u->pivot->permissions)
                    ? (json_decode($u->pivot->permissions, true) ?: [])
                    : ($u->pivot->permissions ?? []),
                'finance_own_entries_only' => (bool) $u->pivot->finance_own_entries_only,
                'is_active' => (bool) $u->pivot->is_active,
                'coach_id' => $coachByUserId[$u->id] ?? null,
            ])
            ->values()
            ->all();
    }

    private function syncCoachUserLink(Club $club, User $user, string $role, ?string $coachId): void
    {
        Coach::query()
            ->withoutGlobalScopes()
            ->where('club_id', $club->id)
            ->where('user_id', $user->id)
            ->update(['user_id' => null]);

        if ($role !== 'coach' || ! $coachId) {
            return;
        }

        $coach = Coach::query()
            ->withoutGlobalScopes()
            ->where('club_id', $club->id)
            ->whereKey($coachId)
            ->first();

        if ($coach) {
            $coach->user_id = $user->id;
            $coach->save();
        }
    }

    private function settingsFor(Club $club): ClubSetting
    {
        return $club->settings()->firstOrCreate(
            ['club_id' => $club->id],
            ['id' => (string) Str::uuid()]
        );
    }

    private function backTab(string $tab, ?string $success = null, ?string $error = null): RedirectResponse
    {
        $response = redirect()->route('admin.settings.index', ['tab' => $tab]);
        if ($success) {
            $response->with('success', $success);
        }
        if ($error) {
            $response->with('error', $error);
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>|null  $channel
     * @param  list<string>  $secretKeys
     * @return array<string, mixed>
     */
    private function maskChannel(?array $channel, array $secretKeys = ['password']): array
    {
        $channel = $channel ?? [];
        foreach ($secretKeys as $key) {
            $has = ! empty($channel[$key]);
            $channel[$key] = '';
            $channel[$key.'Set'] = $has;
        }

        return $channel;
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $incoming
     * @param  list<string>  $secretKeys
     * @return array<string, mixed>
     */
    private function mergeSecretChannel(array $existing, array $incoming, array $secretKeys): array
    {
        foreach ($secretKeys as $key) {
            $value = $incoming[$key] ?? null;
            if ($value === null || $value === '' || $value === '••••••••') {
                $incoming[$key] = $existing[$key] ?? '';
            }
            unset($incoming[$key.'Set']);
        }

        return array_merge($existing, $incoming);
    }
}
