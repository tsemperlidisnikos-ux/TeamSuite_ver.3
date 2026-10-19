<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\InteractsWithClubResource;
use App\Http\Controllers\Controller;
use App\Models\ReceiptIssue;
use App\Models\ReceiptSeries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceiptController extends Controller
{
    use InteractsWithClubResource;

    public function index(Request $request): Response
    {
        $tab = (string) $request->query('tab', 'registry');
        if (! in_array($tab, ['registry', 'numbering'], true)) {
            $tab = 'registry';
        }

        $filters = $this->filtersFrom($request);
        $issues = $this->allIssues($request);
        $filtered = $this->applyFilters($issues, $filters);

        $valid = $filtered->where('is_cancelled', false)->values();
        $voided = $filtered->where('is_cancelled', true)->values();
        $emailed = $valid->filter(fn (array $row) => filled($row['emailed_at']))->values();
        $totalAmount = round((float) $valid->sum('amount'), 2);

        $bySeries = $filtered
            ->groupBy('series')
            ->map(function ($rows, $series) {
                $validRows = $rows->where('is_cancelled', false);

                return [
                    'series' => (string) $series,
                    'issued' => $validRows->count(),
                    'cancelled' => $rows->where('is_cancelled', true)->count(),
                    'amount' => round((float) $validRows->sum('amount'), 2),
                ];
            })
            ->sortBy('series', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $byMonth = $valid
            ->groupBy(fn (array $row) => substr((string) $row['issued_date'], 0, 7) ?: '—')
            ->map(fn ($rows, $month) => [
                'month' => (string) $month,
                'count' => $rows->count(),
                'amount' => round((float) $rows->sum('amount'), 2),
            ])
            ->sortByDesc('month')
            ->values();

        $byKind = $valid
            ->groupBy('kind')
            ->map(fn ($rows, $kind) => [
                'kind' => (string) $kind,
                'kind_label' => $this->kindLabel((string) $kind),
                'count' => $rows->count(),
                'amount' => round((float) $rows->sum('amount'), 2),
            ])
            ->values();

        return Inertia::render('Admin/Receipts/Index', [
            'tab' => $tab,
            'filters' => $filters,
            'issues' => $filtered,
            'seriesOptions' => $issues->pluck('series')->unique()->sort()->values(),
            'summary' => [
                'issued_count' => $valid->count(),
                'total_amount' => $totalAmount,
                'emailed_count' => $emailed->count(),
                'cancelled_count' => $voided->count(),
            ],
            'bySeries' => $bySeries,
            'byMonth' => $byMonth,
            'byKind' => $byKind,
            'receiptSeries' => $this->clubQuery(ReceiptSeries::class, $request)
                ->orderBy('series')
                ->orderBy('range_from')
                ->get(['id', 'series', 'range_from', 'range_to', 'next_number', 'is_active']),
        ]);
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $issue = $this->clubQuery(ReceiptIssue::class, $request)->findOrFail($id);

        if ($issue->status !== 'cancelled' && $issue->cancelled_at === null) {
            $issue->fill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ])->save();
        }

        return redirect()
            ->route('admin.receipts.index', array_filter([
                'tab' => $request->input('tab', 'registry'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'series' => $request->input('series'),
                'kind' => $request->input('kind'),
                'status' => $request->input('status'),
                'q' => $request->input('q'),
            ], fn ($value) => $value !== null && $value !== ''))
            ->with('success', 'Η απόδειξη ακυρώθηκε.');
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $filtered = $this->applyFilters($this->allIssues($request), $this->filtersFrom($request));
        $filename = 'apodeixeis_'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($filtered) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Ημερομηνία',
                'Σειρά',
                'Αριθμός',
                'Κατάσταση',
                'Είδος',
                'Παραλήπτης',
                'Αιτιολογία',
                'Ποσό',
                'Email',
            ], ';');

            foreach ($filtered as $row) {
                fputcsv($out, [
                    $row['issued_date'] ?? '',
                    $row['series'] ?? '',
                    $row['number'] ?? '',
                    ($row['is_cancelled'] ?? false) ? 'Ακυρωμένη' : 'Εκδοθείσα',
                    $row['kind_label'] ?? '',
                    $row['received_from'] ?? '',
                    $row['reason'] ?? '',
                    number_format((float) ($row['amount'] ?? 0), 2, ',', ''),
                    filled($row['emailed_at'] ?? null) ? 'Ναι' : 'Όχι',
                ], ';');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{date_from: string, date_to: string, series: string, kind: string, status: string, q: string}
     */
    private function filtersFrom(Request $request): array
    {
        return [
            'date_from' => (string) $request->query('date_from', ''),
            'date_to' => (string) $request->query('date_to', ''),
            'series' => (string) $request->query('series', ''),
            'kind' => (string) $request->query('kind', 'all'),
            'status' => (string) $request->query('status', 'all'),
            'q' => (string) $request->query('q', ''),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function allIssues(Request $request): Collection
    {
        return $this->clubQuery(ReceiptIssue::class, $request)
            ->orderByDesc('issued_at')
            ->orderBy('series')
            ->orderByDesc('number')
            ->get()
            ->map(fn (ReceiptIssue $issue) => $this->mapIssue($issue))
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $issues
     * @param  array{date_from: string, date_to: string, series: string, kind: string, status: string, q: string}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function applyFilters(Collection $issues, array $filters): Collection
    {
        return $issues->filter(function (array $row) use ($filters) {
            if ($filters['date_from'] !== '' && ($row['issued_date'] ?? '') < $filters['date_from']) {
                return false;
            }
            if ($filters['date_to'] !== '' && ($row['issued_date'] ?? '') > $filters['date_to']) {
                return false;
            }
            if ($filters['series'] !== '' && $row['series'] !== $filters['series']) {
                return false;
            }
            if ($filters['kind'] !== 'all' && $row['kind'] !== $filters['kind']) {
                return false;
            }
            if ($filters['status'] === 'valid' && $row['is_cancelled']) {
                return false;
            }
            if ($filters['status'] === 'voided' && ! $row['is_cancelled']) {
                return false;
            }
            if ($filters['q'] !== '') {
                $hay = mb_strtolower(
                    $row['label'].' '.$row['received_from'].' '.$row['reason'].' '.$row['kind_label'],
                    'UTF-8'
                );
                if (! str_contains($hay, mb_strtolower($filters['q'], 'UTF-8'))) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapIssue(ReceiptIssue $issue): array
    {
        $meta = is_array($issue->meta) ? $issue->meta : [];
        $kind = $this->inferKind($meta, $issue);
        $isCancelled = $issue->status === 'cancelled'
            || $issue->status === 'voided'
            || $issue->cancelled_at !== null;
        $emailedAt = isset($meta['emailedAt']) ? (string) $meta['emailedAt'] : null;
        $amount = (float) ($meta['amount'] ?? 0);
        $receivedFrom = trim((string) ($meta['receivedFrom'] ?? ''));
        $reason = trim((string) ($meta['reason'] ?? ($meta['voidReason'] ?? '')));
        $issuedDate = $issue->issued_at?->format('Y-m-d') ?? '';

        return [
            'id' => $issue->id,
            'series' => (string) ($issue->series ?? ''),
            'number' => (int) $issue->number,
            'label' => $this->formatLabel((string) ($issue->series ?? ''), (int) $issue->number),
            'issued_at' => $issue->issued_at?->toIso8601String(),
            'issued_date' => $issuedDate,
            'cancelled_at' => $issue->cancelled_at?->toIso8601String(),
            'is_cancelled' => $isCancelled,
            'emailed_at' => $emailedAt,
            'kind' => $kind,
            'kind_label' => $this->kindLabel($kind),
            'amount' => $amount,
            'received_from' => $receivedFrom,
            'reason' => $reason,
            'status_label' => $isCancelled
                ? 'Ακυρωμένη'
                : ($emailedAt ? 'Εκδοθείσα · email' : 'Εκδοθείσα'),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function inferKind(array $meta, ReceiptIssue $issue): string
    {
        $kind = $meta['kind'] ?? null;
        if (in_array($kind, ['subscription', 'rental', 'other'], true)) {
            return $kind;
        }

        $txId = (string) ($issue->athlete_transaction_id ?? ($meta['transactionId'] ?? ''));
        if (str_starts_with($txId, 'rent_')) {
            return 'rental';
        }
        if (filled($meta['athleteId'] ?? null) || filled($issue->athlete_transaction_id)) {
            return 'subscription';
        }

        return 'other';
    }

    private function kindLabel(string $kind): string
    {
        return match ($kind) {
            'rental' => 'Ενοικίαση γηπέδου',
            'subscription' => 'Συνδρομή / πληρωμή αθλητή',
            default => 'Άλλο',
        };
    }

    private function formatLabel(string $series, int $number): string
    {
        $series = trim($series);

        return $series !== '' ? "{$series}-{$number}" : (string) $number;
    }
}
