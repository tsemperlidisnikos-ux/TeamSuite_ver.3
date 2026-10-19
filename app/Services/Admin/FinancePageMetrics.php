<?php

namespace App\Services\Admin;

use App\Models\Athlete;
use App\Models\AthleteTransaction;
use App\Models\BudgetLine;
use App\Models\Expense;
use App\Models\Revenue;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FinancePageMetrics
{
    public const ATHLETE_SUBSCRIPTIONS = 'ΣΥΝΔΡΟΜΕΣ ΑΘΛΗΤΩΝ';

    /**
     * @return array{
     *     from: string,
     *     to: string,
     *     revenue: float,
     *     expense: float,
     *     net: float,
     *     collectionMethods: list<array{key: string, label: string, count: int, amount: float}>,
     *     incomeRows: list<array{key: string, sport: string, category: string, cash: float, card: float, bank: float, online: float, total: float}>,
     *     expenseRows: list<array{key: string, sport: string, category: string, cash: float, card: float, bank: float, online: float, total: float}>
     * }
     */
    public function balance(string $clubId, Carbon $from, Carbon $to): array
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        $revenues = Revenue::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->get(['id', 'amount', 'category', 'description', 'occurred_on', 'meta']);

        $expenses = Expense::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->get(['id', 'amount', 'category', 'description', 'occurred_on', 'meta']);

        $filteredRevenues = [];
        foreach ($revenues as $revenue) {
            $date = $this->entryDate($revenue->occurred_on, $revenue->meta ?? []);
            if ($date >= $fromDate && $date <= $toDate) {
                $filteredRevenues[] = $revenue;
            }
        }

        $filteredExpenses = [];
        foreach ($expenses as $expense) {
            $date = $this->entryDate($expense->occurred_on, $expense->meta ?? []);
            if ($date >= $fromDate && $date <= $toDate) {
                $filteredExpenses[] = $expense;
            }
        }

        // Legacy FinanceBalancePanel: Σ filtered revenues / expenses (not + athlete payments).
        $revenueTotal = 0.0;
        foreach ($filteredRevenues as $revenue) {
            $revenueTotal += (float) $revenue->amount;
        }

        $expenseTotal = 0.0;
        foreach ($filteredExpenses as $expense) {
            $expenseTotal += (float) $expense->amount;
        }

        // Legacy: paid revenues with linkedTransactionId/part dedupe; keep viva/eurobank/stripe distinct.
        $methods = [];
        $seenLinkedParts = [];
        foreach ($filteredRevenues as $revenue) {
            $meta = $revenue->meta ?? [];
            $status = strtolower((string) ($meta['paymentStatus'] ?? $meta['payment_status'] ?? 'paid'));
            if ($status !== '' && $status !== 'paid' && $status !== '1') {
                continue;
            }

            $sourceId = (string) ($meta['linkedTransactionId'] ?? $meta['linked_transaction_id']
                ?? $meta['linkedRentalBookingId'] ?? $meta['linked_rental_booking_id'] ?? '');
            $method = $this->normalizeBalancePaymentMethod(
                (string) ($meta['paymentMethod'] ?? $meta['payment_method'] ?? '')
            );
            if ($sourceId !== '') {
                $partKey = implode('|', [
                    $sourceId,
                    (string) ($revenue->description ?? ''),
                    (string) ((float) $revenue->amount),
                    $method,
                ]);
                if (isset($seenLinkedParts[$partKey])) {
                    continue;
                }
                $seenLinkedParts[$partKey] = true;
            }

            $key = $method !== '' ? $method : 'unspecified';
            if (! isset($methods[$key])) {
                $methods[$key] = [
                    'key' => $key,
                    'label' => $this->balancePaymentMethodLabel($key),
                    'count' => 0,
                    'amount' => 0.0,
                ];
            }
            $methods[$key]['count']++;
            $methods[$key]['amount'] = round($methods[$key]['amount'] + (float) $revenue->amount, 2);
        }

        $methodOrder = ['cash', 'transfer', 'card', 'viva', 'eurobank', 'stripe', 'unspecified'];
        $ordered = [];
        foreach ($methodOrder as $key) {
            if (isset($methods[$key])) {
                $ordered[] = $methods[$key];
                unset($methods[$key]);
            }
        }
        foreach ($methods as $row) {
            $ordered[] = $row;
        }

        return [
            'from' => $fromDate,
            'to' => $toDate,
            'revenue' => round($revenueTotal, 2),
            'expense' => round($expenseTotal, 2),
            'net' => round($revenueTotal - $expenseTotal, 2),
            'collectionMethods' => $ordered,
            'incomeRows' => $this->balanceAggregateRows($filteredRevenues),
            'expenseRows' => $this->balanceAggregateRows($filteredExpenses),
        ];
    }

    /**
     * @return array{
     *     dateFrom: string,
     *     dateTo: string,
     *     totalRevenue: float,
     *     totalExpense: float,
     *     balance: float,
     *     athleteDebts: float,
     *     todayRevenue: float,
     *     todayExpense: float,
     *     todayBalance: float,
     *     currentSeasonLabel: string
     * }
     */
    public function reports(string $clubId, Carbon $from, Carbon $to): array
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();
        $today = now()->toDateString();

        $revenues = Revenue::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->get(['amount', 'occurred_on', 'meta']);

        $expenses = Expense::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->get(['amount', 'occurred_on', 'meta']);

        $payments = AthleteTransaction::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->where('type', 'payment')
            ->get(['amount', 'created_at', 'meta']);

        $charges = AthleteTransaction::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->where('type', 'charge')
            ->get(['amount', 'meta']);

        // Legacy reports: Σ revenues in range only (subscription mirrors already included).
        $sumRevenueBetween = function (string $start, string $end) use ($revenues): float {
            $total = 0.0;
            foreach ($revenues as $revenue) {
                $date = $this->entryDate($revenue->occurred_on, $revenue->meta ?? []);
                if ($date >= $start && $date <= $end) {
                    $total += (float) $revenue->amount;
                }
            }

            return round($total, 2);
        };

        $sumExpenseBetween = function (string $start, string $end) use ($expenses): float {
            $total = 0.0;
            foreach ($expenses as $expense) {
                $date = $this->entryDate($expense->occurred_on, $expense->meta ?? []);
                if ($date >= $start && $date <= $end) {
                    $total += (float) $expense->amount;
                }
            }

            return round($total, 2);
        };

        $athleteDebts = round((float) $charges->sum(fn (AthleteTransaction $tx) => (float) $tx->amount)
            - (float) $payments->sum(fn (AthleteTransaction $tx) => (float) $tx->amount), 2);
        if ($athleteDebts < 0) {
            $athleteDebts = 0.0;
        }

        $totalRevenue = $sumRevenueBetween($fromDate, $toDate);
        $totalExpense = $sumExpenseBetween($fromDate, $toDate);
        $todayRevenue = $sumRevenueBetween($today, $today);
        $todayExpense = $sumExpenseBetween($today, $today);

        return [
            'dateFrom' => $fromDate,
            'dateTo' => $toDate,
            'totalRevenue' => $totalRevenue,
            'totalExpense' => $totalExpense,
            'balance' => round($totalRevenue - $totalExpense, 2),
            'athleteDebts' => $athleteDebts,
            'todayRevenue' => $todayRevenue,
            'todayExpense' => $todayExpense,
            'todayBalance' => round($todayRevenue - $todayExpense, 2),
            'currentSeasonLabel' => $this->seasonLabel($this->currentSeasonStartYear()),
        ];
    }

    /**
     * @return array{
     *     seasonStart: int,
     *     seasonLabel: string,
     *     budgetedRevenue: float,
     *     actualRevenue: float,
     *     budgetedExpense: float,
     *     actualExpense: float,
     *     lines: list<array{id: string, type: string, subcategory: string, amount: float, actual: float, clubName: string, sport: string}>
     * }
     */
    public function budget(string $clubId, int $seasonStart): array
    {
        $bounds = $this->seasonBounds($seasonStart);
        $lines = BudgetLine::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->get(['id', 'label', 'year', 'amount', 'meta']);

        $seasonLines = $lines->filter(function (BudgetLine $line) use ($seasonStart) {
            $meta = $line->meta ?? [];
            $start = (int) ($meta['seasonStart'] ?? $line->year ?? $seasonStart);

            return $start === $seasonStart;
        });

        $revenues = Revenue::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->get(['amount', 'occurred_on', 'category', 'meta']);

        $expenses = Expense::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->get(['amount', 'occurred_on', 'category', 'meta']);

        $payments = AthleteTransaction::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->where('type', 'payment')
            ->get(['amount', 'month', 'year', 'athlete_id', 'meta']);

        $athletes = Athlete::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->get(['id', 'meta']);

        $athleteMeta = [];
        foreach ($athletes as $athlete) {
            $meta = $athlete->meta ?? [];
            $athleteMeta[(string) $athlete->id] = [
                'clubName' => (string) ($meta['clubName'] ?? $meta['club_name'] ?? ''),
                'sport' => (string) ($meta['sport'] ?? ''),
            ];
        }

        $mapped = [];
        $budgetedRevenue = 0.0;
        $budgetedExpense = 0.0;
        $actualRevenue = 0.0;
        $actualExpense = 0.0;

        foreach ($seasonLines as $line) {
            $meta = $line->meta ?? [];
            $type = (string) ($meta['type'] ?? 'income');
            $subcategory = (string) ($meta['subcategory'] ?? $line->label ?? '');
            $clubName = (string) ($meta['clubName'] ?? '');
            $sport = (string) ($meta['sport'] ?? '');
            $amount = (float) $line->amount;

            if ($type === 'income') {
                $budgetedRevenue += $amount;
            } else {
                $budgetedExpense += $amount;
            }

            $actual = 0.0;
            if ($type === 'income' && $subcategory === self::ATHLETE_SUBSCRIPTIONS) {
                foreach ($payments as $payment) {
                    if (! $this->paymentInSeason($payment, $seasonStart)) {
                        continue;
                    }
                    $athlete = $athleteMeta[(string) $payment->athlete_id] ?? ['clubName' => '', 'sport' => ''];
                    if ($clubName !== '' && $athlete['clubName'] !== $clubName) {
                        continue;
                    }
                    if ($sport !== '' && $athlete['sport'] !== $sport) {
                        continue;
                    }
                    $actual += (float) $payment->amount;
                }
            } elseif ($type === 'income') {
                foreach ($revenues as $revenue) {
                    $date = $this->entryDate($revenue->occurred_on, $revenue->meta ?? []);
                    if ($date < $bounds['from'] || $date > $bounds['to']) {
                        continue;
                    }
                    $revSub = (string) (($revenue->meta['subcategory'] ?? null) ?: $revenue->category);
                    if ($revSub !== $subcategory) {
                        continue;
                    }
                    if ($clubName !== '' && (string) ($revenue->meta['clubName'] ?? '') !== $clubName) {
                        continue;
                    }
                    if ($sport !== '' && (string) ($revenue->meta['sport'] ?? '') !== $sport) {
                        continue;
                    }
                    $actual += (float) $revenue->amount;
                }
            } else {
                foreach ($expenses as $expense) {
                    $date = $this->entryDate($expense->occurred_on, $expense->meta ?? []);
                    if ($date < $bounds['from'] || $date > $bounds['to']) {
                        continue;
                    }
                    $expSub = (string) (($expense->meta['subcategory'] ?? null) ?: $expense->category);
                    if ($expSub !== $subcategory) {
                        continue;
                    }
                    if ($clubName !== '' && (string) ($expense->meta['clubName'] ?? '') !== $clubName) {
                        continue;
                    }
                    if ($sport !== '' && (string) ($expense->meta['sport'] ?? '') !== $sport) {
                        continue;
                    }
                    $actual += (float) $expense->amount;
                }
            }

            $actual = round($actual, 2);
            if ($type === 'income') {
                $actualRevenue += $actual;
            } else {
                $actualExpense += $actual;
            }

            $mapped[] = [
                'id' => (string) $line->id,
                'type' => $type,
                'subcategory' => $subcategory,
                'amount' => round($amount, 2),
                'actual' => $actual,
                'clubName' => $clubName,
                'sport' => $sport,
            ];
        }

        usort($mapped, function (array $a, array $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'income' ? -1 : 1;
            }

            return strcmp($a['subcategory'], $b['subcategory']);
        });

        return [
            'seasonStart' => $seasonStart,
            'seasonLabel' => $this->seasonLabel($seasonStart),
            'budgetedRevenue' => round($budgetedRevenue, 2),
            'actualRevenue' => round($actualRevenue, 2),
            'budgetedExpense' => round($budgetedExpense, 2),
            'actualExpense' => round($actualExpense, 2),
            'lines' => $mapped,
        ];
    }

    /**
     * @param  Collection<int, Revenue>  $revenues
     * @param  Collection<int, AthleteTransaction>  $payments
     * @return list<array<string, mixed>>
     */
    public function revenueRows(Collection $revenues, Collection $payments, string $subcategory): array
    {
        $rows = [];
        $mirroredPaymentKeys = [];

        foreach ($revenues as $revenue) {
            $meta = $revenue->meta ?? [];
            $revSub = (string) (($meta['subcategory'] ?? null) ?: $revenue->category);
            if ($revSub !== $subcategory) {
                continue;
            }

            $linkedId = $this->linkedTransactionId($revenue);
            if ($linkedId !== null) {
                $mirroredPaymentKeys[$linkedId] = true;
            }

            $name = trim((string) (($meta['surname'] ?? '').' '.($meta['firstName'] ?? $meta['first_name'] ?? '')));
            $label = trim((string) ($revenue->description ?: ($subcategory === self::ATHLETE_SUBSCRIPTIONS ? 'ΜΗΝΙΑΙΑ ΣΥΝΔΡΟΜΗ' : $revSub)));
            if ($name !== '' && ! str_contains(mb_strtoupper($label), mb_strtoupper($name))) {
                $label = $label.' – '.$name;
            }

            $periodRaw = (string) ($meta['subscriptionPeriod'] ?? $meta['subscription_period'] ?? '');
            $period = $periodRaw;
            if (preg_match('/^(\d{4})-(\d{2})$/', $periodRaw, $matches) === 1) {
                $period = $matches[2].'-'.$matches[1];
            }

            $rows[] = [
                'id' => (string) $revenue->id,
                'date' => $this->entryDate($revenue->occurred_on, $meta),
                'description' => $label !== '' ? $label : $revSub,
                'clubName' => (string) ($meta['clubName'] ?? ''),
                'sport' => (string) ($meta['sport'] ?? ''),
                'period' => $period,
                'amount' => (float) $revenue->amount,
            ];
        }

        // New payments without a ledger mirror (TeamSuite sync bridge not present yet).
        if ($subcategory === self::ATHLETE_SUBSCRIPTIONS) {
            $athletes = Athlete::query()->withoutGlobalScopes()
                ->whereIn('id', $payments->pluck('athlete_id')->filter()->unique())
                ->get(['id', 'first_name', 'last_name', 'meta']);
            $byId = $athletes->keyBy('id');

            foreach ($payments as $payment) {
                $keys = array_filter([
                    (string) $payment->id,
                    (string) ($payment->legacy_id ?? ''),
                ]);
                $alreadyMirrored = false;
                foreach ($keys as $key) {
                    if ($key !== '' && isset($mirroredPaymentKeys[$key])) {
                        $alreadyMirrored = true;
                        break;
                    }
                }
                if ($alreadyMirrored) {
                    continue;
                }

                $athlete = $byId->get($payment->athlete_id);
                $meta = $athlete?->meta ?? [];
                $name = trim(($athlete?->last_name ?? '').' '.($athlete?->first_name ?? ''));
                $label = trim((string) ($payment->comments ?: 'ΜΗΝΙΑΙΑ ΣΥΝΔΡΟΜΗ'));
                if ($name !== '' && ! str_contains(mb_strtoupper($label), mb_strtoupper($name))) {
                    $label = $label.' – '.$name;
                }

                $rows[] = [
                    'id' => (string) $payment->id,
                    'date' => $this->paymentDate($payment),
                    'description' => $label !== '' ? $label : 'Πληρωμή αθλητή',
                    'clubName' => (string) ($meta['clubName'] ?? $meta['club_name'] ?? ''),
                    'sport' => (string) (($meta['sport'] ?? $meta['sports'][0] ?? '') ?: ''),
                    'period' => sprintf('%02d-%04d', (int) $payment->month, (int) $payment->year),
                    'amount' => (float) $payment->amount,
                ];
            }
        }

        usort($rows, fn (array $a, array $b) => strcmp($b['date'], $a['date']));

        return $rows;
    }

    /**
     * @param  Collection<int, Expense>  $expenses
     * @return list<array<string, mixed>>
     */
    public function expenseRows(Collection $expenses, string $subcategory): array
    {
        $rows = [];
        foreach ($expenses as $expense) {
            $meta = $expense->meta ?? [];
            $expSub = (string) (($meta['subcategory'] ?? null) ?: $expense->category);
            if ($expSub !== $subcategory) {
                continue;
            }
            $rows[] = [
                'id' => (string) $expense->id,
                'date' => $this->entryDate($expense->occurred_on, $meta),
                'description' => (string) ($expense->description ?: $expSub),
                'clubName' => (string) ($meta['clubName'] ?? ''),
                'sport' => (string) ($meta['sport'] ?? ''),
                'amount' => (float) $expense->amount,
            ];
        }

        usort($rows, fn (array $a, array $b) => strcmp($b['date'], $a['date']));

        return $rows;
    }

    public function currentSeasonStartYear(?Carbon $anchor = null): int
    {
        $anchor ??= now();
        $month = (int) $anchor->format('n');
        $year = (int) $anchor->format('Y');

        return $month >= 8 ? $year : $year - 1;
    }

    public function seasonLabel(int $seasonStart): string
    {
        return sprintf('%d-%02d', $seasonStart, ($seasonStart + 1) % 100);
    }

    /**
     * @return array{from: string, to: string}
     */
    public function seasonBounds(int $seasonStart): array
    {
        return [
            'from' => sprintf('%d-08-01', $seasonStart),
            'to' => sprintf('%d-07-31', $seasonStart + 1),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function entryDate(mixed $occurredOn, array $meta): string
    {
        foreach (['date', 'occurredOn', 'occurred_on'] as $key) {
            $raw = $meta[$key] ?? null;
            if (is_string($raw) && preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) {
                return substr($raw, 0, 10);
            }
        }

        if ($occurredOn instanceof Carbon) {
            return $occurredOn->toDateString();
        }
        if (is_string($occurredOn) && preg_match('/^\d{4}-\d{2}-\d{2}/', $occurredOn)) {
            return substr($occurredOn, 0, 10);
        }

        return '';
    }

    private function paymentDate(AthleteTransaction $transaction): string
    {
        $meta = $transaction->meta ?? [];
        $raw = $meta['createdAt'] ?? $meta['created_at'] ?? null;
        if (is_string($raw) && preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) {
            return substr($raw, 0, 10);
        }

        return optional($transaction->created_at)?->toDateString() ?? '';
    }

    private function paymentInSeason(AthleteTransaction $payment, int $seasonStart): bool
    {
        $month = (int) $payment->month;
        $year = (int) $payment->year;
        if ($month <= 0 || $year <= 0) {
            $date = $this->paymentDate($payment);
            if ($date === '') {
                return false;
            }
            $parsed = Carbon::parse($date);

            return $this->currentSeasonStartYear($parsed) === $seasonStart;
        }

        $start = $month >= 8 ? $year : $year - 1;

        return $start === $seasonStart;
    }

    private function collectionMethodKey(AthleteTransaction $payment): string
    {
        $method = strtolower((string) ($payment->payment_method ?? $payment->meta['paymentMethod'] ?? ''));

        return match ($method) {
            'cash' => 'cash',
            'card' => 'card',
            'transfer' => 'transfer',
            'viva', 'stripe', 'eurobank', 'online' => 'online',
            default => 'other',
        };
    }

    private function collectionMethodLabel(string $key): string
    {
        return match ($key) {
            'cash' => 'Μετρητά',
            'card' => 'POS',
            'transfer' => 'Κατάθεση',
            'online' => 'VIVA',
            default => 'Χωρίς τρόπο πληρωμής',
        };
    }

    private function linkedTransactionId(Revenue $revenue): ?string
    {
        $id = $revenue->meta['linkedTransactionId'] ?? $revenue->meta['linked_transaction_id'] ?? null;

        return $id !== null && $id !== '' ? (string) $id : null;
    }

    /**
     * @param  list<Revenue|Expense>  $entries
     * @return list<array{key: string, sport: string, category: string, cash: float, card: float, bank: float, online: float, total: float}>
     */
    private function balanceAggregateRows(array $entries): array
    {
        $map = [];
        foreach ($entries as $entry) {
            $meta = $entry->meta ?? [];
            $sport = trim((string) ($meta['sport'] ?? ''));
            if ($sport === '') {
                $sport = '—';
            }
            $category = trim((string) (($meta['subcategory'] ?? null) ?: ($entry->category ?? '')));
            if ($category === '') {
                $category = '—';
            }
            $key = mb_strtolower($sport.'::'.$category);
            if (! isset($map[$key])) {
                $map[$key] = [
                    'key' => $key,
                    'sport' => $sport,
                    'category' => $category,
                    'cash' => 0.0,
                    'card' => 0.0,
                    'bank' => 0.0,
                    'online' => 0.0,
                    'total' => 0.0,
                ];
            }
            $amount = (float) $entry->amount;
            $bucket = $this->balancePaymentBucket(
                (string) ($meta['paymentMethod'] ?? $meta['payment_method'] ?? '')
            );
            $map[$key][$bucket] = round($map[$key][$bucket] + $amount, 2);
            $map[$key]['total'] = round($map[$key]['total'] + $amount, 2);
        }

        $rows = array_values($map);
        usort(
            $rows,
            fn (array $a, array $b) => strcmp($a['sport'].' '.$a['category'], $b['sport'].' '.$b['category'])
        );

        return $rows;
    }

    private function balancePaymentBucket(string $method): string
    {
        $normalized = $this->normalizeBalancePaymentMethod($method);

        return match ($normalized) {
            'cash' => 'cash',
            'card' => 'card',
            'transfer' => 'bank',
            default => 'online',
        };
    }

    private function normalizeBalancePaymentMethod(string $method): string
    {
        $method = strtolower(trim($method));
        if ($method === 'other') {
            return 'viva';
        }

        return match ($method) {
            'cash', 'μετρητά', 'μετρητα' => 'cash',
            'card', 'pos', 'κάρτα', 'καρτα' => 'card',
            'transfer', 'bank', 'κατάθεση', 'καταθεση' => 'transfer',
            'viva' => 'viva',
            'eurobank' => 'eurobank',
            'stripe' => 'stripe',
            'online' => 'viva',
            default => $method,
        };
    }

    private function balancePaymentMethodLabel(string $key): string
    {
        return match ($key) {
            'cash' => 'Μετρητά',
            'card' => 'POS',
            'transfer' => 'Κατάθεση',
            'viva' => 'VIVA',
            'eurobank' => 'Eurobank',
            'stripe' => 'Stripe',
            'unspecified' => 'Χωρίς τρόπο πληρωμής',
            default => $key,
        };
    }
}
