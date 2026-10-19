<?php

namespace App\Http\Controllers\Admin;

use App\Models\Facility;
use App\Models\RentalBooking;
use App\Models\RentalSetting;
use App\Models\Revenue;
use App\Models\ScheduleSlot;
use App\Models\SportMatch;
use App\Models\Training;
use App\Services\Audit\ClubActivityLogger;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RentalController extends ClubResourceController
{
    protected function resourceKey(): string
    {
        return 'rentals';
    }

    public function index(Request $request): Response
    {
        $config = $this->config();
        $columnKeys = array_column($config['columns'], 'key');
        $fieldKeys = array_values(array_unique(array_merge(
            array_column($config['fields'], 'name'),
            ['facility_id', 'meta', 'starts_at', 'ends_at', 'customer_name', 'customer_email', 'customer_phone', 'amount', 'status'],
        )));
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $paginator = $this->clubResourceIndex(
            $request,
            $config['model'],
            $columnKeys,
            30,
            $fieldKeys,
            $q,
            $status,
            $config['search'] ?? [],
            $config['status_field'] ?? null,
        );

        $clubId = $this->clubId($request);
        $facilityNames = Facility::query()
            ->where('club_id', $clubId)
            ->pluck('name', 'id');

        $facilities = Facility::query()
            ->where('club_id', $clubId)
            ->orderBy('name')
            ->get(['id', 'name', 'is_rentable', 'meta'])
            ->map(function (Facility $facility) {
                $meta = is_array($facility->meta) ? $facility->meta : [];
                $sports = $meta['sports'] ?? $meta['sport'] ?? [];
                if (is_string($sports)) {
                    $sports = array_values(array_filter(array_map('trim', explode(',', $sports))));
                }
                if (! is_array($sports)) {
                    $sports = [];
                }

                return [
                    'id' => $facility->id,
                    'name' => $facility->name,
                    'is_rentable' => (bool) $facility->is_rentable,
                    'photoUrl' => $meta['photoUrl'] ?? $meta['photo_url'] ?? null,
                    'sports' => array_values(array_map('strval', $sports)),
                ];
            })
            ->values();

        $rentalSettings = RentalSetting::query()->where('club_id', $clubId)->first();
        $rulesPayload = $this->normalizeRulesPayload($rentalSettings?->rules);

        $bookings = RentalBooking::query()
            ->where('club_id', $clubId)
            ->orderBy('starts_at')
            ->get();

        $bookingRows = $bookings
            ->map(fn (RentalBooking $booking) => $this->serializeBooking($booking, $facilityNames))
            ->values();

        $uncollected = $bookingRows
            ->filter(fn (array $row) => $this->isUncollectedRow($row))
            ->values();

        $upcomingBookings = $bookingRows
            ->filter(function (array $row) {
                if ($row['status'] === 'cancelled') {
                    return false;
                }

                return $row['date'] >= now()->toDateString() || $this->isUncollectedRow($row);
            })
            ->values();

        $pastBookings = $bookingRows
            ->reject(fn (array $row) => $upcomingBookings->contains('id', $row['id']))
            ->sortByDesc(fn (array $row) => ($row['date'] ?? '').' '.($row['startTime'] ?? ''))
            ->values();

        $today = now()->startOfDay();
        $horizon = now()->addDays(60)->endOfDay();

        $occupancy = [
            'schedule' => ScheduleSlot::query()
                ->where('club_id', $clubId)
                ->get()
                ->map(function (ScheduleSlot $slot) use ($facilityNames) {
                    $meta = is_array($slot->meta) ? $slot->meta : [];

                    return [
                        'facilityId' => $slot->facility_id,
                        'location' => $facilityNames[$slot->facility_id] ?? ($meta['location'] ?? null),
                        'dayOfWeek' => (int) ($slot->weekday ?? -1),
                        'startTime' => $this->timeToHm($slot->starts_at),
                        'endTime' => $this->timeToHm($slot->ends_at),
                    ];
                })
                ->values(),
            'trainings' => Training::query()
                ->where('club_id', $clubId)
                ->whereBetween('starts_at', [$today, $horizon])
                ->where('status', '!=', 'cancelled')
                ->get()
                ->map(function (Training $training) use ($facilityNames) {
                    $meta = is_array($training->meta) ? $training->meta : [];

                    return [
                        'facilityId' => $training->facility_id,
                        'location' => $facilityNames[$training->facility_id]
                            ?? ($meta['location'] ?? $training->title),
                        'date' => optional($training->starts_at)->toDateString(),
                        'startTime' => optional($training->starts_at)?->format('H:i'),
                        'endTime' => optional($training->ends_at)?->format('H:i'),
                    ];
                })
                ->values(),
            'matches' => SportMatch::query()
                ->where('club_id', $clubId)
                ->whereBetween('starts_at', [$today, $horizon])
                ->get()
                ->map(function (SportMatch $match) {
                    $meta = is_array($match->meta) ? $match->meta : [];

                    return [
                        'facilityId' => $meta['facility_id'] ?? null,
                        'location' => $meta['location'] ?? $match->venue,
                        'date' => optional($match->starts_at)->toDateString(),
                        'time' => optional($match->starts_at)?->format('H:i'),
                        'homeAway' => $match->home_away,
                        'status' => $match->status,
                    ];
                })
                ->values(),
            'rentalBookings' => $bookingRows,
            'rentalRules' => $rulesPayload['rules'],
        ];

        return Inertia::render($config['page'], [
            'title' => $config['title'],
            'subtitle' => $config['subtitle'] ?? null,
            'resourceKey' => $this->resourceKey(),
            'routes' => [
                'store' => "{$config['route']}.store",
                'update' => "{$config['route']}.update",
                'destroy' => "{$config['route']}.destroy",
                'index' => "{$config['route']}.index",
                'saveAvailability' => 'admin.rentals.save-availability',
                'book' => 'admin.rentals.book',
                'collect' => 'admin.rentals.collect',
                'cancel' => 'admin.rentals.cancel',
            ],
            'columns' => $config['columns'],
            'fields' => $config['fields'],
            'items' => $paginator,
            'filters' => [
                'q' => $q,
                'status' => $status,
                'tab' => (string) $request->query('tab', ''),
            ],
            'filterOptions' => $config['filter_options'] ?? null,
            'filterLabel' => $config['filter_label'] ?? 'Κατάσταση',
            'toolbar' => $config['toolbar'] ?? [],
            'facilities' => $facilities,
            'rentalRules' => $rulesPayload['rules'],
            'publicRentEnabled' => (bool) ($rentalSettings?->public_enabled ?? false),
            'rentalNotes' => $rulesPayload['notes'],
            'heroImageUrl' => $rulesPayload['heroImageUrl'],
            'uncollectedCount' => $uncollected->count(),
            'uncollected' => $uncollected,
            'upcomingBookings' => $upcomingBookings,
            'pastBookings' => $pastBookings,
            'canDeleteBookings' => (bool) $request->user()?->is_platform_admin,
            'occupancy' => $occupancy,
        ]);
    }

    public function book(Request $request): RedirectResponse
    {
        $clubId = $this->clubId($request);

        $data = $request->validate([
            'facility_id' => ['required', 'uuid', Rule::exists('facilities', 'id')->where('club_id', $clubId)],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'string', 'max:8'],
            'end_time' => ['required', 'string', 'max:8'],
            'court_share' => ['required', Rule::in(['full', 'half'])],
            'use_locker_room' => ['sometimes', 'boolean'],
            'customer_last_name' => ['required', 'string', 'max:80'],
            'customer_first_name' => ['required', 'string', 'max:80'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'customer_email' => ['nullable', 'email', 'max:190'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'special_discount' => ['nullable', 'numeric', 'min:0'],
            'paid_now' => ['sometimes', 'boolean'],
            'payment_method' => ['nullable', Rule::in(['cash', 'card', 'transfer'])],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'recurring' => ['sometimes', 'boolean'],
            'until_date' => ['nullable', 'date', 'after_or_equal:date'],
            'rec_weekdays' => ['nullable', 'array'],
            'rec_weekdays.*' => ['integer', 'between:0,6'],
        ]);

        $facility = Facility::query()->where('club_id', $clubId)->findOrFail($data['facility_id']);
        $paidNow = (bool) ($data['paid_now'] ?? false);
        $paymentMethod = $paidNow ? ($data['payment_method'] ?? 'cash') : null;
        $discount = max(0, (float) ($data['special_discount'] ?? 0));
        $amount = max(0, (float) ($data['amount'] ?? 0));
        $customerName = trim($data['customer_last_name'].' '.$data['customer_first_name']);

        $dates = [$data['date']];
        if (! empty($data['recurring']) && ! empty($data['until_date'])) {
            $weekdays = array_values(array_unique(array_map('intval', $data['rec_weekdays'] ?? [])));
            if ($weekdays === []) {
                $weekdays = [Carbon::parse($data['date'])->dayOfWeek];
            }
            $cursor = Carbon::parse($data['date'])->addDay();
            $until = Carbon::parse($data['until_date']);
            while ($cursor->lte($until)) {
                if (in_array($cursor->dayOfWeek, $weekdays, true)) {
                    $dates[] = $cursor->toDateString();
                }
                $cursor->addDay();
            }
        }

        $created = 0;
        foreach ($dates as $date) {
            $startsAt = Carbon::parse($date.' '.$data['start_time']);
            $endsAt = Carbon::parse($date.' '.$data['end_time']);
            if ($endsAt->lte($startsAt)) {
                $endsAt->addDay();
            }

            $meta = [
                'courtShare' => $data['court_share'],
                'useLockerRoom' => (bool) ($data['use_locker_room'] ?? false),
                'notes' => $data['notes'] ?? null,
                'specialDiscount' => $discount,
                'source' => 'secretariat',
                'facilityName' => $facility->name,
                'customerFirstName' => $data['customer_first_name'],
                'customerLastName' => $data['customer_last_name'],
                'paymentCollected' => $paidNow,
                'paymentMethod' => $paymentMethod,
                'paidOn' => $paidNow ? now()->toDateString() : null,
                'paidAt' => $paidNow ? now()->toIso8601String() : null,
            ];

            $booking = RentalBooking::query()->create([
                'club_id' => $clubId,
                'facility_id' => $facility->id,
                'customer_name' => $customerName,
                'customer_email' => $data['customer_email'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'amount' => $amount,
                'status' => $paidNow ? 'confirmed' : 'pending',
                'meta' => $meta,
            ]);

            if ($paidNow && $amount > 0) {
                $this->upsertRentalRevenue($request, $booking, $facility->name, $paymentMethod ?? 'cash');
            }

            app(ClubActivityLogger::class)->record(
                $clubId,
                'rental.book',
                'Δημιούργησε κράτηση ενοικίασης',
                sprintf(
                    '%s · %s %s–%s · %s · %s € · %s',
                    $facility->name,
                    $date,
                    $data['start_time'],
                    $data['end_time'],
                    $customerName,
                    number_format((float) $amount, 2, ',', '.'),
                    $paidNow ? 'εισπράχθηκε' : 'εκκρεμής είσπραξη'
                ),
                'rental_booking',
                $booking->id,
            );

            $created++;
        }

        return redirect()
            ->route('admin.rentals.index', ['tab' => 'bookings'])
            ->with('success', $created > 1
                ? "Καταχωρήθηκαν {$created} κρατήσεις."
                : 'Η κράτηση καταχωρήθηκε.');
    }

    public function collect(Request $request, string $id): RedirectResponse
    {
        $clubId = $this->clubId($request);
        $data = $request->validate([
            'payment_method' => ['required', Rule::in(['cash', 'card', 'transfer'])],
        ]);

        $booking = RentalBooking::query()
            ->where('club_id', $clubId)
            ->findOrFail($id);

        abort_if($booking->status === 'cancelled', 422, 'Η κράτηση είναι ακυρωμένη.');
        abort_if((float) $booking->amount <= 0, 422, 'Δεν υπάρχει ποσό προς είσπραξη.');

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['paymentCollected'] = true;
        $meta['paymentMethod'] = $data['payment_method'];
        $meta['paidOn'] = now()->toDateString();
        $meta['paidAt'] = now()->toIso8601String();

        $booking->forceFill([
            'status' => 'confirmed',
            'meta' => $meta,
        ])->save();

        $facilityName = $meta['facilityName']
            ?? Facility::query()->where('club_id', $clubId)->find($booking->facility_id)?->name
            ?? 'Γήπεδο';

        $this->upsertRentalRevenue($request, $booking, $facilityName, $data['payment_method']);

        $methodLabel = match ($data['payment_method']) {
            'card' => 'POS',
            'transfer' => 'τραπεζική κατάθεση',
            default => 'μετρητά',
        };
        app(ClubActivityLogger::class)->record(
            $clubId,
            'rental.collect',
            'Εισέπραξε κράτηση ενοικίασης',
            sprintf(
                '%s · %s · %s · %s € · %s',
                $facilityName,
                optional($booking->starts_at)?->format('d/m/Y H:i'),
                $booking->customer_name,
                number_format((float) $booking->amount, 2, ',', '.'),
                $methodLabel
            ),
            'rental_booking',
            $booking->id,
        );

        return redirect()
            ->route('admin.rentals.index', ['tab' => 'bookings'])
            ->with('success', 'Η είσπραξη καταχωρήθηκε στα έσοδα.');
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $clubId = $this->clubId($request);
        $booking = RentalBooking::query()
            ->where('club_id', $clubId)
            ->findOrFail($id);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['paymentCollected'] = false;
        $booking->forceFill([
            'status' => 'cancelled',
            'meta' => $meta,
        ])->save();

        $this->deleteLinkedRentalRevenue($clubId, $booking);

        app(ClubActivityLogger::class)->record(
            $clubId,
            'rental.cancel',
            'Ακύρωσε κράτηση ενοικίασης',
            sprintf(
                '%s · %s · %s · %s €',
                data_get($booking->meta, 'facilityName', 'Γήπεδο'),
                optional($booking->starts_at)?->format('d/m/Y H:i'),
                $booking->customer_name,
                number_format((float) $booking->amount, 2, ',', '.')
            ),
            'rental_booking',
            $booking->id,
        );

        return redirect()
            ->route('admin.rentals.index', ['tab' => 'bookings'])
            ->with('success', 'Η κράτηση ακυρώθηκε.');
    }

    /**
     * Permanent remove from booking lists — platform admin only.
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        abort_unless($request->user()?->is_platform_admin, 403, 'Μόνο Platform Admin μπορεί να διαγράψει κράτηση.');

        $clubId = $this->clubId($request);
        $booking = RentalBooking::query()
            ->where('club_id', $clubId)
            ->findOrFail($id);

        $description = sprintf(
            '%s · %s · %s · %s €',
            data_get($booking->meta, 'facilityName', 'Γήπεδο'),
            optional($booking->starts_at)?->format('d/m/Y H:i'),
            $booking->customer_name,
            number_format((float) $booking->amount, 2, ',', '.')
        );

        $this->deleteLinkedRentalRevenue($clubId, $booking);
        $booking->delete();

        app(ClubActivityLogger::class)->record(
            $clubId,
            'rental.delete',
            'Διέγραψε κράτηση ενοικίασης',
            $description,
            'rental_booking',
            $id,
        );

        return redirect()
            ->route('admin.rentals.index', ['tab' => 'bookings'])
            ->with('success', 'Η κράτηση διαγράφηκε.');
    }

    private function deleteLinkedRentalRevenue(string $clubId, RentalBooking $booking): void
    {
        Revenue::query()
            ->where('club_id', $clubId)
            ->where(function ($q) use ($booking) {
                $q->where('legacy_id', 'rev_rent_'.$booking->id)
                    ->orWhere('meta->linkedRentalBookingId', $booking->id);
            })
            ->delete();
    }

    public function saveAvailability(Request $request): RedirectResponse
    {
        $clubId = $this->clubId($request);

        $data = $request->validate([
            'public_enabled' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'hero_image_url' => ['nullable', 'string', 'max:500000'],
            'rules' => ['required', 'array'],
            'rules.*.facilityId' => ['required', 'uuid'],
            'rules.*.enabled' => ['sometimes', 'boolean'],
            'rules.*.slotMinutes' => ['nullable', 'integer', 'in:60,90,120'],
            'rules.*.hourlyRate' => ['nullable', 'numeric', 'min:0'],
            'rules.*.hourlyRateFull' => ['nullable', 'numeric', 'min:0'],
            'rules.*.hourlyRateHalf' => ['nullable', 'numeric', 'min:0'],
            'rules.*.lockerRoomAvailable' => ['sometimes', 'boolean'],
            'rules.*.lockerRoomFee' => ['nullable', 'numeric', 'min:0'],
            'rules.*.windows' => ['nullable', 'array'],
            'rules.*.windows.*.days' => ['nullable', 'array'],
            'rules.*.windows.*.days.*' => ['integer', 'between:0,6'],
            'rules.*.windows.*.startTime' => ['nullable', 'string', 'max:8'],
            'rules.*.windows.*.endTime' => ['nullable', 'string', 'max:8'],
            'facilities' => ['nullable', 'array'],
            'facilities.*.id' => ['required', 'uuid'],
            'facilities.*.photoUrl' => ['nullable', 'string', 'max:500000'],
            'facilities.*.is_rentable' => ['sometimes', 'boolean'],
            'facilities.*.sports' => ['nullable', 'array'],
            'facilities.*.sports.*' => ['string', 'max:80'],
        ]);

        $facilityIds = Facility::query()
            ->where('club_id', $clubId)
            ->pluck('id')
            ->all();
        $allowed = array_flip($facilityIds);

        $normalizedRules = [];
        foreach ($data['rules'] as $rule) {
            $facilityId = (string) ($rule['facilityId'] ?? '');
            if ($facilityId === '' || ! isset($allowed[$facilityId])) {
                continue;
            }

            $full = (float) ($rule['hourlyRateFull'] ?? $rule['hourlyRate'] ?? 0);
            $half = (float) ($rule['hourlyRateHalf'] ?? 0);
            $windows = [];
            foreach ($rule['windows'] ?? [] as $window) {
                if (! is_array($window)) {
                    continue;
                }
                $days = array_values(array_unique(array_map(
                    'intval',
                    array_values($window['days'] ?? [1, 2, 3, 4, 5, 6, 0]),
                )));
                $windows[] = [
                    'days' => $days !== [] ? $days : [1, 2, 3, 4, 5, 6, 0],
                    'startTime' => (string) ($window['startTime'] ?? '18:00'),
                    'endTime' => (string) ($window['endTime'] ?? '22:00'),
                ];
            }
            if ($windows === []) {
                $windows = [[
                    'days' => [1, 2, 3, 4, 5, 6, 0],
                    'startTime' => '18:00',
                    'endTime' => '22:00',
                ]];
            }

            $normalizedRules[] = [
                'facilityId' => $facilityId,
                'enabled' => (bool) ($rule['enabled'] ?? false),
                'slotMinutes' => (int) ($rule['slotMinutes'] ?? 60) ?: 60,
                'windows' => $windows,
                'hourlyRate' => $full,
                'hourlyRateFull' => $full,
                'hourlyRateHalf' => $half >= 0 ? $half : 0,
                'lockerRoomAvailable' => (bool) ($rule['lockerRoomAvailable'] ?? false),
                'lockerRoomFee' => max(0, (float) ($rule['lockerRoomFee'] ?? 0)),
            ];
        }

        $existing = RentalSetting::query()->where('club_id', $clubId)->first();
        $existingPayload = $this->normalizeRulesPayload($existing?->rules);

        $payload = [
            'rules' => $normalizedRules,
            'notes' => array_key_exists('notes', $data)
                ? (string) ($data['notes'] ?? '')
                : $existingPayload['notes'],
            'heroImageUrl' => array_key_exists('hero_image_url', $data)
                ? ($data['hero_image_url'] !== null && $data['hero_image_url'] !== ''
                    ? (string) $data['hero_image_url']
                    : null)
                : $existingPayload['heroImageUrl'],
        ];

        RentalSetting::query()->updateOrCreate(
            ['club_id' => $clubId],
            [
                'public_enabled' => array_key_exists('public_enabled', $data)
                    ? (bool) $data['public_enabled']
                    : (bool) ($existing?->public_enabled ?? false),
                'rules' => $payload,
            ],
        );

        foreach ($data['facilities'] ?? [] as $facilityPayload) {
            $facilityId = (string) ($facilityPayload['id'] ?? '');
            if ($facilityId === '' || ! isset($allowed[$facilityId])) {
                continue;
            }

            $facility = Facility::query()->where('club_id', $clubId)->find($facilityId);
            if (! $facility) {
                continue;
            }

            $meta = is_array($facility->meta) ? $facility->meta : [];
            if (array_key_exists('photoUrl', $facilityPayload)) {
                $photo = $facilityPayload['photoUrl'];
                $meta['photoUrl'] = $photo !== null && $photo !== '' ? (string) $photo : null;
                unset($meta['photo_url']);
            }
            if (array_key_exists('sports', $facilityPayload) && is_array($facilityPayload['sports'])) {
                $meta['sports'] = array_values(array_map('strval', $facilityPayload['sports']));
            }

            $updates = ['meta' => $meta];
            if (array_key_exists('is_rentable', $facilityPayload)) {
                $updates['is_rentable'] = (bool) $facilityPayload['is_rentable'];
            } else {
                $matchingRule = collect($normalizedRules)->firstWhere('facilityId', $facilityId);
                if ($matchingRule) {
                    $updates['is_rentable'] = (bool) $matchingRule['enabled'];
                }
            }

            $facility->fill($updates)->save();
        }

        return redirect()
            ->route('admin.rentals.index')
            ->with('success', 'Η διαθεσιμότητα αποθηκεύτηκε.');
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
            return ['rules' => $rules, 'notes' => '', 'heroImageUrl' => null];
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
     * @param  array<string, mixed>  $row
     */
    private function isUncollectedRow(array $row): bool
    {
        if (($row['status'] ?? '') === 'cancelled') {
            return false;
        }

        if ((float) ($row['amount'] ?? 0) <= 0) {
            return false;
        }

        if (($row['status'] ?? '') === 'pending_payment') {
            return true;
        }

        if (($row['paymentCollected'] ?? null) === false) {
            return true;
        }

        if (($row['paymentCollected'] ?? null) === true) {
            return false;
        }

        if (! empty($row['paidOn']) || ! empty($row['paidAt'])) {
            return false;
        }

        return ($row['status'] ?? '') !== 'confirmed';
    }

    /**
     * @param  Collection<string, string|null>  $facilityNames
     * @return array<string, mixed>
     */
    private function serializeBooking(RentalBooking $booking, $facilityNames): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $starts = $booking->starts_at;
        $ends = $booking->ends_at;

        return [
            'id' => $booking->id,
            'facilityId' => $booking->facility_id,
            'facilityName' => $meta['facilityName']
                ?? $facilityNames[$booking->facility_id]
                ?? '—',
            'date' => optional($starts)->toDateString(),
            'startTime' => optional($starts)?->format('H:i'),
            'endTime' => optional($ends)?->format('H:i'),
            'customerName' => $booking->customer_name,
            'customerPhone' => $booking->customer_phone,
            'customerEmail' => $booking->customer_email,
            'amount' => (float) $booking->amount,
            'status' => $booking->status,
            'courtShare' => $meta['courtShare'] ?? 'full',
            'useLockerRoom' => (bool) ($meta['useLockerRoom'] ?? false),
            'source' => $meta['source'] ?? 'secretariat',
            'notes' => $meta['notes'] ?? null,
            'specialDiscount' => (float) ($meta['specialDiscount'] ?? 0),
            'paymentCollected' => array_key_exists('paymentCollected', $meta)
                ? (bool) $meta['paymentCollected']
                : null,
            'paymentMethod' => $meta['paymentMethod'] ?? null,
            'paidOn' => $meta['paidOn'] ?? null,
            'paidAt' => $meta['paidAt'] ?? null,
        ];
    }

    private function timeToHm(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->format('H:i');
        }

        $raw = (string) $value;
        if (preg_match('/^(\d{1,2}:\d{2})/', $raw, $m)) {
            return strlen($m[1]) === 4 ? '0'.$m[1] : $m[1];
        }

        try {
            return Carbon::parse($raw)->format('H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    private function upsertRentalRevenue(
        Request $request,
        RentalBooking $booking,
        string $facilityName,
        string $paymentMethod,
    ): void {
        $legacyId = 'rev_rent_'.$booking->id;
        $starts = $booking->starts_at;
        $ends = $booking->ends_at;
        $timeLabel = trim(
            (optional($starts)?->format('H:i') ?? '').
            '–'.
            (optional($ends)?->format('H:i') ?? ''),
            '–',
        );

        Revenue::query()->updateOrCreate(
            [
                'club_id' => $booking->club_id,
                'legacy_id' => $legacyId,
            ],
            [
                'created_by' => $request->user()?->id,
                'amount' => $booking->amount,
                'category' => 'ΕΝΟΙΚΙΑΣΗ ΓΗΠΕΔΟΥ',
                'occurred_on' => now()->toDateString(),
                'description' => 'Ενοικίαση '.$facilityName.($timeLabel !== '' ? ' ('.$timeLabel.')' : ''),
                'meta' => [
                    'subcategory' => 'ΕΝΟΙΚΙΑΣΗ ΓΗΠΕΔΟΥ',
                    'payment_method' => $paymentMethod,
                    'paymentMethod' => $paymentMethod,
                    'linkedRentalBookingId' => $booking->id,
                    'notes' => trim(($booking->customer_name ?? '').' · '.($booking->customer_phone ?? '')),
                ],
            ],
        );
    }
}
