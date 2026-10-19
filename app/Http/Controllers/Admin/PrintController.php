<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\InteractsWithClubResource;
use App\Http\Controllers\Controller;
use App\Models\AcademyClass;
use App\Models\Athlete;
use App\Models\AthleteTransaction;
use App\Models\Sport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PrintController extends Controller
{
    use InteractsWithClubResource;

    public function index(Request $request): Response
    {
        $clubId = $this->clubId($request);

        $athletes = $this->clubQuery(Athlete::class, $request)
            ->with(['classes:id,name,sport'])
            ->where('status', '!=', 'inactive')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(800)
            ->get(['id', 'first_name', 'last_name', 'status', 'sport', 'gender', 'phone', 'guardian_phone', 'monthly_fee', 'meta']);

        $month = (int) $request->query('month', now()->month);
        $year = (int) $request->query('year', now()->year);

        $monthTx = AthleteTransaction::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->where('month', $month)
            ->where('year', $year)
            ->get(['athlete_id', 'type', 'amount']);

        $byAthlete = $monthTx->groupBy('athlete_id');

        $athleteRows = $athletes->map(function (Athlete $a) use ($byAthlete) {
            $txs = $byAthlete->get($a->id, collect());
            $charged = (float) $txs->where('type', 'charge')->sum('amount');
            $paid = (float) $txs->where('type', 'payment')->sum('amount');
            $remaining = round($charged - $paid, 2);
            $status = 'no_charge';
            $statusLabel = 'Χωρίς χρέωση';
            if ($charged > 0.009) {
                if ($remaining <= 0.009) {
                    $status = 'paid';
                    $statusLabel = 'Πληρωμένη';
                } else {
                    $status = 'unpaid';
                    $statusLabel = 'Ανεξόφλητη';
                }
            }

            $meta = $a->meta ?? [];

            return [
                'id' => $a->id,
                'first_name' => $a->first_name,
                'last_name' => $a->last_name,
                'status' => $a->status,
                'sport' => $a->sport,
                'gender' => $a->gender,
                'phone' => $a->phone,
                'guardian_phone' => $a->guardian_phone,
                'monthly_fee' => (float) $a->monthly_fee,
                'monthly_charge' => ($meta['monthly_charge'] ?? true) !== false,
                'registration_charge' => (bool) ($meta['registration_charge'] ?? false),
                'season_ticket' => (bool) ($meta['season_ticket'] ?? false),
                'custom_charge' => (bool) ($meta['custom_charge'] ?? false),
                'class_names' => $a->classes->pluck('name')->filter()->join(', '),
                'class_ids' => $a->classes->pluck('id')->values()->all(),
                'month_charged' => $charged,
                'month_paid' => $paid,
                'month_remaining' => $remaining,
                'month_pay_status' => $status,
                'month_pay_status_label' => $statusLabel,
            ];
        });

        return Inertia::render('Admin/Prints/Index', [
            'athletes' => $athleteRows,
            'classes' => $this->clubQuery(AcademyClass::class, $request)
                ->orderBy('name')
                ->get(['id', 'name', 'sport']),
            'sports' => $this->clubQuery(Sport::class, $request)
                ->orderBy('name')
                ->pluck('name')
                ->values(),
            'filters' => [
                'month' => $month,
                'year' => $year,
            ],
            'yearOptions' => [now()->year - 1, now()->year, now()->year + 1],
        ]);
    }
}
