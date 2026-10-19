<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\AthleteTransaction;
use App\Models\BudgetLine;
use App\Models\CashAccount;
use App\Models\ClosedFinanceMonth;
use App\Models\Club;
use App\Models\Expense;
use App\Models\Revenue;
use App\Models\User;
use App\Services\Admin\FinancePageMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinanceStubsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{club: Club, user: User}
     */
    private function seedClub(): array
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Finance Stubs Club',
            'slug' => 'finance-stubs-'.Str::random(5),
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'active_club_id' => $club->id,
        ]);
        $user->clubs()->attach($club->id, ['role' => 'admin', 'is_active' => true]);

        return compact('club', 'user');
    }

    public function test_cash_account_stores_kind_in_meta(): void
    {
        ['user' => $user] = $this->seedClub();

        $this->actingAs($user)
            ->post(route('admin.finance.accounts.store'), [
                'name' => 'Τράπεζα Alpha',
                'opening_balance' => 50,
                'kind' => 'bank',
            ])
            ->assertRedirect(route('admin.finance.index', ['tab' => 'accounts']));

        $account = CashAccount::query()->withoutGlobalScopes()->first();
        $this->assertNotNull($account);
        $this->assertSame('bank', $account->meta['kind'] ?? null);
        $this->assertSame('bank', $account->kind);
    }

    public function test_close_month_persists_and_blocks_revenue(): void
    {
        ['club' => $club, 'user' => $user] = $this->seedClub();
        $yearMonth = now()->format('Y-m');

        $this->actingAs($user)
            ->post(route('admin.finance.close-month'), [
                'year_month' => $yearMonth,
            ])
            ->assertRedirect(route('admin.finance.index', ['tab' => 'accounts']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('closed_finance_months', [
            'club_id' => $club->id,
            'year_month' => $yearMonth,
        ]);

        $this->actingAs($user)
            ->get(route('admin.finance.index', ['tab' => 'accounts']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Finance/Index')
                ->where('closedMonths', fn ($months) => in_array($yearMonth, collect($months)->all(), true))
            );

        $this->actingAs($user)
            ->from(route('admin.finance.index', ['tab' => 'revenues']))
            ->post(route('admin.finance.revenues.store'), [
                'amount' => 10,
                'category' => 'Χορηγία',
                'occurred_on' => now()->toDateString(),
            ])
            ->assertRedirect(route('admin.finance.index', ['tab' => 'revenues']))
            ->assertSessionHasErrors('occurred_on');

        $this->assertSame(0, Revenue::query()->withoutGlobalScopes()->count());
        $this->assertSame(0, Expense::query()->withoutGlobalScopes()->count());
        $this->assertSame(1, ClosedFinanceMonth::query()->withoutGlobalScopes()->count());
    }

    public function test_budget_line_store_persists_for_season(): void
    {
        ['club' => $club, 'user' => $user] = $this->seedClub();
        $seasonStart = 2025;

        $this->actingAs($user)
            ->post(route('admin.finance.budget.store'), [
                'season_start' => $seasonStart,
                'type' => 'income',
                'subcategory' => 'ΣΥΝΔΡΟΜΕΣ ΑΘΛΗΤΩΝ',
                'amount' => 1200,
                'club_name' => '',
                'sport' => 'Ποδόσφαιρο',
            ])
            ->assertRedirect(route('admin.finance.index', [
                'tab' => 'budget',
                'season' => $seasonStart,
            ]));

        $line = BudgetLine::query()->withoutGlobalScopes()
            ->where('club_id', $club->id)
            ->first();

        $this->assertNotNull($line);
        $this->assertSame(1200.0, (float) $line->amount);
        $this->assertSame('income', $line->meta['type'] ?? null);
        $this->assertSame('ΣΥΝΔΡΟΜΕΣ ΑΘΛΗΤΩΝ', $line->meta['subcategory'] ?? null);
        $this->assertSame($seasonStart, (int) ($line->meta['seasonStart'] ?? 0));
        $this->assertSame('Ποδόσφαιρο', $line->meta['sport'] ?? null);

        $this->actingAs($user)
            ->get(route('admin.finance.index', ['tab' => 'budget', 'season' => $seasonStart]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Finance/Index')
                ->has('budget.lines', 1)
                ->where('budget.lines.0.subcategory', 'ΣΥΝΔΡΟΜΕΣ ΑΘΛΗΤΩΝ')
                ->where('budget.lines.0.amount', 1200)
            );
    }

    public function test_match_expense_store_persists_meta(): void
    {
        ['club' => $club, 'user' => $user] = $this->seedClub();

        $this->actingAs($user)
            ->from(route('admin.finance.index', [
                'tab' => 'expenses',
                'expense_subcategory' => 'ΑΓΩΝΕΣ',
            ]))
            ->post(route('admin.finance.expenses.store'), [
                'club_name' => 'ΑΟ Παράδειγμα',
                'occurred_on' => now()->toDateString(),
                'category' => 'ΑΓΩΝΕΣ',
                'subcategory' => 'ΑΓΩΝΕΣ',
                'notes' => 'Φιλικός',
                'match_details' => [
                    'sport' => 'Μπάσκετ',
                    'category' => 'U16',
                    'teams' => 'Α - Β',
                    'referees' => 40,
                    'judges' => 10,
                    'travelReferees' => 15,
                    'food' => 5,
                ],
            ])
            ->assertRedirect(route('admin.finance.index', [
                'tab' => 'expenses',
                'expense_subcategory' => 'ΑΓΩΝΕΣ',
            ]));

        $expense = Expense::query()->withoutGlobalScopes()
            ->where('club_id', $club->id)
            ->first();

        $this->assertNotNull($expense);
        $this->assertSame(70.0, (float) $expense->amount);
        $this->assertSame('Αγώνας: Α - Β', $expense->description);
        $this->assertSame('ΑΓΩΝΕΣ', $expense->meta['subcategory'] ?? null);
        $this->assertSame('ΑΟ Παράδειγμα', $expense->meta['clubName'] ?? null);
        $this->assertSame('Μπάσκετ', $expense->meta['sport'] ?? null);
        $this->assertSame('U16', $expense->meta['matchDetails']['category'] ?? null);
        $this->assertSame(15.0, (float) ($expense->meta['matchDetails']['travelAllowance'] ?? 0));
    }

    public function test_budget_line_update_and_delete(): void
    {
        ['club' => $club, 'user' => $user] = $this->seedClub();
        $seasonStart = 2025;

        $line = BudgetLine::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'label' => 'ΕΓΚΑΤΑΣΤΑΣΗ',
            'year' => $seasonStart,
            'amount' => 500,
            'meta' => [
                'type' => 'expense',
                'subcategory' => 'ΕΓΚΑΤΑΣΤΑΣΗ',
                'seasonStart' => $seasonStart,
                'clubName' => '',
                'sport' => '',
            ],
        ]);

        $this->actingAs($user)
            ->patch(route('admin.finance.budget.update', $line->id), [
                'season_start' => $seasonStart,
                'type' => 'expense',
                'subcategory' => 'ΕΓΚΑΤΑΣΤΑΣΗ',
                'amount' => 750,
                'club_name' => 'Σωματείο Α',
                'sport' => 'Ποδόσφαιρο',
            ])
            ->assertRedirect(route('admin.finance.index', [
                'tab' => 'budget',
                'season' => $seasonStart,
            ]));

        $line->refresh();
        $this->assertSame(750.0, (float) $line->amount);
        $this->assertSame('Σωματείο Α', $line->meta['clubName'] ?? null);
        $this->assertSame('Ποδόσφαιρο', $line->meta['sport'] ?? null);

        $this->actingAs($user)
            ->delete(route('admin.finance.budget.destroy', $line->id))
            ->assertRedirect(route('admin.finance.index', [
                'tab' => 'budget',
                'season' => $seasonStart,
            ]));

        $this->assertSoftDeleted('budget_lines', ['id' => $line->id]);
    }

    public function test_reports_and_balance_excel_export_csv(): void
    {
        ['user' => $user] = $this->seedClub();

        $this->actingAs($user)
            ->get(route('admin.finance.reports.export', [
                'reports_from' => '2025-08-01',
                'reports_to' => '2026-07-31',
            ]))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=finance-reports-2025-08-01-2026-07-31.csv');

        $reportsBody = $this->actingAs($user)
            ->get(route('admin.finance.reports.export', [
                'reports_from' => '2025-08-01',
                'reports_to' => '2026-07-31',
            ]))
            ->streamedContent();

        $this->assertStringContainsString('Σύνολο εσόδων', $reportsBody);
        $this->assertStringContainsString('Μετρική', $reportsBody);

        $balanceResponse = $this->actingAs($user)
            ->get(route('admin.finance.balance.export', [
                'balance_from' => '2025-08-01',
                'balance_to' => '2026-07-31',
            ]))
            ->assertOk();

        $this->assertStringContainsString(
            'attachment; filename=finance-balance-2025-08-01-2026-07-31.csv',
            (string) $balanceResponse->headers->get('content-disposition'),
        );

        $balanceBody = $balanceResponse->streamedContent();
        $this->assertStringContainsString('Σύνοψη', $balanceBody);
        $this->assertStringContainsString('Υπόλοιπο', $balanceBody);
        $this->assertStringContainsString('Ενότητα', $balanceBody);
    }

    public function test_athlete_subscription_revenue_rows_include_athlete_name_in_description(): void
    {
        ['club' => $club, 'user' => $user] = $this->seedClub();

        $athlete = Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Βασίλειος',
            'last_name' => 'Νικολάου',
            'status' => 'active',
            'sport' => 'Μπάσκετ',
            'monthly_fee' => 35,
            'gdpr_consent' => 'pending',
        ]);

        AthleteTransaction::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'athlete_id' => $athlete->id,
            'type' => 'payment',
            'amount' => 35,
            'month' => 10,
            'year' => 2026,
            'comments' => 'ΣΥΝΔΡΟΜΗ 10/26',
            'legacy_id' => 'txn_test_payment',
            'meta' => ['createdAt' => now()->toIso8601String()],
        ]);

        $revenue = Revenue::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'amount' => 35,
            'category' => 'tuition',
            'description' => 'ΜΗΝΙΑΙΑ ΣΥΝΔΡΟΜΗ',
            'occurred_on' => now()->toDateString(),
            'meta' => [
                'subcategory' => FinancePageMetrics::ATHLETE_SUBSCRIPTIONS,
                'linkedTransactionId' => 'txn_test_payment',
                'surname' => 'Νικολάου',
                'firstName' => 'Βασίλειος',
                'subscriptionPeriod' => '2026-10',
                'paymentStatus' => 'paid',
            ],
        ]);

        $this->actingAs($user)
            ->get(route('admin.finance.index', [
                'tab' => 'revenues',
                'revenue_subcategory' => FinancePageMetrics::ATHLETE_SUBSCRIPTIONS,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Finance/Index')
                ->has('revenueRows', 1)
                ->where('revenueRows.0.id', (string) $revenue->id)
                ->where('revenueRows.0.description', 'ΜΗΝΙΑΙΑ ΣΥΝΔΡΟΜΗ – Νικολάου Βασίλειος')
                ->where('revenueRows.0.period', '10-2026')
                ->where('revenueRows.0.amount', 35)
            );
    }

    public function test_balance_builds_income_rows_and_collection_methods_from_revenues(): void
    {
        ['club' => $club, 'user' => $user] = $this->seedClub();

        Revenue::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'amount' => 35,
            'category' => 'tuition',
            'description' => 'ΜΗΝΙΑΙΑ ΣΥΝΔΡΟΜΗ',
            'occurred_on' => '2026-09-10',
            'meta' => [
                'date' => '2026-09-10',
                'subcategory' => FinancePageMetrics::ATHLETE_SUBSCRIPTIONS,
                'sport' => 'Μπάσκετ',
                'paymentMethod' => 'card',
                'paymentStatus' => 'paid',
                'linkedTransactionId' => 'txn_balance_1',
            ],
        ]);
        Revenue::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'amount' => 35,
            'category' => 'tuition',
            'description' => 'ΜΗΝΙΑΙΑ ΣΥΝΔΡΟΜΗ',
            'occurred_on' => '2026-09-10',
            'meta' => [
                'date' => '2026-09-10',
                'subcategory' => FinancePageMetrics::ATHLETE_SUBSCRIPTIONS,
                'sport' => 'Μπάσκετ',
                'paymentMethod' => 'card',
                'paymentStatus' => 'paid',
                'linkedTransactionId' => 'txn_balance_1',
            ],
        ]);
        Revenue::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'amount' => 100,
            'category' => 'Χορηγία',
            'occurred_on' => '2026-09-12',
            'meta' => [
                'date' => '2026-09-12',
                'subcategory' => 'ΧΟΡΗΓΙΕΣ',
                'sport' => 'Μπάσκετ',
                'paymentMethod' => 'eurobank',
                'paymentStatus' => 'paid',
            ],
        ]);

        $this->actingAs($user)
            ->get(route('admin.finance.index', [
                'tab' => 'balance',
                'balance_from' => '2026-08-01',
                'balance_to' => '2026-10-17',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Finance/Index')
                ->where('balance.from', '2026-08-01')
                ->where('balance.to', '2026-10-17')
                ->where('balance.revenue', 170)
                ->where('balance.collectionMethods.0.key', 'card')
                ->where('balance.collectionMethods.0.amount', 35)
                ->where('balance.collectionMethods.1.key', 'eurobank')
                ->where('balance.collectionMethods.1.amount', 100)
                ->has('balance.incomeRows', 2)
            );
    }

    public function test_analysis_totals_do_not_double_count_linked_athlete_payments(): void
    {
        ['club' => $club, 'user' => $user] = $this->seedClub();

        $athlete = Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Άννα',
            'last_name' => 'Παπα',
            'status' => 'active',
            'monthly_fee' => 40,
            'gdpr_consent' => 'pending',
        ]);

        AthleteTransaction::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'athlete_id' => $athlete->id,
            'type' => 'payment',
            'amount' => 40,
            'month' => (int) now()->format('n'),
            'year' => (int) now()->format('Y'),
            'legacy_id' => 'txn_analysis_dedupe',
            'meta' => ['createdAt' => now()->toIso8601String()],
        ]);

        Revenue::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'amount' => 40,
            'category' => 'tuition',
            'occurred_on' => now()->toDateString(),
            'meta' => [
                'subcategory' => FinancePageMetrics::ATHLETE_SUBSCRIPTIONS,
                'linkedTransactionId' => 'txn_analysis_dedupe',
                'paymentStatus' => 'paid',
                'date' => now()->toDateString(),
            ],
        ]);

        Revenue::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'amount' => 100,
            'category' => 'Χορηγία',
            'occurred_on' => now()->toDateString(),
            'meta' => [
                'paymentStatus' => 'paid',
                'date' => now()->toDateString(),
            ],
        ]);

        $this->actingAs($user)
            ->get(route('admin.finance.index', ['tab' => 'analysis']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Finance/Index')
                ->where('analysis.totals.revenue', 140)
            );
    }
}
