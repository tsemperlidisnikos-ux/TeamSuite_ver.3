<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\InteractsWithClubResource;
use App\Http\Controllers\Controller;
use App\Models\AcademyClass;
use App\Models\Association;
use App\Models\Athlete;
use App\Models\AthleteTransaction;
use App\Models\Attendance;
use App\Models\ClothingPackage;
use App\Models\ClubSetting;
use App\Models\RegistrationApplication;
use App\Models\SizeChart;
use App\Models\Sport;
use App\Models\Training;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PrintController extends Controller
{
    use InteractsWithClubResource;

    public function index(Request $request): Response
    {
        $clubId = $this->clubId($request);

        $dateFrom = $this->parseDateQuery($request->query('date_from'));
        $dateTo = $this->parseDateQuery($request->query('date_to'));

        $attendanceRows = Attendance::query()
            ->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->when($dateFrom, fn ($q) => $q->whereDate('attended_on', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('attended_on', '<=', $dateTo))
            ->get(['athlete_id', 'status']);

        $attendanceStats = $attendanceRows->groupBy('athlete_id')->map(function ($rows) {
            $present = $rows->where('status', 'present')->count();
            $absent = $rows->whereIn('status', ['absent', 'excused', 'late'])->count();
            $total = $rows->count();

            return [
                'present' => $present,
                'absent' => $absent,
                'total' => $total,
                'rate' => $total > 0 ? round(($present / $total) * 100, 1) : 0,
            ];
        });

        $athletes = $this->clubQuery(Athlete::class, $request)
            ->with(['classes:id,name,sport'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(800)
            ->get([
                'id',
                'first_name',
                'last_name',
                'status',
                'sport',
                'gender',
                'phone',
                'guardian_phone',
                'email',
                'monthly_fee',
                'birth_date',
                'enrolled_at',
                'health_card',
                'health_card_expires',
                'meta',
            ]);

        $month = (int) $request->query('month', now()->month);
        $year = (int) $request->query('year', now()->year);

        $monthTx = AthleteTransaction::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->where('month', $month)
            ->where('year', $year)
            ->get(['athlete_id', 'type', 'amount']);

        $byAthleteMonth = $monthTx->groupBy('athlete_id');

        $allTx = AthleteTransaction::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->get(['athlete_id', 'type', 'amount']);

        $byAthleteAll = $allTx->groupBy('athlete_id');

        $clothingPackages = $this->clubQuery(ClothingPackage::class, $request)
            ->orderBy('name')
            ->get(['id', 'name', 'legacy_id']);

        $packagesById = $clothingPackages->keyBy('id');
        $packagesByLegacy = $clothingPackages
            ->filter(fn (ClothingPackage $p) => filled($p->legacy_id))
            ->keyBy(fn (ClothingPackage $p) => (string) $p->legacy_id);

        $athleteRows = $athletes->map(function (Athlete $a) use (
            $byAthleteMonth,
            $byAthleteAll,
            $attendanceStats,
            $packagesById,
            $packagesByLegacy,
        ) {
            $monthTxs = $byAthleteMonth->get($a->id, collect());
            $charged = (float) $monthTxs->where('type', 'charge')->sum('amount');
            $paid = (float) $monthTxs->where('type', 'payment')->sum('amount');
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

            $allTxs = $byAthleteAll->get($a->id, collect());
            $balanceCharged = (float) $allTxs->where('type', 'charge')->sum('amount');
            $balancePaid = (float) $allTxs->where('type', 'payment')->sum('amount');
            $balance = round($balanceCharged - $balancePaid, 2);

            $meta = is_array($a->meta) ? $a->meta : [];
            $photoUrl = trim((string) ($meta['photo_url'] ?? $meta['photoUrl'] ?? ''));
            $clothingIds = [];
            foreach ((array) ($meta['clothing_package_ids'] ?? []) as $rawId) {
                $id = is_string($rawId) ? trim($rawId) : '';
                if ($id === '') {
                    continue;
                }
                if (Str::isUuid($id) && $packagesById->has($id)) {
                    $clothingIds[] = $id;
                } elseif ($packagesByLegacy->has($id)) {
                    $clothingIds[] = (string) $packagesByLegacy->get($id)->id;
                }
            }
            $clothingIds = array_values(array_unique($clothingIds));
            $registrationNumber = trim((string) ($meta['registration_number'] ?? $meta['registrationNumber'] ?? ''));
            $att = $attendanceStats->get($a->id, [
                'present' => 0,
                'absent' => 0,
                'total' => 0,
                'rate' => 0,
            ]);

            return [
                'id' => $a->id,
                'first_name' => $a->first_name,
                'last_name' => $a->last_name,
                'status' => $a->status,
                'sport' => $a->sport,
                'gender' => $a->gender,
                'email' => $a->email,
                'phone' => $a->phone,
                'guardian_phone' => $a->guardian_phone,
                'father_email' => trim((string) ($meta['father_email'] ?? $meta['fatherEmail'] ?? '')),
                'mother_email' => trim((string) ($meta['mother_email'] ?? $meta['motherEmail'] ?? '')),
                'monthly_fee' => (float) $a->monthly_fee,
                'birth_date' => optional($a->birth_date)->format('Y-m-d'),
                'birth_year' => $a->birth_date?->year,
                'enrolled_at' => optional($a->enrolled_at)->format('Y-m-d'),
                'monthly_charge' => ($meta['monthly_charge'] ?? true) !== false,
                'registration_charge' => (bool) ($meta['registration_charge'] ?? false),
                'season_ticket' => (bool) ($meta['season_ticket'] ?? false),
                'custom_charge' => (bool) ($meta['custom_charge'] ?? false),
                'has_photo' => $photoUrl !== '',
                'health_card' => (bool) $a->health_card,
                'health_card_expires' => optional($a->health_card_expires)->format('Y-m-d'),
                'club_name' => trim((string) ($meta['club_name'] ?? $meta['clubName'] ?? '')),
                'clothing_package_ids' => $clothingIds,
                'registration_number' => $registrationNumber,
                'has_registration_card' => $registrationNumber !== '',
                'uniform_received' => (bool) ($meta['uniform_received'] ?? false),
                'uniform_size' => trim((string) ($meta['uniform_size'] ?? $meta['uniformSize'] ?? '')),
                'has_attendance' => ($att['present'] ?? 0) > 0,
                'attendance_present_count' => (int) ($att['present'] ?? 0),
                'attendance_absent_count' => (int) ($att['absent'] ?? 0),
                'attendance_total' => (int) ($att['total'] ?? 0),
                'attendance_rate' => (float) ($att['rate'] ?? 0),
                'balance' => $balance,
                'class_names' => $a->classes->pluck('name')->filter()->join(', '),
                'class_ids' => $a->classes->pluck('id')->values()->all(),
                'month_charged' => $charged,
                'month_paid' => $paid,
                'month_remaining' => $remaining,
                'month_pay_status' => $status,
                'month_pay_status_label' => $statusLabel,
            ];
        });

        $sizeChart = $this->clubQuery(SizeChart::class, $request)->first();
        $chart = is_array($sizeChart?->chart) ? $sizeChart->chart : [];
        $sizes = collect([
            ...($chart['kids'] ?? []),
            ...($chart['men'] ?? []),
            ...($chart['women'] ?? []),
            ...(is_array($chart) && array_is_list($chart) ? $chart : []),
        ])->map(fn ($s) => trim((string) $s))->filter()->unique()->values();

        $clubSettings = ClubSetting::query()->where('club_id', $clubId)->first();
        $profile = is_array($clubSettings?->profile) ? $clubSettings->profile : [];
        $customChargeLabel = trim((string) ($profile['custom_charge_label'] ?? ''));

        $trainings = $this->clubQuery(Training::class, $request)
            ->orderByDesc('starts_at')
            ->limit(400)
            ->get(['id', 'academy_class_id', 'starts_at', 'ends_at', 'title', 'status', 'meta'])
            ->map(function (Training $t) {
                $meta = is_array($t->meta) ? $t->meta : [];

                return [
                    'id' => $t->id,
                    'class_id' => $t->academy_class_id,
                    'date' => optional($t->starts_at)->format('Y-m-d'),
                    'start_time' => optional($t->starts_at)->format('H:i'),
                    'location' => trim((string) ($meta['location'] ?? $meta['venue'] ?? '')),
                    'title' => $t->title,
                    'status' => $t->status,
                ];
            })
            ->values();

        $registrationApplications = $this->clubQuery(RegistrationApplication::class, $request)
            ->orderByDesc('created_at')
            ->limit(500)
            ->get([
                'id',
                'type',
                'status',
                'first_name',
                'last_name',
                'email',
                'phone',
                'form_snapshot',
                'created_at',
            ])
            ->map(function (RegistrationApplication $app) {
                $snapshot = is_array($app->form_snapshot) ? $app->form_snapshot : [];

                return [
                    'id' => $app->id,
                    'type' => $app->type,
                    'status' => $app->status,
                    'first_name' => $app->first_name,
                    'last_name' => $app->last_name,
                    'email' => $app->email,
                    'phone' => $app->phone,
                    'guardian' => trim((string) ($snapshot['guardian_name'] ?? $snapshot['guardianName'] ?? '')),
                    'sport' => trim((string) ($snapshot['sport'] ?? '')),
                    'class_id' => $snapshot['class_id'] ?? $snapshot['classId'] ?? null,
                    'created_at' => optional($app->created_at)->format('Y-m-d'),
                ];
            })
            ->values();

        $periodTransactions = AthleteTransaction::query()
            ->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->when($dateFrom, function ($q) use ($dateFrom) {
                $q->where(function ($inner) use ($dateFrom) {
                    $inner->whereDate('created_at', '>=', $dateFrom)
                        ->orWhere(function ($ym) use ($dateFrom) {
                            $from = Carbon::parse($dateFrom);
                            $ym->where('year', '>', $from->year)
                                ->orWhere(function ($sameYear) use ($from) {
                                    $sameYear->where('year', $from->year)
                                        ->where('month', '>=', $from->month);
                                });
                        });
                });
            })
            ->when($dateTo, function ($q) use ($dateTo) {
                $q->where(function ($inner) use ($dateTo) {
                    $inner->whereDate('created_at', '<=', $dateTo)
                        ->orWhere(function ($ym) use ($dateTo) {
                            $to = Carbon::parse($dateTo);
                            $ym->where('year', '<', $to->year)
                                ->orWhere(function ($sameYear) use ($to) {
                                    $sameYear->where('year', $to->year)
                                        ->where('month', '<=', $to->month);
                                });
                        });
                });
            })
            ->orderByDesc('created_at')
            ->limit(1000)
            ->get(['id', 'athlete_id', 'type', 'amount', 'month', 'year', 'payment_method', 'receipt_number', 'created_at'])
            ->map(fn (AthleteTransaction $tx) => [
                'id' => $tx->id,
                'athlete_id' => $tx->athlete_id,
                'type' => $tx->type,
                'amount' => (float) $tx->amount,
                'month' => $tx->month,
                'year' => $tx->year,
                'payment_method' => $tx->payment_method,
                'receipt_number' => trim((string) ($tx->receipt_number ?? '')),
                'created_at' => optional($tx->created_at)->format('Y-m-d'),
            ])
            ->values();

        return Inertia::render('Admin/Prints/Index', [
            'athletes' => $athleteRows,
            'classes' => $this->clubQuery(AcademyClass::class, $request)
                ->orderBy('name')
                ->get(['id', 'name', 'sport']),
            'sports' => $this->clubQuery(Sport::class, $request)
                ->orderBy('name')
                ->pluck('name')
                ->values(),
            'associations' => $this->clubQuery(Association::class, $request)
                ->orderBy('name')
                ->pluck('name')
                ->filter()
                ->values(),
            'clothingPackages' => $clothingPackages->map(fn (ClothingPackage $p) => [
                'id' => $p->id,
                'name' => $p->name,
            ])->values(),
            'sizeOptions' => $sizes,
            'customChargeLabel' => $customChargeLabel,
            'trainings' => $trainings,
            'registrationApplications' => $registrationApplications,
            'periodTransactions' => $periodTransactions,
            'filters' => [
                'month' => $month,
                'year' => $year,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'yearOptions' => [now()->year - 1, now()->year, now()->year + 1],
        ]);
    }

    private function parseDateQuery(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
