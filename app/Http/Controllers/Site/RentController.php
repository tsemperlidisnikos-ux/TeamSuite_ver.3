<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Facility;
use App\Models\RentalBooking;
use App\Models\RentalSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class RentController extends Controller
{
    public function show(string $slug): Response
    {
        $club = Club::query()->where('slug', $slug)->where('is_active', true)->firstOrFail();
        $clubSettings = $club->settings()->first();
        $rentalSettings = RentalSetting::query()->where('club_id', $club->id)->first();
        $publicEnabled = (bool) ($clubSettings?->public_rent_enabled ?? false)
            && (bool) ($rentalSettings?->public_enabled ?? true);

        $rulesPayload = $this->normalizeRulesPayload($rentalSettings?->rules);
        $rulesByFacility = $this->rulesByFacilityId($rulesPayload['rules']);

        $facilities = collect();
        $prices = [];

        if ($publicEnabled) {
            $facilities = Facility::query()->withoutGlobalScopes()
                ->where('club_id', $club->id)
                ->where('is_rentable', true)
                ->orderBy('name')
                ->get(['id', 'name', 'meta'])
                ->filter(function (Facility $facility) use ($rulesByFacility) {
                    $rule = $rulesByFacility[$facility->id] ?? null;

                    return $rule === null || (bool) ($rule['enabled'] ?? true);
                })
                ->values()
                ->map(function (Facility $facility) use ($rulesByFacility) {
                    $meta = is_array($facility->meta) ? $facility->meta : [];
                    $rule = $rulesByFacility[$facility->id] ?? null;

                    return [
                        'id' => $facility->id,
                        'name' => $facility->name,
                        'photoUrl' => $meta['photoUrl'] ?? $meta['photo_url'] ?? null,
                        'slotMinutes' => (int) ($rule['slotMinutes'] ?? 60) ?: 60,
                        'windows' => $this->normalizeWindows($rule['windows'] ?? null),
                    ];
                });

            foreach ($facilities as $facility) {
                $rule = $rulesByFacility[$facility['id']] ?? [];
                $full = (float) ($rule['hourlyRateFull'] ?? $rule['hourlyRate'] ?? 0);
                $half = (float) ($rule['hourlyRateHalf'] ?? 0);
                $prices[] = [
                    'facilityId' => $facility['id'],
                    'hourlyRateFull' => $full,
                    'hourlyRateHalf' => $half,
                    'lockerRoomAvailable' => (bool) ($rule['lockerRoomAvailable'] ?? false),
                    'lockerRoomFee' => (float) ($rule['lockerRoomFee'] ?? 0),
                    'slotMinutes' => (int) ($rule['slotMinutes'] ?? 60) ?: 60,
                    'windows' => $this->normalizeWindows($rule['windows'] ?? null),
                ];
            }
        }

        $viva = is_array($clubSettings?->viva) ? $clubSettings->viva : [];
        $payOnline = (bool) ($viva['enabled'] ?? false);

        return Inertia::render('Public/Rent', [
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
                'logo_url' => $club->logo_path ? Storage::disk('public')->url($club->logo_path) : null,
                'heroImageUrl' => $rulesPayload['heroImageUrl'],
                'notes' => $rulesPayload['notes'],
            ],
            'publicEnabled' => $publicEnabled,
            'facilities' => $facilities,
            'prices' => $prices,
            'payOnline' => $payOnline,
        ]);
    }

    public function store(Request $request, string $slug): RedirectResponse
    {
        $club = Club::query()->where('slug', $slug)->where('is_active', true)->firstOrFail();
        $clubSettings = $club->settings()->first();
        $rentalSettings = RentalSetting::query()->where('club_id', $club->id)->first();
        abort_unless(
            (bool) ($clubSettings?->public_rent_enabled ?? false) && (bool) ($rentalSettings?->public_enabled ?? true),
            403,
            'Η δημόσια ενοικίαση δεν είναι ενεργή.'
        );

        $data = $request->validate([
            'facility_id' => 'nullable|uuid',
            'customer_name' => 'nullable|string|max:200',
            'customer_last_name' => 'required_without:customer_name|nullable|string|max:120',
            'customer_first_name' => 'required_without:customer_name|nullable|string|max:120',
            'customer_email' => 'nullable|email',
            'customer_phone' => 'nullable|string|max:40',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'court_share' => 'nullable|in:full,half',
            'use_locker_room' => 'boolean',
            'notes' => 'nullable|string|max:2000',
            'amount' => 'nullable|numeric|min:0',
            'pay_online' => 'boolean',
        ]);

        $name = trim((string) ($data['customer_name'] ?? ''));
        if ($name === '') {
            $name = trim(($data['customer_last_name'] ?? '').' '.($data['customer_first_name'] ?? ''));
        }

        RentalBooking::query()->withoutGlobalScopes()->create([
            'club_id' => $club->id,
            'facility_id' => $data['facility_id'] ?? null,
            'customer_name' => $name,
            'customer_email' => $data['customer_email'] ?? null,
            'customer_phone' => $data['customer_phone'] ?? null,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'status' => 'pending',
            'amount' => (float) ($data['amount'] ?? 0),
            'meta' => [
                'court_share' => $data['court_share'] ?? 'full',
                'use_locker_room' => (bool) ($data['use_locker_room'] ?? false),
                'notes' => $data['notes'] ?? null,
                'pay_online' => (bool) ($data['pay_online'] ?? false),
            ],
        ]);

        return back()->with('success', 'Η κράτηση καταχωρήθηκε.');
    }

    /**
     * @return array{rules: list<array<string, mixed>>, notes: string, heroImageUrl: ?string}
     */
    private function normalizeRulesPayload(mixed $rules): array
    {
        if (! is_array($rules)) {
            return ['rules' => [], 'notes' => '', 'heroImageUrl' => null];
        }

        if (array_is_list($rules)) {
            return [
                'rules' => $rules,
                'notes' => '',
                'heroImageUrl' => null,
            ];
        }

        $inner = $rules['rules'] ?? [];
        if (! is_array($inner)) {
            $inner = [];
        }

        return [
            'rules' => array_values(array_filter($inner, 'is_array')),
            'notes' => (string) ($rules['notes'] ?? ''),
            'heroImageUrl' => isset($rules['heroImageUrl']) ? (string) $rules['heroImageUrl'] : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rules
     * @return array<string, array<string, mixed>>
     */
    private function rulesByFacilityId(array $rules): array
    {
        $map = [];
        foreach ($rules as $rule) {
            $facilityId = (string) ($rule['facilityId'] ?? $rule['facility_id'] ?? '');
            if ($facilityId === '') {
                continue;
            }
            $map[$facilityId] = $rule;
        }

        return $map;
    }

    /**
     * @return list<array{days: list<int>, startTime: string, endTime: string}>
     */
    private function normalizeWindows(mixed $windows): array
    {
        if (! is_array($windows) || $windows === []) {
            return [
                [
                    'days' => [1, 2, 3, 4, 5, 6, 0],
                    'startTime' => '18:00',
                    'endTime' => '22:00',
                ],
            ];
        }

        $normalized = [];
        foreach ($windows as $window) {
            if (! is_array($window)) {
                continue;
            }
            $days = $window['days'] ?? [1, 2, 3, 4, 5, 6, 0];
            if (! is_array($days)) {
                $days = [1, 2, 3, 4, 5, 6, 0];
            }
            $normalized[] = [
                'days' => array_map('intval', array_values($days)),
                'startTime' => (string) ($window['startTime'] ?? $window['start_time'] ?? '18:00'),
                'endTime' => (string) ($window['endTime'] ?? $window['end_time'] ?? '22:00'),
            ];
        }

        return $normalized !== [] ? $normalized : [
            [
                'days' => [1, 2, 3, 4, 5, 6, 0],
                'startTime' => '18:00',
                'endTime' => '22:00',
            ],
        ];
    }
}
