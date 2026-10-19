<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Expense;
use App\Models\Revenue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinanceAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_index_renders_teamsuite_analysis_shell(): void
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Finance Club',
            'slug' => 'finance-'.Str::random(4),
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'active_club_id' => $club->id,
        ]);
        $user->clubs()->attach($club->id, ['role' => 'admin', 'is_active' => true]);

        Revenue::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'amount' => 120.5,
            'category' => 'Χορηγία',
            'occurred_on' => now()->toDateString(),
            'created_by' => $user->id,
        ]);

        Expense::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'amount' => 40,
            'category' => 'Υλικά',
            'occurred_on' => now()->toDateString(),
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('admin.finance.index', ['tab' => 'analysis']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Finance/Index')
                ->where('tab', 'analysis')
                ->has('tabs')
                ->has('analysis.day')
                ->has('analysis.totals')
                ->has('analysis.monthlySeries')
                ->has('analysis.revenueByCategory')
                ->has('analysis.expenseByCategory')
                ->where('analysis.totals.expense', 40)
            );
    }
}
