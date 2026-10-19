<?php

namespace Tests\Feature;

use App\Models\AcademyClass;
use App\Models\Athlete;
use App\Models\AthleteTransaction;
use App\Models\CashAccount;
use App\Models\Club;
use App\Models\Coach;
use App\Models\Expense;
use App\Models\RentalBooking;
use App\Models\Revenue;
use App\Models\User;
use App\Services\Admin\ClubDashboardMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminDashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{club: Club, user: User}
     */
    private function seedClub(): array
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Dash Club',
            'slug' => 'dash-'.Str::random(6),
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

    public function test_dashboard_numbers_agree_across_cards_and_tables(): void
    {
        ['club' => $club, 'user' => $user] = $this->seedClub();

        $basketClass = AcademyClass::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'name' => 'U12 Μπάσκετ',
            'sport' => 'Μπάσκετ',
            'monthly_fee' => 40,
        ]);
        $volleyClass = AcademyClass::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'name' => 'U14 Βόλεϊ',
            'sport' => 'Βόλεϊ',
            'monthly_fee' => 35,
        ]);

        Coach::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Γιάννης',
            'last_name' => 'Προπονητής',
            'sport' => 'Μπάσκετ',
            'is_active' => true,
        ]);
        Coach::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Μαρία',
            'last_name' => 'Βόλεϊ',
            'sport' => 'Βόλεϊ',
            'is_active' => true,
        ]);

        $basketAthlete = Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Νίκος',
            'last_name' => 'Αθλητής',
            'status' => 'active',
            'sport' => '',
            'monthly_fee' => 40,
            'gdpr_consent' => 'pending',
            'meta' => ['classIds' => [$basketClass->id]],
        ]);
        $volleyAthlete = Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Έλενα',
            'last_name' => 'Αθλήτρια',
            'status' => 'active',
            'sport' => 'Βόλεϊ',
            'monthly_fee' => 35,
            'gdpr_consent' => 'pending',
        ]);
        $basketClass->athletes()->attach($basketAthlete->id, ['is_primary' => true]);

        CashAccount::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'name' => 'Ταμείο',
            'opening_balance' => 100,
            'legacy_id' => 'cash_main',
        ]);

        AthleteTransaction::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'athlete_id' => $basketAthlete->id,
            'type' => 'charge',
            'amount' => 100,
            'month' => now()->month,
            'year' => now()->year,
        ]);
        AthleteTransaction::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'athlete_id' => $basketAthlete->id,
            'type' => 'payment',
            'amount' => 40,
            'month' => now()->month,
            'year' => now()->year,
            'meta' => ['createdAt' => now()->toIso8601String()],
        ]);
        AthleteTransaction::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'athlete_id' => $volleyAthlete->id,
            'type' => 'charge',
            'amount' => 80,
            'month' => now()->month,
            'year' => now()->year,
        ]);

        Revenue::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'amount' => 25,
            'category' => 'Άλλο',
            'occurred_on' => now()->toDateString(),
            'meta' => [
                'date' => now()->toDateString(),
                'paymentStatus' => 'paid',
                'accountId' => 'cash_main',
                'sport' => 'Μπάσκετ',
            ],
        ]);
        Expense::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'amount' => 10,
            'category' => 'Λειτουργικά',
            'occurred_on' => now()->toDateString(),
            'meta' => ['accountId' => 'cash_main'],
        ]);

        RentalBooking::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'customer_name' => 'Ενοικιαστής',
            'starts_at' => now()->setTime(18, 0),
            'ends_at' => now()->setTime(19, 0),
            'amount' => 50,
            'status' => 'confirmed',
            'meta' => ['paymentCollected' => false, 'date' => now()->toDateString()],
        ]);

        $metrics = app(ClubDashboardMetrics::class)->forClub($club->id);

        $this->assertSame(2, (int) collect($metrics['bySport'])->sum('athletes'));
        $this->assertSame(1, collect($metrics['bySport'])->firstWhere('sport', 'Μπάσκετ')['athletes']);
        $this->assertSame(1, collect($metrics['bySport'])->firstWhere('sport', 'Βόλεϊ')['athletes']);
        $this->assertSame(1, collect($metrics['bySport'])->firstWhere('sport', 'Μπάσκετ')['classes']);
        $this->assertSame(1, collect($metrics['bySport'])->firstWhere('sport', 'Βόλεϊ')['classes']);
        $this->assertNull(collect($metrics['bySport'])->firstWhere('sport', 'Χωρίς άθλημα'));

        $this->assertEquals(115.0, $metrics['money']['cash']);
        $this->assertEquals(140.0, $metrics['money']['debts']);
        $this->assertEqualsWithDelta(
            collect($metrics['financeBySport'])->sum('collected'),
            $metrics['money']['collectionsMonth'],
            0.01,
        );
        $this->assertEqualsWithDelta(
            collect($metrics['financeBySport'])->sum('owed'),
            $metrics['money']['debts'],
            0.01,
        );
        $this->assertSame(1, $metrics['uncollectedRentals']['count']);
        $this->assertEquals(50.0, $metrics['uncollectedRentals']['total']);
        $this->assertEquals(65.0, $metrics['money']['collectionsMonth']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('money.cash', 115)
                ->where('money.debts', 140)
                ->where('uncollectedRentals.count', 1)
            );
    }

    public function test_without_sport_row_does_not_count_all_classes(): void
    {
        ['club' => $club] = $this->seedClub();

        AcademyClass::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'name' => 'Μπάσκετ A',
            'sport' => 'Μπάσκετ',
            'monthly_fee' => 40,
        ]);

        Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Χωρίς',
            'last_name' => 'Άθλημα',
            'status' => 'active',
            'sport' => '',
            'monthly_fee' => 0,
            'gdpr_consent' => 'pending',
        ]);

        $metrics = app(ClubDashboardMetrics::class)->forClub($club->id);
        $without = collect($metrics['bySport'])->firstWhere('sport', 'Χωρίς άθλημα');

        $this->assertNotNull($without);
        $this->assertSame(1, $without['athletes']);
        $this->assertSame(0, $without['classes']);
        $this->assertSame(0, $without['coaches']);
    }
}
