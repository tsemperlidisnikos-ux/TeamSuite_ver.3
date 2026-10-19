<?php

namespace App\Services\Admin;

use App\Models\AcademyClass;
use App\Models\Athlete;
use App\Models\AthleteTransaction;
use App\Models\CashAccount;
use App\Models\ClubSeason;
use App\Models\Coach;
use App\Models\Expense;
use App\Models\RentalBooking;
use App\Models\Revenue;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClubDashboardMetrics
{
    /**
     * Build Επισκόπηση KPIs using the same formulas as legacy TeamSuite DashboardPage.
     *
     * @return array{
     *     bySport: list<array{sport: string, athletes: int, classes: int, coaches: int}>,
     *     money: array{cash: float, debts: float, revenueToday: float, collectionsMonth: float},
     *     financeBySport: list<array{sport: string, collected: float, owed: float}>,
     *     till: array{date: string, collections: float, revenues: float, rentals: float},
     *     uncollectedRentals: array{count: int, total: float}
     * }
     */
    public function forClub(string $clubId, ?Carbon $now = null): array
    {
        $now ??= now();
        $today = $now->toDateString();
        $monthPrefix = $now->format('Y-m');

        $classes = AcademyClass::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->get(['id', 'sport', 'club_season_id', 'meta', 'legacy_id']);

        $seasons = ClubSeason::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->get(['id', 'legacy_id', 'is_active', 'starts_on', 'ends_on']);

        $classSportById = [];
        foreach ($classes as $class) {
            $sport = trim((string) $class->sport);
            $classSportById[(string) $class->id] = $sport;
            if ($class->legacy_id) {
                $classSportById[(string) $class->legacy_id] = $sport;
            }
        }

        $activeClasses = $classes->filter(
            fn (AcademyClass $class) => $this->isClassListedActive($class, $seasons, $today)
        );

        $classesBySport = $this->countBySportKey(
            $activeClasses->map(fn (AcademyClass $class) => trim((string) $class->sport))
        );

        $coachesBySport = $this->countBySportKey(
            Coach::query()->withoutGlobalScopes()
                ->where('club_id', $clubId)
                ->where(function ($query) {
                    $query->where('is_active', true)->orWhereNull('is_active');
                })
                ->get(['sport', 'meta'])
                ->filter(function (Coach $coach) {
                    $active = $coach->meta['active'] ?? null;

                    return $active !== false;
                })
                ->map(fn (Coach $coach) => trim((string) $coach->sport))
        );

        $athletes = Athlete::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->get(['id', 'status', 'sport', 'meta']);

        $pivotClassSports = $this->athletePivotClassSports($clubId);

        $athleteSportById = [];
        $activeBySport = [];
        foreach ($athletes as $athlete) {
            $sport = $this->resolveAthleteSport(
                $athlete,
                $classSportById,
                $pivotClassSports[(string) $athlete->id] ?? [],
            );
            $athleteSportById[$athlete->id] = $sport;
            if ($athlete->status === 'active') {
                $key = $sport !== '' ? $sport : 'Χωρίς άθλημα';
                $activeBySport[$key] = ($activeBySport[$key] ?? 0) + 1;
            }
        }

        $bySport = collect($activeBySport)
            ->map(function (int $count, string $sport) use ($classesBySport, $coachesBySport) {
                $key = $sport === 'Χωρίς άθλημα' ? '' : $sport;

                return [
                    'sport' => $sport,
                    'athletes' => $count,
                    'classes' => (int) ($classesBySport[$key] ?? 0),
                    'coaches' => (int) ($coachesBySport[$key] ?? 0),
                ];
            })
            ->sortByDesc('athletes')
            ->values()
            ->all();

        $transactions = AthleteTransaction::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->get(['id', 'athlete_id', 'type', 'amount', 'month', 'year', 'created_at', 'meta']);

        $revenues = Revenue::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->get(['id', 'amount', 'occurred_on', 'cash_account_id', 'meta']);

        $expenses = Expense::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->get(['id', 'amount', 'occurred_on', 'cash_account_id', 'meta']);

        $rentals = RentalBooking::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->get(['id', 'amount', 'status', 'starts_at', 'meta']);

        $cashBalance = $this->cashBalance($clubId, $revenues, $expenses);
        $debts = $this->outstandingOwed($athletes, $transactions);
        $paymentsMonth = $this->sumPaymentsForMonth($transactions, $monthPrefix);
        $unlinkedRevenuesMonth = $this->sumUnlinkedPaidRevenuesForMonth($revenues, $monthPrefix);
        $collectionsMonth = $paymentsMonth + $unlinkedRevenuesMonth;

        $paymentsToday = $this->sumPaymentsForDay($transactions, $today);
        $unlinkedRevenuesToday = $this->sumUnlinkedPaidRevenuesForDay($revenues, $today);
        $collectedRentalsToday = $this->sumCollectedRentalsForDay($rentals, $revenues, $today);
        $dailyIncome = $paymentsToday + $unlinkedRevenuesToday + $collectedRentalsToday;

        $revenuesTodayAll = $revenues
            ->filter(fn (Revenue $revenue) => $this->revenueDate($revenue) === $today)
            ->sum(fn (Revenue $revenue) => (float) $revenue->amount);

        $financeBySport = $this->financeBySport(
            $activeBySport,
            $athleteSportById,
            $athletes,
            $transactions,
            $revenues,
            $monthPrefix,
        );

        $uncollected = $rentals->filter(fn (RentalBooking $booking) => $this->isUncollectedRental($booking));

        return [
            'bySport' => $bySport,
            'money' => [
                'cash' => round($cashBalance, 2),
                'debts' => round($debts, 2),
                'revenueToday' => round($dailyIncome, 2),
                'collectionsMonth' => round($collectionsMonth, 2),
            ],
            'financeBySport' => $financeBySport,
            'till' => [
                'date' => $today,
                'collections' => round($paymentsToday, 2),
                'revenues' => round((float) $revenuesTodayAll, 2),
                'rentals' => round($this->sumCollectedRentalsForDayRaw($rentals, $today), 2),
            ],
            'uncollectedRentals' => [
                'count' => $uncollected->count(),
                'total' => round((float) $uncollected->sum(fn (RentalBooking $booking) => (float) $booking->amount), 2),
            ],
        ];
    }

    /**
     * @param  array<string, string>  $classSportById
     * @param  list<string>  $pivotSports
     */
    private function resolveAthleteSport(Athlete $athlete, array $classSportById, array $pivotSports = []): string
    {
        $meta = $athlete->meta ?? [];
        $sports = $meta['sports'] ?? [];
        if (is_string($sports)) {
            $sports = array_filter(array_map('trim', explode(',', $sports)));
        }
        if (is_array($sports)) {
            foreach ($sports as $sport) {
                $sport = trim((string) $sport);
                if ($sport !== '') {
                    return $sport;
                }
            }
        }

        $own = trim((string) ($athlete->sport ?? ''));
        if ($own !== '') {
            return $own;
        }

        $classIds = [];
        if (! empty($meta['class_ids']) && is_array($meta['class_ids'])) {
            $classIds = $meta['class_ids'];
        } elseif (! empty($meta['classIds']) && is_array($meta['classIds'])) {
            $classIds = $meta['classIds'];
        }
        if (! empty($meta['primary_class_id'])) {
            array_unshift($classIds, $meta['primary_class_id']);
        } elseif (! empty($meta['classId'])) {
            array_unshift($classIds, $meta['classId']);
        }

        foreach ($classIds as $classId) {
            $sport = trim((string) ($classSportById[(string) $classId] ?? ''));
            if ($sport !== '') {
                return $sport;
            }
        }

        foreach ($pivotSports as $sport) {
            $sport = trim((string) $sport);
            if ($sport !== '') {
                return $sport;
            }
        }

        return '';
    }

    /**
     * @return array<string, list<string>>
     */
    private function athletePivotClassSports(string $clubId): array
    {
        $rows = DB::table('academy_class_athlete')
            ->join('academy_classes', 'academy_classes.id', '=', 'academy_class_athlete.academy_class_id')
            ->where('academy_classes.club_id', $clubId)
            ->whereNull('academy_classes.deleted_at')
            ->select(['academy_class_athlete.athlete_id', 'academy_classes.sport'])
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row->athlete_id][] = trim((string) $row->sport);
        }

        return $map;
    }

    /**
     * @param  Collection<int, ClubSeason>  $seasons
     */
    private function isClassListedActive(AcademyClass $class, Collection $seasons, string $today): bool
    {
        $meta = $class->meta ?? [];
        if (($meta['manualInactive'] ?? false) === true) {
            return false;
        }

        if ($seasons->isEmpty()) {
            return true;
        }

        $activeSeason = $seasons->first(function (ClubSeason $season) use ($today) {
            if ($season->is_active) {
                return true;
            }
            $start = optional($season->starts_on)?->toDateString();
            $end = optional($season->ends_on)?->toDateString();
            if ($start && $end) {
                return $today >= $start && $today <= $end;
            }

            return false;
        });

        if (! $activeSeason) {
            return true;
        }

        $classSeasonId = $class->club_season_id
            ?? ($meta['clubSeasonId'] ?? $meta['seasonId'] ?? null);

        if ($classSeasonId === null || $classSeasonId === '') {
            return true;
        }

        return (string) $classSeasonId === (string) $activeSeason->id
            || (string) $classSeasonId === (string) ($activeSeason->legacy_id ?? '');
    }

    /**
     * @param  Collection<int, string>  $sports
     * @return array<string, int>
     */
    private function countBySportKey(Collection $sports): array
    {
        $counts = [];
        foreach ($sports as $sport) {
            $key = trim((string) $sport);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Match legacy getAccountBalances(): only paid revenues / expenses linked to a cash account.
     *
     * @param  Collection<int, Revenue>  $revenues
     * @param  Collection<int, Expense>  $expenses
     */
    private function cashBalance(string $clubId, Collection $revenues, Collection $expenses): float
    {
        $accounts = CashAccount::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->get(['id', 'legacy_id', 'opening_balance']);

        if ($accounts->isEmpty()) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($accounts as $account) {
            $ids = array_filter([(string) $account->id, $account->legacy_id ? (string) $account->legacy_id : null]);

            $income = $revenues
                ->filter(function (Revenue $revenue) use ($ids, $account) {
                    if (! $this->isRevenuePaid($revenue)) {
                        return false;
                    }

                    return $this->entryBelongsToAccount($revenue->cash_account_id, $revenue->meta ?? [], $ids, (string) $account->id);
                })
                ->sum(fn (Revenue $revenue) => (float) $revenue->amount);

            $expenseTotal = $expenses
                ->filter(function (Expense $expense) use ($ids, $account) {
                    return $this->entryBelongsToAccount($expense->cash_account_id, $expense->meta ?? [], $ids, (string) $account->id);
                })
                ->sum(fn (Expense $expense) => (float) $expense->amount);

            $total += (float) $account->opening_balance + (float) $income - (float) $expenseTotal;
        }

        return $total;
    }

    /**
     * @param  list<string>  $accountIds
     * @param  array<string, mixed>  $meta
     */
    private function entryBelongsToAccount(mixed $cashAccountId, array $meta, array $accountIds, string $accountId): bool
    {
        $candidates = array_filter([
            $cashAccountId !== null && $cashAccountId !== '' ? (string) $cashAccountId : null,
            isset($meta['accountId']) && $meta['accountId'] !== '' ? (string) $meta['accountId'] : null,
            isset($meta['cashAccountId']) && $meta['cashAccountId'] !== '' ? (string) $meta['cashAccountId'] : null,
        ]);

        foreach ($candidates as $candidate) {
            if ($candidate === $accountId || in_array($candidate, $accountIds, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, Athlete>  $athletes
     * @param  Collection<int, AthleteTransaction>  $transactions
     */
    private function outstandingOwed(Collection $athletes, Collection $transactions): float
    {
        $balances = [];
        foreach ($transactions as $transaction) {
            $athleteId = (string) $transaction->athlete_id;
            $delta = $transaction->type === 'charge'
                ? (float) $transaction->amount
                : -(float) $transaction->amount;
            $balances[$athleteId] = ($balances[$athleteId] ?? 0) + $delta;
        }

        $total = 0.0;
        foreach ($athletes as $athlete) {
            if ($athlete->status === 'inactive') {
                continue;
            }
            $total += max(0, $balances[$athlete->id] ?? 0);
        }

        return $total;
    }

    /**
     * @param  Collection<int, AthleteTransaction>  $transactions
     */
    private function sumPaymentsForMonth(Collection $transactions, string $monthPrefix): float
    {
        return (float) $transactions
            ->filter(fn (AthleteTransaction $tx) => $tx->type === 'payment'
                && str_starts_with($this->transactionRecordedAt($tx)->toDateString(), $monthPrefix))
            ->sum(fn (AthleteTransaction $tx) => (float) $tx->amount);
    }

    /**
     * @param  Collection<int, AthleteTransaction>  $transactions
     */
    private function sumPaymentsForDay(Collection $transactions, string $day): float
    {
        return (float) $transactions
            ->filter(fn (AthleteTransaction $tx) => $tx->type === 'payment'
                && $this->transactionRecordedAt($tx)->toDateString() === $day)
            ->sum(fn (AthleteTransaction $tx) => (float) $tx->amount);
    }

    /**
     * @param  Collection<int, Revenue>  $revenues
     */
    private function sumUnlinkedPaidRevenuesForMonth(Collection $revenues, string $monthPrefix): float
    {
        return (float) $revenues
            ->filter(function (Revenue $revenue) use ($monthPrefix) {
                if (! $this->isRevenuePaid($revenue) || $this->linkedTransactionId($revenue)) {
                    return false;
                }

                return str_starts_with($this->revenueDate($revenue), $monthPrefix);
            })
            ->sum(fn (Revenue $revenue) => (float) $revenue->amount);
    }

    /**
     * @param  Collection<int, Revenue>  $revenues
     */
    private function sumUnlinkedPaidRevenuesForDay(Collection $revenues, string $day): float
    {
        return (float) $revenues
            ->filter(fn (Revenue $revenue) => $this->isRevenuePaid($revenue)
                && ! $this->linkedTransactionId($revenue)
                && $this->revenueDate($revenue) === $day)
            ->sum(fn (Revenue $revenue) => (float) $revenue->amount);
    }

    /**
     * @param  Collection<int, RentalBooking>  $rentals
     * @param  Collection<int, Revenue>  $revenues
     */
    private function sumCollectedRentalsForDay(Collection $rentals, Collection $revenues, string $day): float
    {
        $countedRentalIds = $revenues
            ->map(fn (Revenue $revenue) => $this->linkedRentalBookingId($revenue))
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->all();
        $counted = array_flip($countedRentalIds);

        return (float) $rentals
            ->filter(function (RentalBooking $booking) use ($day, $counted) {
                if (! $this->isRentalCollected($booking) || ! ((float) $booking->amount > 0)) {
                    return false;
                }
                if ($this->rentalRevenueDate($booking) !== $day) {
                    return false;
                }

                return ! isset($counted[(string) $booking->id])
                    && ! isset($counted[(string) ($booking->legacy_id ?? '')]);
            })
            ->sum(fn (RentalBooking $booking) => (float) $booking->amount);
    }

    /**
     * @param  Collection<int, RentalBooking>  $rentals
     */
    private function sumCollectedRentalsForDayRaw(Collection $rentals, string $day): float
    {
        return (float) $rentals
            ->filter(fn (RentalBooking $booking) => $this->isRentalCollected($booking)
                && (float) $booking->amount > 0
                && $this->rentalRevenueDate($booking) === $day)
            ->sum(fn (RentalBooking $booking) => (float) $booking->amount);
    }

    /**
     * @param  array<string, int>  $activeBySport
     * @param  array<string, string>  $athleteSportById
     * @param  Collection<int, Athlete>  $athletes
     * @param  Collection<int, AthleteTransaction>  $transactions
     * @param  Collection<int, Revenue>  $revenues
     * @return list<array{sport: string, collected: float, owed: float}>
     */
    private function financeBySport(
        array $activeBySport,
        array $athleteSportById,
        Collection $athletes,
        Collection $transactions,
        Collection $revenues,
        string $monthPrefix,
    ): array {
        $sportKeys = array_keys($activeBySport);
        foreach ($athleteSportById as $sport) {
            $label = $sport !== '' ? $sport : 'Χωρίς άθλημα';
            if (! in_array($label, $sportKeys, true)) {
                $sportKeys[] = $label;
            }
        }

        $rows = [];
        foreach ($sportKeys as $sportLabel) {
            $sportKey = $sportLabel === 'Χωρίς άθλημα' ? '' : $sportLabel;
            $studentIds = [];
            foreach ($athleteSportById as $athleteId => $sport) {
                if ($sport === $sportKey) {
                    $studentIds[$athleteId] = true;
                }
            }

            $transactionCollections = (float) $transactions
                ->filter(fn (AthleteTransaction $tx) => $tx->type === 'payment'
                    && isset($studentIds[(string) $tx->athlete_id])
                    && str_starts_with($this->transactionRecordedAt($tx)->toDateString(), $monthPrefix))
                ->sum(fn (AthleteTransaction $tx) => (float) $tx->amount);

            $revenueCollections = (float) $revenues
                ->filter(function (Revenue $revenue) use ($studentIds, $sportKey, $monthPrefix) {
                    if (! $this->isRevenuePaid($revenue) || $this->linkedTransactionId($revenue)) {
                        return false;
                    }
                    if (! str_starts_with($this->revenueDate($revenue), $monthPrefix)) {
                        return false;
                    }
                    $studentId = $this->revenueStudentId($revenue);
                    if ($studentId) {
                        return isset($studentIds[$studentId]);
                    }
                    $revenueSport = trim((string) (($revenue->meta['sport'] ?? '')));

                    return $sportKey !== ''
                        ? $revenueSport === $sportKey
                        : $revenueSport === '';
                })
                ->sum(fn (Revenue $revenue) => (float) $revenue->amount);

            $sportAthletes = $athletes->filter(function (Athlete $athlete) use ($athleteSportById, $sportKey) {
                return ($athleteSportById[$athlete->id] ?? '') === $sportKey;
            });

            $rows[] = [
                'sport' => $sportLabel,
                'collected' => round($transactionCollections + $revenueCollections, 2),
                'owed' => round($this->outstandingOwed($sportAthletes, $transactions), 2),
            ];
        }

        usort($rows, fn (array $a, array $b) => $b['collected'] <=> $a['collected']);

        return array_values($rows);
    }

    private function transactionRecordedAt(AthleteTransaction $transaction): Carbon
    {
        $meta = $transaction->meta ?? [];
        $raw = $meta['createdAt'] ?? $meta['created_at'] ?? null;
        if (is_string($raw) && trim($raw) !== '') {
            return Carbon::parse($raw);
        }

        return Carbon::parse($transaction->created_at);
    }

    private function revenueDate(Revenue $revenue): string
    {
        $meta = $revenue->meta ?? [];
        $raw = $meta['date'] ?? $meta['occurredOn'] ?? null;
        if (is_string($raw) && preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) {
            return substr($raw, 0, 10);
        }

        return optional($revenue->occurred_on)?->toDateString() ?? '';
    }

    private function isRevenuePaid(Revenue $revenue): bool
    {
        $status = $revenue->meta['paymentStatus'] ?? $revenue->meta['payment_status'] ?? 'paid';

        return $status === 'paid' || $status === true || $status === 1 || $status === '1';
    }

    private function linkedTransactionId(Revenue $revenue): ?string
    {
        $id = $revenue->meta['linkedTransactionId'] ?? $revenue->meta['linked_transaction_id'] ?? null;

        return $id !== null && $id !== '' ? (string) $id : null;
    }

    private function linkedRentalBookingId(Revenue $revenue): ?string
    {
        $id = $revenue->meta['linkedRentalBookingId'] ?? $revenue->meta['linked_rental_booking_id'] ?? null;

        return $id !== null && $id !== '' ? (string) $id : null;
    }

    private function revenueStudentId(Revenue $revenue): ?string
    {
        $id = $revenue->meta['studentId'] ?? $revenue->meta['athleteId'] ?? $revenue->meta['student_id'] ?? null;

        return $id !== null && $id !== '' ? (string) $id : null;
    }

    private function isRentalCollected(RentalBooking $booking): bool
    {
        if (in_array($booking->status, ['cancelled', 'pending_payment'], true)) {
            return false;
        }

        $meta = $booking->meta ?? [];
        if (array_key_exists('paymentCollected', $meta) && $meta['paymentCollected'] === false) {
            return false;
        }
        if (($meta['paymentCollected'] ?? null) === true) {
            return true;
        }
        if (! empty($meta['paidOn']) || ! empty($meta['paidAt'])) {
            return true;
        }

        return $booking->status === 'confirmed';
    }

    private function isUncollectedRental(RentalBooking $booking): bool
    {
        if ($booking->status === 'cancelled') {
            return false;
        }

        return (float) $booking->amount > 0 && ! $this->isRentalCollected($booking);
    }

    private function rentalRevenueDate(RentalBooking $booking): string
    {
        $meta = $booking->meta ?? [];
        foreach (['paidOn', 'paidAt'] as $key) {
            $raw = $meta[$key] ?? null;
            if (is_string($raw) && preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) {
                return substr($raw, 0, 10);
            }
        }

        $metaDate = $meta['date'] ?? null;
        if (is_string($metaDate) && preg_match('/^\d{4}-\d{2}-\d{2}/', $metaDate)) {
            return substr($metaDate, 0, 10);
        }

        return optional($booking->starts_at)?->toDateString() ?? '';
    }
}
