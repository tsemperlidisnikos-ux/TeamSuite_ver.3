<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\InteractsWithClubResource;
use App\Http\Controllers\Controller;
use App\Models\Association;
use App\Models\AthleteTransaction;
use App\Models\BudgetLine;
use App\Models\CashAccount;
use App\Models\ClosedFinanceMonth;
use App\Models\Expense;
use App\Models\Revenue;
use App\Models\Sport;
use App\Services\Admin\FinanceAnalysisMetrics;
use App\Services\Admin\FinancePageMetrics;
use App\Services\Platform\PlatformConfigService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    use InteractsWithClubResource;

    public function index(
        Request $request,
        PlatformConfigService $config,
        FinanceAnalysisMetrics $analysis,
        FinancePageMetrics $pageMetrics,
    ): Response {
        $catalog = $config->financeTabsCatalog();
        $enabled = $config->get()['financeTabs'] ?? array_column($catalog, 'id');
        $tabs = array_values(array_filter(
            $catalog,
            fn (array $tab) => in_array($tab['id'], $enabled, true)
        ));
        if ($tabs === []) {
            $tabs = $catalog;
        }

        $tabIds = array_column($tabs, 'id');
        $tab = (string) $request->query('tab', 'analysis');
        if (! in_array($tab, $tabIds, true)) {
            $tab = $tabIds[0] ?? 'analysis';
        }

        $dateRaw = (string) $request->query('date', now()->toDateString());
        try {
            $day = Carbon::parse($dateRaw)->startOfDay();
        } catch (\Throwable) {
            $day = now()->startOfDay();
        }

        $clubId = $this->clubId($request);
        $analysisData = $analysis->forClub($clubId, $day);

        $incomeCategories = $config->get()['incomeCategories'] ?? [];
        $expenseCategories = $config->get()['expenseCategories'] ?? [];
        $revenueSubcategory = (string) $request->query(
            'revenue_subcategory',
            $incomeCategories[0] ?? FinancePageMetrics::ATHLETE_SUBSCRIPTIONS,
        );
        $expenseSubcategory = (string) $request->query(
            'expense_subcategory',
            $expenseCategories[0] ?? 'ΑΓΩΝΕΣ',
        );

        $revenuesQuery = $this->clubQuery(Revenue::class, $request)->latest()->limit(10_000)->get();
        $expensesQuery = $this->clubQuery(Expense::class, $request)->latest()->limit(10_000)->get();
        $payments = AthleteTransaction::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->where('type', 'payment')
            ->latest()
            ->limit(10_000)
            ->get();

        $seasonStart = (int) $request->query(
            'season',
            $pageMetrics->currentSeasonStartYear($day),
        );

        $balanceFromRaw = (string) $request->query('balance_from', '');
        $balanceToRaw = (string) $request->query('balance_to', '');
        $seasonBounds = $pageMetrics->seasonBounds($seasonStart);
        try {
            $balanceFrom = $balanceFromRaw !== ''
                ? Carbon::parse($balanceFromRaw)->startOfDay()
                : Carbon::parse($seasonBounds['from'])->startOfDay();
        } catch (\Throwable) {
            $balanceFrom = Carbon::parse($seasonBounds['from'])->startOfDay();
        }
        try {
            $balanceTo = $balanceToRaw !== ''
                ? Carbon::parse($balanceToRaw)->startOfDay()
                : Carbon::parse($seasonBounds['to'])->startOfDay();
        } catch (\Throwable) {
            $balanceTo = Carbon::parse($seasonBounds['to'])->startOfDay();
        }

        $reportsFromRaw = (string) $request->query('reports_from', $seasonBounds['from']);
        $reportsToRaw = (string) $request->query('reports_to', $seasonBounds['to']);
        try {
            $reportsFrom = Carbon::parse($reportsFromRaw)->startOfDay();
        } catch (\Throwable) {
            $reportsFrom = Carbon::parse($seasonBounds['from'])->startOfDay();
        }
        try {
            $reportsTo = Carbon::parse($reportsToRaw)->startOfDay();
        } catch (\Throwable) {
            $reportsTo = Carbon::parse($seasonBounds['to'])->startOfDay();
        }

        $accounts = $this->clubQuery(CashAccount::class, $request)->orderBy('name')->get();
        $closedMonths = $this->clubQuery(ClosedFinanceMonth::class, $request)
            ->orderByDesc('year_month')
            ->pluck('year_month')
            ->values()
            ->all();

        $associationOptions = $this->clubQuery(Association::class, $request)
            ->orderBy('name')
            ->get(['id', 'name', 'meta'])
            ->map(fn (Association $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'active' => (bool) ($a->meta['active'] ?? true),
            ])
            ->values()
            ->all();

        $sportOptions = $this->clubQuery(Sport::class, $request)
            ->orderBy('name')
            ->pluck('name')
            ->values()
            ->all();

        return Inertia::render('Admin/Finance/Index', [
            'tab' => $tab,
            'tabs' => $tabs,
            'date' => $day->toDateString(),
            'analysis' => $analysisData,
            'revenues' => $revenuesQuery,
            'expenses' => $expensesQuery,
            'revenueRows' => $pageMetrics->revenueRows($revenuesQuery, $payments, $revenueSubcategory),
            'expenseRows' => $pageMetrics->expenseRows($expensesQuery, $expenseSubcategory),
            'revenueSubcategory' => $revenueSubcategory,
            'expenseSubcategory' => $expenseSubcategory,
            'accounts' => $accounts,
            'closedMonths' => $closedMonths,
            'balance' => $pageMetrics->balance($clubId, $balanceFrom, $balanceTo),
            'reports' => $pageMetrics->reports($clubId, $reportsFrom, $reportsTo),
            'budget' => $pageMetrics->budget($clubId, $seasonStart),
            'associationOptions' => $associationOptions,
            'sportOptions' => $sportOptions,
            'categories' => [
                'income' => array_values(array_unique(array_merge(
                    [FinancePageMetrics::ATHLETE_SUBSCRIPTIONS],
                    $incomeCategories,
                ))),
                'expense' => $expenseCategories,
            ],
        ]);
    }

    public function storeRevenue(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0',
            'category' => 'nullable|string|max:64',
            'subcategory' => 'nullable|string|max:120',
            'description' => 'nullable|string|max:255',
            'occurred_on' => 'nullable|date',
            'club_name' => 'nullable|string|max:160',
            'sport' => 'nullable|string|max:120',
            'payment_method' => 'nullable|string|max:40',
            'cash_account_id' => 'nullable|uuid',
            'vat_rate' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:2000',
        ]);
        $this->assertMonthOpen($request, $data['occurred_on'] ?? now()->toDateString());

        $payload = [
            'club_id' => $this->clubId($request),
            'created_by' => $request->user()->id,
            'amount' => $data['amount'],
            'category' => $data['category'] ?? $data['subcategory'] ?? null,
            'description' => $data['description'] ?? null,
            'occurred_on' => $data['occurred_on'] ?? now()->toDateString(),
            'cash_account_id' => $data['cash_account_id'] ?? null,
            'meta' => [
                'subcategory' => $data['subcategory'] ?? ($data['category'] ?? null),
                'clubName' => $data['club_name'] ?? '',
                'sport' => $data['sport'] ?? '',
                'paymentMethod' => $data['payment_method'] ?? '',
                'vatRate' => (float) ($data['vat_rate'] ?? 0),
                'notes' => $data['notes'] ?? '',
            ],
        ];

        Revenue::query()->withoutGlobalScopes()->create($payload);

        return back()->with('success', 'Έσοδο καταχωρήθηκε.');
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount' => 'required_without:match_details|nullable|numeric|min:0',
            'category' => 'nullable|string|max:64',
            'description' => 'nullable|string|max:255',
            'occurred_on' => 'nullable|date',
            'club_name' => 'nullable|string|max:160',
            'sport' => 'nullable|string|max:120',
            'subcategory' => 'nullable|string|max:120',
            'payment_method' => 'nullable|string|max:40',
            'cash_account_id' => 'nullable|uuid',
            'vat_rate' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:2000',
            'match_details' => 'nullable|array',
            'match_details.sport' => 'nullable|string|max:120',
            'match_details.category' => 'nullable|string|max:120',
            'match_details.teams' => 'nullable|string|max:255',
            'match_details.referees' => 'nullable|numeric|min:0',
            'match_details.judges' => 'nullable|numeric|min:0',
            'match_details.commissioner' => 'nullable|numeric|min:0',
            'match_details.observer' => 'nullable|numeric|min:0',
            'match_details.doctor' => 'nullable|numeric|min:0',
            'match_details.travelAllowance' => 'nullable|numeric|min:0',
            'match_details.travelReferees' => 'nullable|numeric|min:0',
            'match_details.travelJudges' => 'nullable|numeric|min:0',
            'match_details.travelCommissioner' => 'nullable|numeric|min:0',
            'match_details.travelObserver' => 'nullable|numeric|min:0',
            'match_details.transportBus' => 'nullable|numeric|min:0',
            'match_details.transportPlane' => 'nullable|numeric|min:0',
            'match_details.transportShip' => 'nullable|numeric|min:0',
            'match_details.transportOther' => 'nullable|numeric|min:0',
            'match_details.accommodation' => 'nullable|numeric|min:0',
            'match_details.food' => 'nullable|numeric|min:0',
            'match_details.otherExpenses' => 'nullable|numeric|min:0',
            'match_details.otherExpensesNote' => 'nullable|string|max:255',
        ]);

        $matchDetails = null;
        if (! empty($data['match_details'])) {
            $matchDetails = $this->normalizeMatchExpenseDetails($data['match_details']);

            if (trim((string) ($data['club_name'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'club_name' => 'Επιλέξτε σωματείο.',
                ]);
            }
            if ($matchDetails['sport'] === '') {
                throw ValidationException::withMessages([
                    'match_details.sport' => 'Επιλέξτε άθλημα.',
                ]);
            }
            if ($matchDetails['category'] === '') {
                throw ValidationException::withMessages([
                    'match_details.category' => 'Συμπληρώστε κατηγορία αγώνα.',
                ]);
            }
            if ($matchDetails['teams'] === '') {
                throw ValidationException::withMessages([
                    'match_details.teams' => 'Συμπληρώστε ομάδες.',
                ]);
            }

            $amount = $this->matchExpenseTotal($matchDetails);
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Το σύνολο εξόδων αγώνα πρέπει να είναι θετικό.',
                ]);
            }

            $data['amount'] = $amount;
            $data['description'] = $data['description'] ?? ('Αγώνας: '.$matchDetails['teams']);
            $data['category'] = $data['category'] ?: 'ΑΓΩΝΕΣ';
            $data['subcategory'] = 'ΑΓΩΝΕΣ';
            $data['sport'] = $matchDetails['sport'];
        }

        $occurredOn = $data['occurred_on'] ?? now()->toDateString();
        $this->assertMonthOpen($request, $occurredOn);

        $meta = [
            'subcategory' => $data['subcategory'] ?? ($data['category'] ?? null),
            'clubName' => $data['club_name'] ?? '',
            'sport' => $data['sport'] ?? '',
            'paymentMethod' => $data['payment_method'] ?? '',
            'vatRate' => (float) ($data['vat_rate'] ?? 0),
            'notes' => $data['notes'] ?? '',
        ];
        if ($matchDetails !== null) {
            $meta['matchDetails'] = $matchDetails;
        }

        Expense::query()->withoutGlobalScopes()->create([
            'club_id' => $this->clubId($request),
            'created_by' => $request->user()->id,
            'amount' => $data['amount'],
            'category' => $data['category'] ?? null,
            'description' => $data['description'] ?? null,
            'occurred_on' => $occurredOn,
            'cash_account_id' => $data['cash_account_id'] ?? null,
            'meta' => $meta,
        ]);

        return back()->with('success', 'Έξοδο καταχωρήθηκε.');
    }

    public function storeCashAccount(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'opening_balance' => 'nullable|numeric',
            'kind' => 'nullable|in:cash,bank,other',
        ]);

        CashAccount::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $this->clubId($request),
            'name' => $data['name'],
            'opening_balance' => $data['opening_balance'] ?? 0,
            'is_active' => true,
            'meta' => [
                'kind' => $data['kind'] ?? 'cash',
            ],
        ]);

        return redirect()
            ->route('admin.finance.index', ['tab' => 'accounts'])
            ->with('success', 'Το ταμείο δημιουργήθηκε.');
    }

    public function closeMonth(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'year_month' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $clubId = $this->clubId($request);
        $yearMonth = $data['year_month'];

        ClosedFinanceMonth::query()->withoutGlobalScopes()->updateOrCreate(
            [
                'club_id' => $clubId,
                'year_month' => $yearMonth,
            ],
            [
                'closed_by' => $request->user()->id,
                'closed_at' => now(),
            ],
        );

        return redirect()
            ->route('admin.finance.index', ['tab' => 'accounts'])
            ->with('success', "Ο μήνας {$yearMonth} κλείστηκε.");
    }

    public function storeBudgetLine(Request $request): RedirectResponse
    {
        $data = $this->validatedBudgetLine($request);
        $seasonStart = (int) $data['season_start'];

        BudgetLine::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $this->clubId($request),
            'label' => $data['subcategory'],
            'year' => $seasonStart,
            'amount' => $data['amount'],
            'meta' => $this->budgetLineMeta($data, $seasonStart),
        ]);

        return redirect()
            ->route('admin.finance.index', [
                'tab' => 'budget',
                'season' => $seasonStart,
            ])
            ->with('success', 'Η γραμμή προϋπολογισμού αποθηκεύτηκε.');
    }

    public function updateBudgetLine(Request $request, string $id): RedirectResponse
    {
        $data = $this->validatedBudgetLine($request);
        $seasonStart = (int) $data['season_start'];

        $line = $this->clubQuery(BudgetLine::class, $request)->findOrFail($id);
        $line->update([
            'label' => $data['subcategory'],
            'year' => $seasonStart,
            'amount' => $data['amount'],
            'meta' => $this->budgetLineMeta($data, $seasonStart),
        ]);

        return redirect()
            ->route('admin.finance.index', [
                'tab' => 'budget',
                'season' => $seasonStart,
            ])
            ->with('success', 'Η γραμμή προϋπολογισμού ενημερώθηκε.');
    }

    public function destroyBudgetLine(Request $request, string $id): RedirectResponse
    {
        $line = $this->clubQuery(BudgetLine::class, $request)->findOrFail($id);
        $seasonStart = (int) (($line->meta['seasonStart'] ?? null) ?: $line->year ?: now()->year);
        $line->delete();

        return redirect()
            ->route('admin.finance.index', [
                'tab' => 'budget',
                'season' => $seasonStart,
            ])
            ->with('success', 'Η γραμμή προϋπολογισμού διαγράφηκε.');
    }

    public function exportReports(
        Request $request,
        FinancePageMetrics $pageMetrics,
    ): StreamedResponse {
        [$from, $to] = $this->resolveDateRange(
            $request,
            'reports_from',
            'reports_to',
            $pageMetrics,
        );
        $reports = $pageMetrics->reports($this->clubId($request), $from, $to);

        $headers = ['Μετρική', 'Τιμή'];
        $rows = [
            ['Από', $reports['dateFrom']],
            ['Έως', $reports['dateTo']],
            ['Σύνολο εσόδων', number_format($reports['totalRevenue'], 2, '.', '')],
            ['Σύνολο εξόδων', number_format($reports['totalExpense'], 2, '.', '')],
            ['Υπόλοιπο', number_format($reports['balance'], 2, '.', '')],
            ['Οφειλές αθλητών', number_format($reports['athleteDebts'], 2, '.', '')],
            ['Έσοδα σήμερα', number_format($reports['todayRevenue'], 2, '.', '')],
            ['Έξοδα σήμερα', number_format($reports['todayExpense'], 2, '.', '')],
            ['Υπόλοιπο σήμερα', number_format($reports['todayBalance'], 2, '.', '')],
        ];

        return $this->csvDownload(
            'finance-reports-'.$reports['dateFrom'].'-'.$reports['dateTo'].'.csv',
            $headers,
            $rows,
        );
    }

    public function exportBalance(
        Request $request,
        FinancePageMetrics $pageMetrics,
    ): StreamedResponse {
        [$from, $to] = $this->resolveDateRange(
            $request,
            'balance_from',
            'balance_to',
            $pageMetrics,
        );
        $balance = $pageMetrics->balance($this->clubId($request), $from, $to);

        $headers = ['Ενότητα', 'Ετικέτα', 'Κινήσεις', 'Ποσό'];
        $rows = [
            ['Σύνοψη', 'Έσοδα', '', number_format($balance['revenue'], 2, '.', '')],
            ['Σύνοψη', 'Έξοδα', '', number_format($balance['expense'], 2, '.', '')],
            ['Σύνοψη', 'Υπόλοιπο', '', number_format($balance['net'], 2, '.', '')],
            ['Περίοδος', $balance['from'].' - '.$balance['to'], '', ''],
        ];
        foreach ($balance['collectionMethods'] as $method) {
            $rows[] = [
                'Εισπράξεις ανά τρόπο',
                $method['label'],
                (string) $method['count'],
                number_format($method['amount'], 2, '.', ''),
            ];
        }
        foreach ($balance['incomeRows'] as $row) {
            $rows[] = [
                'Έσοδα',
                trim($row['sport'].' / '.$row['category']),
                '',
                number_format($row['total'], 2, '.', ''),
            ];
        }
        foreach ($balance['expenseRows'] as $row) {
            $rows[] = [
                'Έξοδα',
                trim($row['sport'].' / '.$row['category']),
                '',
                number_format($row['total'], 2, '.', ''),
            ];
        }

        return $this->csvDownload(
            'finance-balance-'.$balance['from'].'-'.$balance['to'].'.csv',
            $headers,
            $rows,
        );
    }

    /**
     * @return array{season_start: int|string, type: string, subcategory: string, amount: float|int|string, club_name?: string|null, sport?: string|null}
     */
    private function validatedBudgetLine(Request $request): array
    {
        return $request->validate([
            'season_start' => 'required|integer|min:2000|max:2100',
            'type' => 'required|in:income,expense',
            'subcategory' => 'required|string|max:120',
            'amount' => 'required|numeric|min:0',
            'club_name' => 'nullable|string|max:160',
            'sport' => 'nullable|string|max:120',
        ]);
    }

    /**
     * @param  array{type: string, subcategory: string, club_name?: string|null, sport?: string|null}  $data
     * @return array{type: string, subcategory: string, seasonStart: int, clubName: string, sport: string}
     */
    private function budgetLineMeta(array $data, int $seasonStart): array
    {
        return [
            'type' => $data['type'],
            'subcategory' => $data['subcategory'],
            'seasonStart' => $seasonStart,
            'clubName' => $data['club_name'] ?? '',
            'sport' => $data['sport'] ?? '',
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDateRange(
        Request $request,
        string $fromKey,
        string $toKey,
        FinancePageMetrics $pageMetrics,
    ): array {
        $seasonStart = $pageMetrics->currentSeasonStartYear();
        $bounds = $pageMetrics->seasonBounds($seasonStart);
        $fromRaw = (string) $request->query($fromKey, $bounds['from']);
        $toRaw = (string) $request->query($toKey, $bounds['to']);

        try {
            $from = Carbon::parse($fromRaw)->startOfDay();
        } catch (\Throwable) {
            $from = Carbon::parse($bounds['from'])->startOfDay();
        }
        try {
            $to = Carbon::parse($toRaw)->startOfDay();
        } catch (\Throwable) {
            $to = Carbon::parse($bounds['to'])->startOfDay();
        }

        return [$from, $to];
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    private function csvDownload(string $filename, array $headers, array $rows): StreamedResponse
    {
        $escape = static function (string $value): string {
            $value = str_replace('"', '""', $value);

            return '"'.$value.'"';
        };

        $lines = [implode(';', array_map($escape, $headers))];
        foreach ($rows as $row) {
            $lines[] = implode(';', array_map(
                static fn ($cell) => $escape((string) $cell),
                $row,
            ));
        }
        $content = "\xEF\xBB\xBF".implode("\n", $lines);

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array{
     *     sport: string,
     *     category: string,
     *     teams: string,
     *     referees: float,
     *     judges: float,
     *     commissioner: float,
     *     observer: float,
     *     doctor: float,
     *     travelAllowance: float,
     *     travelReferees: float,
     *     travelJudges: float,
     *     travelCommissioner: float,
     *     travelObserver: float,
     *     transportBus: float,
     *     transportPlane: float,
     *     transportShip: float,
     *     transportOther: float,
     *     accommodation: float,
     *     food: float,
     *     otherExpenses: float,
     *     otherExpensesNote: string
     * }
     */
    private function normalizeMatchExpenseDetails(array $details): array
    {
        $travelReferees = (float) ($details['travelReferees'] ?? 0);
        $travelJudges = (float) ($details['travelJudges'] ?? 0);
        $travelCommissioner = (float) ($details['travelCommissioner'] ?? 0);
        $travelObserver = (float) ($details['travelObserver'] ?? 0);
        $breakdown = $travelReferees + $travelJudges + $travelCommissioner + $travelObserver;
        $travelAllowance = (float) ($details['travelAllowance'] ?? 0);

        return [
            'sport' => trim((string) ($details['sport'] ?? '')),
            'category' => trim((string) ($details['category'] ?? '')),
            'teams' => trim((string) ($details['teams'] ?? '')),
            'referees' => (float) ($details['referees'] ?? 0),
            'judges' => (float) ($details['judges'] ?? 0),
            'commissioner' => (float) ($details['commissioner'] ?? 0),
            'observer' => (float) ($details['observer'] ?? 0),
            'doctor' => (float) ($details['doctor'] ?? 0),
            'travelReferees' => $breakdown > 0 ? $travelReferees : $travelAllowance,
            'travelJudges' => $travelJudges,
            'travelCommissioner' => $travelCommissioner,
            'travelObserver' => $travelObserver,
            'travelAllowance' => $breakdown > 0 ? $breakdown : $travelAllowance,
            'transportBus' => (float) ($details['transportBus'] ?? 0),
            'transportPlane' => (float) ($details['transportPlane'] ?? 0),
            'transportShip' => (float) ($details['transportShip'] ?? 0),
            'transportOther' => (float) ($details['transportOther'] ?? 0),
            'accommodation' => (float) ($details['accommodation'] ?? 0),
            'food' => (float) ($details['food'] ?? 0),
            'otherExpenses' => (float) ($details['otherExpenses'] ?? 0),
            'otherExpensesNote' => trim((string) ($details['otherExpensesNote'] ?? '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function matchExpenseTotal(array $details): float
    {
        $travelBreakdown =
            (float) ($details['travelReferees'] ?? 0)
            + (float) ($details['travelJudges'] ?? 0)
            + (float) ($details['travelCommissioner'] ?? 0)
            + (float) ($details['travelObserver'] ?? 0);
        $travel = $travelBreakdown > 0
            ? $travelBreakdown
            : (float) ($details['travelAllowance'] ?? 0);

        return round(
            (float) ($details['referees'] ?? 0)
            + (float) ($details['judges'] ?? 0)
            + (float) ($details['commissioner'] ?? 0)
            + (float) ($details['observer'] ?? 0)
            + (float) ($details['doctor'] ?? 0)
            + $travel
            + (float) ($details['transportBus'] ?? 0)
            + (float) ($details['transportPlane'] ?? 0)
            + (float) ($details['transportShip'] ?? 0)
            + (float) ($details['transportOther'] ?? 0)
            + (float) ($details['accommodation'] ?? 0)
            + (float) ($details['food'] ?? 0)
            + (float) ($details['otherExpenses'] ?? 0),
            2,
        );
    }

    private function assertMonthOpen(Request $request, string $date): void
    {
        try {
            $yearMonth = Carbon::parse($date)->format('Y-m');
        } catch (\Throwable) {
            return;
        }

        $closed = $this->clubQuery(ClosedFinanceMonth::class, $request)
            ->where('year_month', $yearMonth)
            ->exists();

        if ($closed) {
            throw ValidationException::withMessages([
                'occurred_on' => "Ο μήνας {$yearMonth} είναι κλειστός· δεν επιτρέπονται νέες εγγραφές.",
            ]);
        }
    }
}
