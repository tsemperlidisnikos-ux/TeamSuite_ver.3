<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\InteractsWithClubResource;
use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\AthleteTransaction;
use App\Models\Attendance;
use App\Models\ClubMember;
use App\Models\ClubSeason;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    use InteractsWithClubResource;

    public const MEMBERS_SPORT_FILTER = '__members__';

    /** Season months Aug → Jul. */
    private const SEASON_MONTHS = [8, 9, 10, 11, 12, 1, 2, 3, 4, 5, 6, 7];

    public function index(Request $request): Response
    {
        $clubId = $this->clubId($request);
        $q = trim((string) $request->query('q', ''));
        $sport = trim((string) $request->query('sport', ''));
        $athleteId = (string) $request->query('athlete_id', '');
        $memberId = (string) $request->query('member_id', '');
        $seasonStart = (int) $request->query(
            'season_start',
            $this->seasonStartFromPeriod((int) now()->month, (int) now()->year),
        );

        $showingMembers = $sport === self::MEMBERS_SPORT_FILTER;
        $seasonPeriods = $this->seasonPeriods($seasonStart);

        $sports = $this->clubQuery(Athlete::class, $request)
            ->where('status', '!=', 'inactive')
            ->whereNotNull('sport')
            ->where('sport', '!=', '')
            ->distinct()
            ->orderBy('sport')
            ->pluck('sport')
            ->values();

        $athleteRows = collect();
        $memberRows = collect();

        if ($showingMembers) {
            $members = $this->clubQuery(ClubMember::class, $request)
                ->where('status', '!=', 'inactive')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get([
                    'id', 'first_name', 'last_name', 'email', 'phone', 'member_number', 'status', 'meta',
                ]);

            if ($q !== '') {
                $needle = mb_strtolower($q);
                $members = $members->filter(function (ClubMember $member) use ($needle) {
                    $city = (string) data_get($member->meta ?? [], 'city', '');
                    $hay = mb_strtolower(trim(
                        $member->last_name.' '.$member->first_name.' '.($member->email ?? '').' '.
                        ($member->phone ?? '').' '.$city.' '.($member->member_number ?? '')
                    ));

                    return str_contains($hay, $needle);
                })->values();
            }

            $memberIds = $members->pluck('id');
            $seasonBalances = $this->seasonBalancesByParty($clubId, $memberIds, $seasonPeriods, 'club_member_id');

            $memberRows = $members->map(function (ClubMember $member) use ($seasonBalances) {
                return [
                    'id' => $member->id,
                    'first_name' => $member->first_name,
                    'last_name' => $member->last_name,
                    'email' => $member->email,
                    'phone' => $member->phone,
                    'city' => data_get($member->meta ?? [], 'city', '') ?: '',
                    'member_number' => $member->member_number,
                    'season_balance' => round((float) ($seasonBalances[$member->id] ?? 0), 2),
                ];
            })->values();
        } else {
            $athletes = $this->clubQuery(Athlete::class, $request)
                ->where('status', '!=', 'inactive')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get([
                    'id', 'first_name', 'last_name', 'birth_date', 'sport', 'status',
                    'monthly_fee', 'email', 'phone', 'amka_encrypted', 'meta',
                ]);

            if ($q !== '') {
                $needle = mb_strtolower($q);
                $athletes = $athletes->filter(function (Athlete $athlete) use ($needle) {
                    $registration = (string) ($athlete->meta['registration_number'] ?? '');
                    $city = (string) ($athlete->meta['city'] ?? '');
                    $hay = mb_strtolower(trim(
                        $athlete->last_name.' '.$athlete->first_name.' '.($athlete->email ?? '').' '.
                        $registration.' '.($athlete->meta['father_first_name'] ?? '').' '.$city
                    ));

                    return str_contains($hay, $needle);
                })->values();
            }

            if ($sport !== '') {
                $athletes = $athletes->filter(function (Athlete $athlete) use ($sport) {
                    $own = trim((string) ($athlete->sport ?? ''));
                    if ($own === $sport) {
                        return true;
                    }
                    $sports = $athlete->meta['sports'] ?? [];
                    if (is_array($sports) && in_array($sport, $sports, true)) {
                        return true;
                    }

                    return false;
                })->values();
            }

            $athleteIds = $athletes->pluck('id');
            $seasonBalances = $this->seasonBalancesByParty($clubId, $athleteIds, $seasonPeriods, 'athlete_id');

            $athleteRows = $athletes->map(function (Athlete $athlete) use ($seasonBalances) {
                $registration = trim((string) ($athlete->meta['registration_number'] ?? ''));
                $fatherName = trim((string) (
                    $athlete->meta['father_first_name']
                    ?? $athlete->meta['father_name']
                    ?? ''
                ));

                return [
                    'id' => $athlete->id,
                    'first_name' => $athlete->first_name,
                    'last_name' => $athlete->last_name,
                    'birth_date' => optional($athlete->birth_date)?->toDateString(),
                    'sport' => $athlete->sport,
                    'amka' => filled($athlete->amka_encrypted) ? '********' : '',
                    // Αρ. Δελτίου: federation registration; fall back to empty dash on UI
                    'registration_number' => $registration,
                    'father_first_name' => $fatherName,
                    'father_name' => $fatherName,
                    'city' => $athlete->meta['city'] ?? '',
                    'season_balance' => round((float) ($seasonBalances[$athlete->id] ?? 0), 2),
                ];
            })->values();
        }

        $selected = null;
        $transactions = [];
        $monthRows = [];
        $balance = ['charges' => 0.0, 'payments' => 0.0, 'owed' => 0.0];

        if ($showingMembers && $memberId !== '') {
            $member = $this->clubQuery(ClubMember::class, $request)->find($memberId);
            if ($member) {
                [$transactions, $balance, $monthRows] = $this->partySeasonDetail(
                    $clubId,
                    $member->id,
                    'club_member_id',
                    $seasonStart,
                    $seasonPeriods,
                    includeAttendance: false,
                );
                $selected = [
                    'id' => $member->id,
                    'party_type' => 'member',
                    'first_name' => $member->first_name,
                    'last_name' => $member->last_name,
                    'email' => $member->email,
                    'phone' => $member->phone,
                    'city' => data_get($member->meta ?? [], 'city', '') ?: '',
                    'status' => $member->status,
                ];
            }
        } elseif (! $showingMembers && $athleteId !== '') {
            $athlete = $this->clubQuery(Athlete::class, $request)->find($athleteId);
            if ($athlete) {
                [$transactions, $balance, $monthRows] = $this->partySeasonDetail(
                    $clubId,
                    $athlete->id,
                    'athlete_id',
                    $seasonStart,
                    $seasonPeriods,
                    includeAttendance: true,
                );
                $fatherName = trim((string) (
                    $athlete->meta['father_first_name']
                    ?? $athlete->meta['father_name']
                    ?? ''
                ));
                $selected = [
                    'id' => $athlete->id,
                    'party_type' => 'athlete',
                    'first_name' => $athlete->first_name,
                    'last_name' => $athlete->last_name,
                    'monthly_fee' => $athlete->monthly_fee,
                    'status' => $athlete->status,
                    'email' => $athlete->email,
                    'phone' => $athlete->phone,
                    'sport' => $athlete->sport,
                    'city' => $athlete->meta['city'] ?? '',
                    'amka' => filled($athlete->amka_encrypted) ? '********' : '',
                    'registration_number' => $athlete->meta['registration_number'] ?? '',
                    'father_first_name' => $fatherName,
                    'father_name' => $fatherName,
                    'birth_date' => optional($athlete->birth_date)?->toDateString(),
                ];
            }
        }

        $activeSeason = ClubSeason::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->where('is_active', true)
            ->first(['name']);

        return Inertia::render('Admin/Transactions/Index', [
            'athletes' => $athleteRows,
            'members' => $memberRows,
            'showingMembers' => $showingMembers,
            'selected' => $selected,
            'transactions' => $transactions,
            'monthRows' => $monthRows,
            'balance' => $balance,
            'sports' => $sports,
            'seasonStart' => $seasonStart,
            'seasonLabel' => $activeSeason?->name ?: sprintf('%d-%d', $seasonStart, $seasonStart + 1),
            'filters' => [
                'q' => $q,
                'sport' => $sport,
                'athlete_id' => $athleteId,
                'member_id' => $memberId,
                'season_start' => $seasonStart,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'athlete_id' => $request->filled('athlete_id') ? $request->input('athlete_id') : null,
            'club_member_id' => $request->filled('club_member_id') ? $request->input('club_member_id') : null,
        ]);

        $data = $request->validate([
            'athlete_id' => 'nullable|uuid',
            'club_member_id' => 'nullable|uuid',
            'type' => 'required|in:charge,payment',
            'amount' => 'required|numeric|min:0.01',
            'day' => 'nullable|integer|min:1|max:31',
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2000|max:2100',
            'payment_method' => 'nullable|in:cash,card,transfer,online,other',
            'receipt_number' => 'nullable|string|max:64',
            'comments' => 'nullable|string|max:2000',
        ]);

        $athleteId = $data['athlete_id'] ?? null;
        $memberId = $data['club_member_id'] ?? null;

        if (($athleteId === null || $athleteId === '') === ($memberId === null || $memberId === '')) {
            throw ValidationException::withMessages([
                'athlete_id' => 'Επιλέξτε αθλητή ή μέλος.',
            ]);
        }

        $clubId = $this->clubId($request);
        $month = (int) ($data['month'] ?? now()->month);
        $year = (int) ($data['year'] ?? now()->year);
        $day = (int) ($data['day'] ?? now()->day);
        $createdAt = Carbon::createSafe($year, $month, min($day, Carbon::create($year, $month, 1)->daysInMonth), 12, 0, 0)
            ?: now();

        $payload = [
            'id' => (string) Str::uuid(),
            'club_id' => $clubId,
            'athlete_id' => null,
            'club_member_id' => null,
            'type' => $data['type'],
            'amount' => $data['amount'],
            'month' => $month,
            'year' => $year,
            'payment_method' => $data['type'] === 'payment' ? ($data['payment_method'] ?? null) : null,
            'receipt_number' => $data['type'] === 'payment' ? ($data['receipt_number'] ?? null) : null,
            'comments' => $data['comments'] ?? null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];

        $redirect = ['season_start' => $this->seasonStartFromPeriod($month, $year)];

        if ($memberId) {
            $member = $this->clubQuery(ClubMember::class, $request)->findOrFail($memberId);
            $payload['club_member_id'] = $member->id;
            $payload['club_id'] = $member->club_id;
            $redirect['sport'] = self::MEMBERS_SPORT_FILTER;
            $redirect['member_id'] = $member->id;
        } else {
            $athlete = $this->clubQuery(Athlete::class, $request)->findOrFail($athleteId);
            $payload['athlete_id'] = $athlete->id;
            $payload['club_id'] = $athlete->club_id;
            $redirect['athlete_id'] = $athlete->id;
        }

        AthleteTransaction::query()->withoutGlobalScopes()->create($payload);

        return redirect()
            ->route('admin.transactions.index', $redirect)
            ->with('success', 'Η συναλλαγή καταχωρήθηκε.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'type' => 'required|in:charge,payment',
            'amount' => 'required|numeric|min:0.01',
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2000|max:2100',
            'payment_method' => 'nullable|in:cash,card,transfer,online,other',
            'receipt_number' => 'nullable|string|max:64',
            'comments' => 'nullable|string|max:2000',
        ]);

        $row = $this->clubQuery(AthleteTransaction::class, $request)->findOrFail($id);
        $row->fill($data)->save();

        $redirect = [
            'season_start' => $this->seasonStartFromPeriod((int) $row->month, (int) $row->year),
        ];
        if ($row->club_member_id) {
            $redirect['sport'] = self::MEMBERS_SPORT_FILTER;
            $redirect['member_id'] = $row->club_member_id;
        } else {
            $redirect['athlete_id'] = $row->athlete_id;
        }

        return redirect()
            ->route('admin.transactions.index', $redirect)
            ->with('success', 'Η συναλλαγή ενημερώθηκε.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $row = $this->clubQuery(AthleteTransaction::class, $request)->findOrFail($id);
        $redirect = [
            'season_start' => $this->seasonStartFromPeriod((int) $row->month, (int) $row->year),
        ];
        if ($row->club_member_id) {
            $redirect['sport'] = self::MEMBERS_SPORT_FILTER;
            $redirect['member_id'] = $row->club_member_id;
        } else {
            $redirect['athlete_id'] = $row->athlete_id;
        }
        $row->delete();

        return redirect()
            ->route('admin.transactions.index', $redirect)
            ->with('success', 'Η συναλλαγή διαγράφηκε.');
    }

    private function seasonStartFromPeriod(int $month, int $year): int
    {
        return $month >= 8 ? $year : $year - 1;
    }

    /**
     * @return list<array{month: int, year: int}>
     */
    private function seasonPeriods(int $seasonStart): array
    {
        return array_map(
            fn (int $month) => [
                'month' => $month,
                'year' => $month >= 8 ? $seasonStart : $seasonStart + 1,
            ],
            self::SEASON_MONTHS,
        );
    }

    /**
     * @param  Collection<int, string>  $partyIds
     * @param  list<array{month: int, year: int}>  $seasonPeriods
     * @return array<string, float>
     */
    private function seasonBalancesByParty(string $clubId, Collection $partyIds, array $seasonPeriods, string $column): array
    {
        if ($partyIds->isEmpty()) {
            return [];
        }

        $balances = [];
        AthleteTransaction::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->whereIn($column, $partyIds)
            ->where(function ($w) use ($seasonPeriods) {
                foreach ($seasonPeriods as $i => $period) {
                    $method = $i === 0 ? 'where' : 'orWhere';
                    $w->{$method}(function ($inner) use ($period) {
                        $inner->where('month', $period['month'])->where('year', $period['year']);
                    });
                }
            })
            ->get([$column, 'type', 'amount'])
            ->each(function (AthleteTransaction $tx) use (&$balances, $column) {
                $partyId = (string) $tx->{$column};
                $delta = $tx->type === 'charge' ? (float) $tx->amount : -(float) $tx->amount;
                $balances[$partyId] = ($balances[$partyId] ?? 0) + $delta;
            });

        return $balances;
    }

    /**
     * @param  list<array{month: int, year: int}>  $seasonPeriods
     * @return array{0: Collection<int, AthleteTransaction>, 1: array{charges: float, payments: float, owed: float}, 2: list<array<string, mixed>>}
     */
    private function partySeasonDetail(
        string $clubId,
        string $partyId,
        string $column,
        int $seasonStart,
        array $seasonPeriods,
        bool $includeAttendance,
    ): array {
        $txQuery = AthleteTransaction::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->where($column, $partyId)
            ->where(function ($w) use ($seasonPeriods) {
                foreach ($seasonPeriods as $i => $period) {
                    $method = $i === 0 ? 'where' : 'orWhere';
                    $w->{$method}(function ($inner) use ($period) {
                        $inner->where('month', $period['month'])->where('year', $period['year']);
                    });
                }
            });

        $seasonTx = (clone $txQuery)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('created_at')
            ->limit(300)
            ->get([
                'id', 'type', 'amount', 'month', 'year', 'payment_method',
                'receipt_number', 'receipt_series', 'receipt_seq', 'comments', 'created_at',
            ]);

        $charges = (float) $seasonTx->where('type', 'charge')->sum('amount');
        $payments = (float) $seasonTx->where('type', 'payment')->sum('amount');
        $balance = [
            'charges' => $charges,
            'payments' => $payments,
            'owed' => $charges - $payments,
        ];

        $attendance = collect();
        if ($includeAttendance) {
            $attendance = Attendance::query()->withoutGlobalScopes()
                ->where('club_id', $clubId)
                ->where('athlete_id', $partyId)
                ->whereNotNull('attended_on')
                ->whereBetween('attended_on', [
                    sprintf('%d-08-01', $seasonStart),
                    sprintf('%d-07-31', $seasonStart + 1),
                ])
                ->get(['attended_on', 'status']);
        }

        return [$seasonTx, $balance, $this->buildMonthRows($seasonStart, $seasonTx, $attendance)];
    }

    /**
     * @param  Collection<int, AthleteTransaction>  $seasonTx
     * @param  Collection<int, Attendance>  $attendance
     * @return list<array<string, mixed>>
     */
    private function buildMonthRows(int $seasonStart, Collection $seasonTx, Collection $attendance): array
    {
        $monthLabels = [
            1 => 'Ιανουάριος', 2 => 'Φεβρουάριος', 3 => 'Μάρτιος', 4 => 'Απρίλιος',
            5 => 'Μάιος', 6 => 'Ιούνιος', 7 => 'Ιούλιος', 8 => 'Αύγουστος',
            9 => 'Σεπτέμβριος', 10 => 'Οκτώβριος', 11 => 'Νοέμβριος', 12 => 'Δεκέμβριος',
        ];

        $rows = [];
        foreach (self::SEASON_MONTHS as $month) {
            $year = $month >= 8 ? $seasonStart : $seasonStart + 1;
            $monthTx = $seasonTx->filter(
                fn (AthleteTransaction $tx) => (int) $tx->month === $month && (int) $tx->year === $year
            );
            $charge = (float) $monthTx->where('type', 'charge')->sum('amount');
            $payment = (float) $monthTx->where('type', 'payment')->sum('amount');
            $prefix = sprintf('%d-%02d', $year, $month);
            $monthAttendance = $attendance->filter(
                fn (Attendance $row) => optional($row->attended_on)?->format('Y-m') === $prefix
            );
            $present = $monthAttendance->filter(fn (Attendance $row) => in_array($row->status, ['present', 'late'], true))->count();
            $absent = $monthAttendance->filter(fn (Attendance $row) => in_array($row->status, ['absent', 'excused'], true))->count();

            $rows[] = [
                'month' => $month,
                'year' => $year,
                'label' => ($monthLabels[$month] ?? $month).' '.$year,
                'charge' => round($charge, 2),
                'payment' => round($payment, 2),
                'balance' => round($charge - $payment, 2),
                'trainings' => $monthAttendance->count(),
                'present' => $present,
                'absent' => $absent,
            ];
        }

        return $rows;
    }
}
