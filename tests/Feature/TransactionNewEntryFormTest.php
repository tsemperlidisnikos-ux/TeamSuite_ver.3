<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\AthleteTransaction;
use App\Models\Club;
use App\Models\ReceiptSeries;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TransactionNewEntryFormTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{club: Club, user: User, athlete: Athlete, series: ReceiptSeries}
     */
    private function seedClub(): array
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Tx Form Club',
            'slug' => 'tx-form-'.Str::random(5),
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'active_club_id' => $club->id,
        ]);
        $user->clubs()->attach($club->id, [
            'role' => 'admin',
            'is_active' => true,
            'permissions' => json_encode(['transactions', 'dashboard']),
        ]);

        $athlete = Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Ανδρέας',
            'last_name' => 'Σγούρας',
            'status' => 'active',
            'gdpr_consent' => 'pending',
        ]);

        $series = ReceiptSeries::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'series' => 'Γ',
            'range_from' => 1000,
            'range_to' => 2000,
            'next_number' => 1096,
            'is_active' => true,
        ]);

        return compact('club', 'user', 'athlete', 'series');
    }

    public function test_transactions_index_exposes_receipt_series_options(): void
    {
        ['user' => $user, 'series' => $series] = $this->seedClub();

        $this->actingAs($user)
            ->get(route('admin.transactions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Transactions/Index')
                ->has('receiptSeries', 1)
                ->where('receiptSeries.0.series', $series->series)
                ->where('receiptSeries.0.next', 1096)
            );
    }

    public function test_payment_store_saves_series_and_advances_next_number(): void
    {
        ['user' => $user, 'athlete' => $athlete, 'series' => $series] = $this->seedClub();

        $this->actingAs($user)
            ->post(route('admin.transactions.store'), [
                'athlete_id' => $athlete->id,
                'type' => 'payment',
                'amount' => 25,
                'day' => 17,
                'month' => 11,
                'year' => 2026,
                'payment_method' => 'cash',
                'receipt_series' => 'Γ',
                'receipt_number' => '1096',
                'comments' => 'Δοκιμή',
            ])
            ->assertRedirect();

        $row = AthleteTransaction::query()->withoutGlobalScopes()
            ->where('athlete_id', $athlete->id)
            ->where('type', 'payment')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('Γ', $row->receipt_series);
        $this->assertSame('1096', $row->receipt_number);
        $this->assertSame(1096, (int) $row->receipt_seq);
        $this->assertSame(1097, (int) $series->fresh()->next_number);
    }

    public function test_issuing_receipt_gamma_100_auto_suggests_101_next(): void
    {
        ['user' => $user, 'athlete' => $athlete, 'series' => $series] = $this->seedClub();

        $series->update([
            'range_from' => 100,
            'range_to' => 999,
            'next_number' => 100,
        ]);

        $this->actingAs($user)
            ->post(route('admin.transactions.store'), [
                'athlete_id' => $athlete->id,
                'type' => 'payment',
                'amount' => 30,
                'day' => 17,
                'month' => 11,
                'year' => 2026,
                'payment_method' => 'cash',
                'receipt_series' => 'Γ',
                'receipt_number' => '100',
            ])
            ->assertRedirect();

        $this->assertSame(101, (int) $series->fresh()->next_number);

        $this->actingAs($user)
            ->get(route('admin.transactions.index', ['athlete_id' => $athlete->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Transactions/Index')
                ->where('receiptSeries.0.series', 'Γ')
                ->where('receiptSeries.0.next', 101)
            );
    }
}
