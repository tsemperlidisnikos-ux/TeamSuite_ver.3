<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\LicensePackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LicensePackageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Platform/LicensePackages', [
            'packages' => LicensePackage::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'athlete_cap' => 'required|integer|min:1',
        ]);

        LicensePackage::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $data['name'],
            'athlete_cap' => $data['athlete_cap'],
            'modules' => ['*'],
            'is_active' => true,
        ]);

        return back()->with('success', 'Το πακέτο δημιουργήθηκε.');
    }
}
