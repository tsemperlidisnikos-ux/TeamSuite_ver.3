<?php

namespace App\Services\Admin;

use App\Models\Athlete;
use App\Models\AthleteTransaction;
use App\Models\Expense;
use App\Models\RentalBooking;
use App\Models\Revenue;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FinanceAnalysisMetrics
{
    /**
     * @return array{
     *     day: array<string, mixed>,
     *     totals: array{revenue: float, expense: float, net: float, pending: float},
     *     monthlySeries: list<array{key: string, label: string, revenue: float, expense: float}>,
     *     revenueByCategory: list<array{category: string, amount: float}>,
     *     expenseByCategory: list<array{category: string, amount: float}>
     * }
     */
    public function forClub(string $clubId, ?Carbon $day = null): array
    {
        $day ??= now();
        $date = $day->toDateString();

        $revenues = Revenue::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->get(['id', 'amount', 'category', 'occurred_on', 'meta']);

        $expenses = Expense::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->get(['id', 'amount', 'category', 'occurred_on', 'meta']);

        $payments = AthleteTransaction::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->where('type', 'payment')
            ->get(['id', 'athlete_id', 'amount', 'created_at', 'receipt_number', 'payment_method', 'meta']);

        $rentals = RentalBooking::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->get(['id', 'amount', 'status', 'starts_at', 'meta', 'legacy_id']);

        $athletes = Athlete::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->get(['id', 'first_name', 'last_name']);

        $dayTill = $this->buildDailyTill(
            $date,
            $revenues,
            $payments,
            $rentals,
            $athletes,
        );

        // Legacy getFinanceSummary: Σ paid revenues only (includes subscription mirrors once).
        $paidRevenues = $revenues->filter(fn (Revenue $revenue) => $this->isRevenuePaid($revenue));
        $pending = round((float) $revenues
            ->filter(fn (Revenue $revenue) => $this->isRevenuePending($revenue))
            ->sum(fn (Revenue $revenue) => (float) $revenue->amount), 2);

        $totalRevenue = round((float) $paidRevenues->sum(fn (Revenue $revenue) => (float) $revenue->amount), 2);
        $totalExpense = round((float) $expenses->sum(fn (Expense $expense) => (float) $expense->amount), 2);

        return [
            'day' => $dayTill,
            'totals' => [
                'revenue' => $totalRevenue,
                'expense' => $totalExpense,
                'net' => round($totalRevenue - $totalExpense, 2),
                'pending' => $pending,
            ],
            'monthlySeries' => $this->monthlySeries($paidRevenues, $expenses, $day),
            'revenueByCategory' => $this->categoryBreakdown(
                $paidRevenues->map(fn (Revenue $revenue) => [
                    'category' => trim((string) ($revenue->category ?: 'Χωρίς κατηγορία')) ?: 'Χωρίς κατηγορία',
                    'amount' => (float) $revenue->amount,
                ])
            ),
            'expenseByCategory' => $this->categoryBreakdown(
                $expenses->map(fn (Expense $expense) => [
                    'category' => trim((string) ($expense->category ?: 'Χωρίς κατηγορία')) ?: 'Χωρίς κατηγορία',
                    'amount' => (float) $expense->amount,
                ])
            ),
        ];
    }

    /**
     * @param  Collection<int, Revenue>  $revenues
     * @param  Collection<int, Expense>  $expenses
     * @return list<array{key: string, label: string, revenue: float, expense: float}>
     */
    private function monthlySeries(
        Collection $revenues,
        Collection $expenses,
        Carbon $anchor,
    ): array {
        $series = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = $anchor->copy()->startOfMonth()->subMonths($i);
            $key = $month->format('Y-m');
            $label = $month->locale('el')->translatedFormat('M Y');

            $revenue = (float) $revenues
                ->filter(fn (Revenue $item) => str_starts_with($this->revenueDate($item), $key))
                ->sum(fn (Revenue $item) => (float) $item->amount);
            $expense = (float) $expenses
                ->filter(fn (Expense $item) => str_starts_with($this->expenseDate($item), $key))
                ->sum(fn (Expense $item) => (float) $item->amount);

            $series[] = [
                'key' => $key,
                'label' => mb_convert_case($label, MB_CASE_TITLE, 'UTF-8'),
                'revenue' => round($revenue, 2),
                'expense' => round($expense, 2),
            ];
        }

        return $series;
    }

    /**
     * @param  Collection<int, array{category: string, amount: float}>  $rows
     * @return list<array{category: string, amount: float}>
     */
    private function categoryBreakdown(Collection $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $category = $row['category'];
            $grouped[$category] = ($grouped[$category] ?? 0) + (float) $row['amount'];
        }

        $list = [];
        foreach ($grouped as $category => $amount) {
            if ($amount <= 0) {
                continue;
            }
            $list[] = [
                'category' => $category,
                'amount' => round($amount, 2),
            ];
        }

        usort($list, fn (array $a, array $b) => $b['amount'] <=> $a['amount']);

        return array_values($list);
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

    private function revenueDate(Revenue $revenue): string
    {
        $meta = $revenue->meta ?? [];
        $raw = $meta['date'] ?? $meta['occurredOn'] ?? null;
        if (is_string($raw) && preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) {
            return substr($raw, 0, 10);
        }

        return optional($revenue->occurred_on)?->toDateString() ?? '';
    }

    private function expenseDate(Expense $expense): string
    {
        $meta = $expense->meta ?? [];
        $raw = $meta['date'] ?? $meta['occurredOn'] ?? null;
        if (is_string($raw) && preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) {
            return substr($raw, 0, 10);
        }

        return optional($expense->occurred_on)?->toDateString() ?? '';
    }

    private function linkedTransactionId(Revenue $revenue): ?string
    {
        $id = $revenue->meta['linkedTransactionId'] ?? $revenue->meta['linked_transaction_id'] ?? null;

        return $id !== null && $id !== '' ? (string) $id : null;
    }

    private function isRevenuePaid(Revenue $revenue): bool
    {
        $status = $revenue->meta['paymentStatus'] ?? $revenue->meta['payment_status'] ?? 'paid';

        return $status === 'paid' || $status === true || $status === 1 || $status === '1';
    }

    private function isRevenuePending(Revenue $revenue): bool
    {
        $status = strtolower((string) ($revenue->meta['paymentStatus'] ?? $revenue->meta['payment_status'] ?? ''));

        return in_array($status, ['pending', 'overdue'], true);
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

    private function rentalDate(RentalBooking $booking): string
    {
        $meta = $booking->meta ?? [];
        foreach (['paidOn', 'paidAt', 'date'] as $key) {
            $raw = $meta[$key] ?? null;
            if (is_string($raw) && preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) {
                return substr($raw, 0, 10);
            }
        }

        return optional($booking->starts_at)?->toDateString() ?? '';
    }

    /**
     * @param  Collection<int, Revenue>  $revenues
     * @param  Collection<int, AthleteTransaction>  $payments
     * @param  Collection<int, RentalBooking>  $rentals
     * @param  Collection<int, Athlete>  $athletes
     * @return array<string, mixed>
     */
    private function buildDailyTill(
        string $date,
        Collection $revenues,
        Collection $payments,
        Collection $rentals,
        Collection $athletes,
    ): array {
        $methodOrder = ['cash', 'card', 'online', 'transfer', 'other'];
        $methods = [];
        foreach ($methodOrder as $key) {
            $methods[$key] = [
                'key' => $key,
                'label' => $this->tillMethodLabel($key),
                'collections' => 0.0,
                'collectionCount' => 0,
                'linkedIncome' => 0.0,
                'otherIncome' => 0.0,
                'income' => 0.0,
            ];
        }

        $bump = function (string $key, float $amount, string $kind) use (&$methods): void {
            if (! isset($methods[$key])) {
                $key = 'other';
            }
            $row = &$methods[$key];
            if ($kind === 'collections') {
                $row['collections'] = round($row['collections'] + $amount, 2);
                $row['collectionCount']++;
            } elseif ($kind === 'linkedIncome') {
                $row['linkedIncome'] = round($row['linkedIncome'] + $amount, 2);
                $row['income'] = round($row['income'] + $amount, 2);
            } else {
                $row['otherIncome'] = round($row['otherIncome'] + $amount, 2);
                $row['income'] = round($row['income'] + $amount, 2);
            }
        };

        $collections = 0.0;
        $collectionCount = 0;
        $missingReceipts = [];
        $athletesById = $athletes->keyBy('id');

        foreach ($payments as $payment) {
            if ($this->paymentDate($payment) !== $date) {
                continue;
            }
            $amount = round((float) $payment->amount, 2);
            if ($amount <= 0) {
                continue;
            }
            $collections = round($collections + $amount, 2);
            $collectionCount++;
            $bump($this->tillMethodKey($payment), $amount, 'collections');

            if (blank($payment->receipt_number)) {
                $athlete = $athletesById->get($payment->athlete_id);
                $name = $athlete
                    ? trim($athlete->last_name.' '.$athlete->first_name)
                    : 'Αθλητής';
                $missingReceipts[] = [
                    'id' => (string) $payment->id,
                    'label' => 'Συνδρομή · '.$name,
                    'amount' => $amount,
                ];
            }
        }

        foreach ($rentals as $booking) {
            if (! $this->isRentalCollected($booking) || (float) $booking->amount <= 0) {
                continue;
            }
            if ($this->rentalDate($booking) !== $date) {
                continue;
            }
            $amount = round((float) $booking->amount, 2);
            $collections = round($collections + $amount, 2);
            $collectionCount++;
            $meta = $booking->meta ?? [];
            $method = (string) ($meta['paymentMethod'] ?? $meta['payment_method'] ?? '');
            $bump($this->tillMethodKeyFromString($method), $amount, 'collections');
        }

        $linkedIncome = 0.0;
        $otherRevenues = 0.0;
        $incomeCount = 0;
        foreach ($revenues as $revenue) {
            if ($this->revenueDate($revenue) !== $date) {
                continue;
            }
            // Legacy dailyTill: exclude pending revenues from income.
            if ($this->isRevenuePending($revenue)) {
                continue;
            }
            $amount = round((float) $revenue->amount, 2);
            if ($amount <= 0) {
                continue;
            }
            $incomeCount++;
            $linked = $this->linkedTransactionId($revenue) !== null
                || filled($revenue->meta['linkedRentalBookingId'] ?? null);
            if ($linked) {
                $linkedIncome = round($linkedIncome + $amount, 2);
                $bump(
                    $this->tillMethodKeyFromString((string) ($revenue->meta['paymentMethod'] ?? '')),
                    $amount,
                    'linkedIncome',
                );
            } else {
                $otherRevenues = round($otherRevenues + $amount, 2);
                $bump(
                    $this->tillMethodKeyFromString((string) ($revenue->meta['paymentMethod'] ?? '')),
                    $amount,
                    'otherIncome',
                );
            }
        }

        $contractual = round($collections, 2);
        // Legacy: incomeTotal = Σ day revenues (linked + other), not collections + income.
        $totalIncome = round($linkedIncome + $otherRevenues, 2);

        $receipts = round((float) $payments
            ->filter(fn (AthleteTransaction $tx) => $this->paymentDate($tx) === $date && filled($tx->receipt_number))
            ->sum(fn (AthleteTransaction $tx) => (float) $tx->amount), 2);

        $receiptsCount = $payments
            ->filter(fn (AthleteTransaction $tx) => $this->paymentDate($tx) === $date && filled($tx->receipt_number))
            ->count();

        $withoutReceipt = round(max(0, $collections - $receipts), 2);

        $methodRows = [];
        foreach ($methodOrder as $key) {
            $row = $methods[$key];
            if ($row['collectionCount'] > 0 || $row['linkedIncome'] > 0 || $row['otherIncome'] > 0) {
                $methodRows[] = $row;
            }
        }

        return [
            'date' => $date,
            'collections' => $contractual,
            'collectionCount' => $collectionCount,
            'linkedIncome' => $linkedIncome,
            'contractual' => $contractual,
            'otherRevenues' => $otherRevenues,
            'totalIncome' => $totalIncome,
            'incomeCount' => $incomeCount,
            'receipts' => $receipts,
            'receiptsCount' => $receiptsCount,
            'withoutReceipt' => $withoutReceipt,
            'hasActivity' => $totalIncome > 0.00001 || $collections > 0.00001,
            'methods' => $methodRows,
            'missingReceipts' => $missingReceipts,
        ];
    }

    private function tillMethodKey(AthleteTransaction $payment): string
    {
        return $this->tillMethodKeyFromString((string) ($payment->payment_method ?? $payment->meta['paymentMethod'] ?? ''));
    }

    private function tillMethodKeyFromString(string $method): string
    {
        $method = strtolower(trim($method));

        return match ($method) {
            'cash' => 'cash',
            'card' => 'card',
            'transfer' => 'transfer',
            'viva', 'stripe', 'eurobank', 'online' => 'online',
            default => 'other',
        };
    }

    private function tillMethodLabel(string $key): string
    {
        return match ($key) {
            'cash' => 'Μετρητά',
            'card' => 'POS',
            'online' => 'Online (Viva / Stripe / Eurobank)',
            'transfer' => 'Κατάθεση',
            default => 'Άλλο / χωρίς τρόπο',
        };
    }
}
