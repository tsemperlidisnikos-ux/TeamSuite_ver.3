<?php

namespace App\Http\Controllers\Admin;

use App\Models\ScheduleSlot;
use App\Models\Training;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ScheduleController extends ClubResourceController
{
    protected function resourceKey(): string
    {
        return 'schedule';
    }

    public function generateTrainings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'days' => ['nullable', 'integer', 'min:1', 'max:14'],
        ]);

        $from = ! empty($data['from'])
            ? Carbon::parse($data['from'])->startOfDay()
            : now()->startOfDay();
        $days = (int) ($data['days'] ?? 7);
        $clubId = $this->clubId($request);

        $slots = $this->clubQuery(ScheduleSlot::class, $request)
            ->get(['id', 'academy_class_id', 'facility_id', 'weekday', 'starts_at', 'ends_at', 'title']);

        $created = 0;

        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i);
            $dow = $day->dayOfWeek;

            foreach ($slots->where('weekday', $dow) as $slot) {
                $startTime = $this->normalizeTime((string) $slot->starts_at) ?: '18:00';
                $endTime = $this->normalizeTime((string) $slot->ends_at);

                $startsAt = Carbon::parse($day->toDateString().' '.$startTime);
                $endsAt = $endTime
                    ? Carbon::parse($day->toDateString().' '.$endTime)
                    : $startsAt->copy()->addHour()->addMinutes(30);

                $title = $slot->title ?: 'Προπόνηση';

                $exists = $this->clubQuery(Training::class, $request)
                    ->where('starts_at', $startsAt)
                    ->where(function ($q) use ($slot, $title) {
                        if ($slot->academy_class_id) {
                            $q->where('academy_class_id', $slot->academy_class_id);
                        } else {
                            $q->where('title', $title);
                        }
                    })
                    ->exists();

                if ($exists) {
                    continue;
                }

                Training::query()->withoutGlobalScopes()->create([
                    'id' => (string) Str::uuid(),
                    'club_id' => $clubId,
                    'academy_class_id' => $slot->academy_class_id,
                    'facility_id' => $slot->facility_id,
                    'title' => $title,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'status' => 'scheduled',
                ]);
                $created++;
            }
        }

        return redirect()
            ->route('admin.trainings.index')
            ->with('success', "Δημιουργήθηκαν {$created} προπονήσεις από το πρόγραμμα.");
    }

    private function normalizeTime(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^\d{1,2}:\d{2}$/', $raw)) {
            [$h, $m] = array_map('intval', explode(':', $raw));

            return sprintf('%02d:%02d', $h, $m);
        }

        try {
            return Carbon::parse($raw)->format('H:i');
        } catch (\Throwable) {
            return null;
        }
    }
}
