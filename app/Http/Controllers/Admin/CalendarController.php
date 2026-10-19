<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\InteractsWithClubResource;
use App\Http\Controllers\Controller;
use App\Models\AcademyClass;
use App\Models\Facility;
use App\Models\RentalBooking;
use App\Models\SportMatch;
use App\Models\Training;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    use InteractsWithClubResource;

    public function index(Request $request): Response
    {
        $month = (int) $request->query('month', now()->month);
        $year = (int) $request->query('year', now()->year);

        if ($month < 1 || $month > 12) {
            $month = now()->month;
        }
        if ($year < 2000 || $year > 2100) {
            $year = now()->year;
        }

        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();

        $facilities = $this->clubQuery(Facility::class, $request)
            ->orderBy('name')
            ->get(['id', 'name']);

        $facilityNameById = $facilities->pluck('name', 'id');

        $trainings = $this->clubQuery(Training::class, $request)
            ->whereBetween('starts_at', [$start, $end])
            ->orderBy('starts_at')
            ->get(['id', 'title', 'starts_at', 'ends_at', 'status', 'academy_class_id', 'facility_id', 'notes']);

        $matches = $this->clubQuery(SportMatch::class, $request)
            ->whereBetween('starts_at', [$start, $end])
            ->orderBy('starts_at')
            ->get(['id', 'opponent', 'starts_at', 'venue', 'status', 'academy_class_id']);

        $rentals = $this->clubQuery(RentalBooking::class, $request)
            ->whereBetween('starts_at', [$start, $end])
            ->orderBy('starts_at')
            ->get(['id', 'customer_name', 'starts_at', 'ends_at', 'facility_id', 'status']);

        $events = [];
        foreach ($trainings as $t) {
            $day = optional($t->starts_at)->format('Y-m-d');
            if (! $day) {
                continue;
            }
            $location = $t->facility_id
                ? ($facilityNameById[$t->facility_id] ?? null)
                : ($t->notes ?: null);
            $events[$day][] = [
                'id' => 't-'.$t->id,
                'kind' => 'training',
                'label' => $t->title ?: 'Προπόνηση',
                'when' => optional($t->starts_at)->format('H:i'),
                'when_end' => optional($t->ends_at)->format('H:i'),
                'status' => $t->status,
                'class_id' => $t->academy_class_id,
                'facility_id' => $t->facility_id,
                'location' => $location,
                'venue' => $t->notes,
                'href' => route('admin.attendance.index', [
                    'date' => $day,
                    'class_id' => $t->academy_class_id,
                ], absolute: false),
            ];
        }
        foreach ($matches as $m) {
            $day = optional($m->starts_at)->format('Y-m-d');
            if (! $day) {
                continue;
            }
            $matchedFacility = $m->venue
                ? $facilities->first(fn (Facility $f) => strcasecmp($f->name, (string) $m->venue) === 0)
                : null;
            $events[$day][] = [
                'id' => 'm-'.$m->id,
                'kind' => 'match',
                'label' => $m->opponent ? 'vs '.$m->opponent : 'Αγώνας',
                'when' => optional($m->starts_at)->format('H:i'),
                'when_end' => optional($m->starts_at)?->copy()->addHours(2)->format('H:i'),
                'status' => $m->status,
                'class_id' => $m->academy_class_id,
                'facility_id' => $matchedFacility?->id,
                'location' => $m->venue,
                'venue' => $m->venue,
                'href' => route('admin.matches.index', absolute: false),
            ];
        }
        foreach ($rentals as $r) {
            $day = optional($r->starts_at)->format('Y-m-d');
            if (! $day) {
                continue;
            }
            $events[$day][] = [
                'id' => 'r-'.$r->id,
                'kind' => 'rental',
                'label' => $r->customer_name ? 'Ενοικίαση · '.$r->customer_name : 'Ενοικίαση',
                'when' => optional($r->starts_at)->format('H:i'),
                'when_end' => optional($r->ends_at)->format('H:i'),
                'status' => $r->status,
                'class_id' => null,
                'facility_id' => $r->facility_id,
                'location' => $r->facility_id ? ($facilityNameById[$r->facility_id] ?? null) : null,
                'venue' => null,
                'href' => route('admin.rentals.index', absolute: false),
            ];
        }

        $dow = (int) $start->dayOfWeek; // 0=Sun
        $startWeekday = $dow === 0 ? 6 : $dow - 1; // Monday-first

        $prev = $start->copy()->subMonth();
        $next = $start->copy()->addMonth();

        $classes = $this->clubQuery(AcademyClass::class, $request)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Admin/Calendar/Index', [
            'month' => $month,
            'year' => $year,
            'monthLabel' => $start->locale('el')->translatedFormat('F Y'),
            'daysInMonth' => $start->daysInMonth,
            'startWeekday' => $startWeekday,
            'events' => $events,
            'classes' => $classes,
            'facilities' => $facilities,
            'prev' => ['month' => $prev->month, 'year' => $prev->year],
            'next' => ['month' => $next->month, 'year' => $next->year],
            'stats' => [
                'trainings' => $trainings->count(),
                'matches' => $matches->count(),
                'rentals' => $rentals->count(),
            ],
        ]);
    }
}
