<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\RegistrationApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class JoinController extends Controller
{
    public function show(string $slug): Response
    {
        $club = Club::query()->where('slug', $slug)->where('is_active', true)->firstOrFail();
        $settings = $club->settings()->first();
        $publicReg = $settings?->public_registration ?? [];

        return Inertia::render('Public/Join', [
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
                'logo_url' => $club->logo_path ? Storage::disk('public')->url($club->logo_path) : null,
                'city' => ($settings?->profile ?? [])['address'] ?? '',
            ],
            'registration' => [
                'enabled' => (bool) ($settings?->public_join_enabled ?? false),
                'allowTrial' => (bool) ($publicReg['allowTrial'] ?? false),
                'allowWaitlist' => (bool) ($publicReg['allowWaitlist'] ?? false),
                'heroImageUrl' => $publicReg['heroImageUrl'] ?? null,
            ],
        ]);
    }

    public function store(Request $request, string $slug): RedirectResponse
    {
        $club = Club::query()->where('slug', $slug)->where('is_active', true)->firstOrFail();
        $settings = $club->settings()->first();
        abort_unless((bool) ($settings?->public_join_enabled ?? false), 403, 'Η δημόσια εγγραφή δεν είναι ενεργή.');

        $data = $request->validate([
            'type' => 'required|in:full,trial,waitlist',
            'amka' => 'nullable|string|max:20',
            'first_name' => 'required|string|max:120',
            'last_name' => 'required|string|max:120',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|string|max:16',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:40',
            'father_name' => 'nullable|string|max:120',
            'mother_name' => 'nullable|string|max:120',
            'father_email' => 'nullable|email',
            'mother_email' => 'nullable|email',
            'father_phone' => 'nullable|string|max:40',
            'mother_phone' => 'nullable|string|max:40',
            'address' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:120',
            'county' => 'nullable|string|max:120',
            'sport' => 'nullable|string|max:120',
            'uniform_size' => 'nullable|string|max:40',
            'clothing_package' => 'nullable|string|max:120',
            'istos' => 'boolean',
            'preferred_payment' => 'nullable|string|max:120',
            'comments' => 'nullable|string|max:2000',
            'health_declaration' => 'boolean',
            'medical_responsibility' => 'boolean',
            'photo_consent' => 'boolean',
            'gdpr_accepted' => 'accepted',
            'parent_signature' => 'nullable|string|max:160',
        ], [
            'gdpr_accepted.accepted' => 'Απαιτείται «ΕΛΑΒΑ ΓΝΩΣΗ» για την προστασία προσωπικών δεδομένων.',
        ]);

        RegistrationApplication::query()->withoutGlobalScopes()->create([
            'club_id' => $club->id,
            'type' => $data['type'],
            'status' => 'pending',
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'gdpr_accepted' => true,
            'form_snapshot' => $data,
        ]);

        return back()->with('success', 'Η αίτηση υποβλήθηκε.');
    }
}
