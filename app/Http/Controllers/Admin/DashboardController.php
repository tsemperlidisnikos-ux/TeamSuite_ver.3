<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\ClubSeason;
use App\Models\ClubSetting;
use App\Models\RegistrationApplication;
use App\Models\RentalBooking;
use App\Services\Admin\ClubDashboardMetrics;
use App\Support\ClubHome;
use App\Support\ClubSetupWizard;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ClubDashboardMetrics $metrics): Response|RedirectResponse
    {
        $club = $request->attributes->get('currentClub')
            ?? $request->user()?->activeClub;

        abort_unless($club, 403);

        $user = $request->user();
        if (! ClubHome::canAccessDashboard($user)) {
            $home = ClubHome::routeName($user);
            abort_if($home === 'dashboard', 403, 'Δεν έχετε πρόσβαση στην επισκόπηση.');

            return redirect()->route($home);
        }

        $clubId = $club->id;
        $role = $user?->roleInClub($club);

        if ($role === Roles::DOCTOR) {
            return $this->doctorDashboard($request, $club);
        }

        $today = now()->toDateString();

        $club->loadMissing('licensePackage');
        $athleteCap = (int) ($club->licensePackage?->athlete_cap ?? 0);

        $dashboard = $metrics->forClub($clubId);

        $activeAthletes = collect($dashboard['bySport'])->sum('athletes');
        $permissions = $request->user()?->permissionsInClub($club) ?? [];
        $showFinance = $request->user()?->is_platform_admin
            || $role === Roles::ADMIN
            || in_array('finance', $permissions, true);
        $showRentals = $request->user()?->is_platform_admin
            || in_array('rental', $permissions, true);

        $rentalsToday = $showRentals
            ? RentalBooking::query()->withoutGlobalScopes()
                ->where('club_id', $clubId)
                ->whereDate('starts_at', $today)
                ->whereNotIn('status', ['cancelled', 'rejected'])
                ->count()
            : 0;

        $pendingApplications = in_array('athletes', $permissions, true) || $role === Roles::ADMIN
            ? RegistrationApplication::query()->withoutGlobalScopes()
                ->where('club_id', $clubId)
                ->whereIn('status', ['pending', 'new', 'waiting'])
                ->count()
            : 0;

        $season = ClubSeason::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->where('is_active', true)
            ->first(['name']);

        $canRunSetup = Roles::canRunClubSetup(
            (bool) $user?->is_platform_admin,
            $role,
        );
        $settings = $canRunSetup
            ? $club->settings()->firstOrCreate(
                ['club_id' => $club->id],
                ['id' => (string) Str::uuid()]
            )
            : null;

        return Inertia::render('Admin/Dashboard', [
            'view' => 'admin',
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
            ],
            'seasonName' => $season?->name,
            'showFinance' => $showFinance,
            'showRentals' => $showRentals,
            'setupBanner' => $settings instanceof ClubSetting
                ? ClubSetupWizard::bannerPayload($club, $settings, $canRunSetup)
                : null,
            'kpis' => [
                'activeAthletes' => (int) $activeAthletes,
                'athleteCap' => $athleteCap,
                'rentalsToday' => $rentalsToday,
                'pendingApplications' => $pendingApplications,
            ],
            'bySport' => $dashboard['bySport'],
            'money' => $showFinance ? $dashboard['money'] : null,
            'financeBySport' => $showFinance ? $dashboard['financeBySport'] : [],
            'till' => $showFinance ? $dashboard['till'] : null,
            'uncollectedRentals' => $showRentals ? $dashboard['uncollectedRentals'] : null,
            'doctor' => null,
        ]);
    }

    private function doctorDashboard(Request $request, $club): Response
    {
        $athletes = Athlete::query()->withoutGlobalScopes()
            ->where('club_id', $club->id)
            ->where('status', '!=', 'inactive')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get([
                'id', 'first_name', 'last_name', 'status', 'guardian_name',
                'amka_encrypted', 'health_card', 'health_card_expires',
            ]);

        $withAmka = $athletes->filter(fn (Athlete $a) => filled($a->amka_encrypted))->count();
        $withHealthCard = $athletes->filter(function (Athlete $a) {
            if (! $a->health_card) {
                return false;
            }
            if (! $a->health_card_expires) {
                return true;
            }

            return $a->health_card_expires->greaterThanOrEqualTo(now()->startOfDay());
        })->count();

        $rows = $athletes->take(12)->map(fn (Athlete $a) => [
            'id' => $a->id,
            'first_name' => $a->first_name,
            'last_name' => $a->last_name,
            'status' => $a->status,
            'guardian_name' => $a->guardian_name,
            'amka' => filled($a->amka_encrypted) ? '********' : '',
            'health_card' => (bool) $a->health_card,
        ])->values();

        return Inertia::render('Admin/Dashboard', [
            'view' => 'doctor',
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
            ],
            'seasonName' => null,
            'kpis' => null,
            'bySport' => [],
            'money' => null,
            'financeBySport' => [],
            'till' => null,
            'uncollectedRentals' => null,
            'doctor' => [
                'activeAthletes' => $athletes->count(),
                'withAmka' => $withAmka,
                'withHealthCard' => $withHealthCard,
                'athletes' => $rows,
                'totalAthletes' => $athletes->count(),
            ],
        ]);
    }
}
