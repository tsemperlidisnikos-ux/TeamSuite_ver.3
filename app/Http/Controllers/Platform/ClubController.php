<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\Club;
use App\Models\LicensePackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClubController extends Controller
{
    public function index(): Response
    {
        $clubs = Club::query()
            ->with('licensePackage:id,name,athlete_cap')
            ->orderBy('name')
            ->get()
            ->map(fn (Club $club) => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
                'is_active' => (bool) $club->is_active,
                'license_package_id' => $club->license_package_id,
                'license' => $club->licensePackage?->name,
                'athlete_cap' => $club->licensePackage?->athlete_cap,
                'athletes_count' => Athlete::query()->withoutGlobalScopes()
                    ->where('club_id', $club->id)
                    ->count(),
            ]);

        return Inertia::render('Platform/Clubs', [
            'clubs' => $clubs,
            'packages' => LicensePackage::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'athlete_cap']),
        ]);
    }

    public function updateLicense(Request $request, string $id): RedirectResponse
    {
        abort_unless($request->user()?->is_platform_admin, 403);

        $data = $request->validate([
            'license_package_id' => ['nullable', 'string', 'exists:license_packages,id'],
        ]);

        $club = Club::query()->findOrFail($id);
        $club->forceFill([
            'license_package_id' => $data['license_package_id'] ?: null,
        ])->save();

        return back()->with('success', 'Το πακέτο αδειών ενημερώθηκε για «'.$club->name.'».');
    }

    public function activate(Request $request, string $id): RedirectResponse
    {
        abort_unless($request->user()?->is_platform_admin, 403);

        $club = Club::query()->findOrFail($id);
        $user = $request->user();

        $user->clubs()->syncWithoutDetaching([
            $club->id => [
                'role' => 'admin',
                'permissions' => json_encode([]),
                'finance_own_entries_only' => false,
                'is_active' => true,
            ],
        ]);
        $user->forceFill(['active_club_id' => $club->id])->save();

        if ($request->boolean('preview')) {
            $request->session()->put('platform_preview_club_id', $club->id);

            return redirect()->route('platform.dashboard', [
                'tab' => 'club',
                'item' => 'preview',
            ]);
        }

        $request->session()->forget('platform_preview_club_id');

        return redirect()
            ->route('dashboard')
            ->with('success', 'Ενεργός σύλλογος: '.$club->name);
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        abort_unless($request->user()?->is_platform_admin, 403);

        $club = Club::query()->findOrFail($id);
        abort_if($request->user()->active_club_id === $club->id, 422, 'Αλλάξτε ενεργό σύλλογο πριν τη διαγραφή.');

        $name = $club->name;
        $club->delete();

        return back()->with('success', 'Διαγράφηκε ο σύλλογος: '.$name);
    }
}
