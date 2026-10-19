<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\AthleteTransaction;
use App\Models\CashAccount;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\Facility;
use App\Models\RentalSetting;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MediumParityGapsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{club: Club, user: User}
     */
    private function seedClub(string $role = 'admin'): array
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Parity Club',
            'slug' => 'parity-'.Str::random(6),
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'active_club_id' => $club->id,
        ]);
        $user->clubs()->attach($club->id, ['role' => $role, 'is_active' => true]);

        return compact('club', 'user');
    }

    public function test_public_athlete_id_card_page_renders(): void
    {
        $this->get(route('public.athlete-id-card'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/AthleteIdCard')
                ->has('appName')
            );
    }

    public function test_transactions_members_mode_lists_city_column_and_stores_member_tx(): void
    {
        ['club' => $club, 'user' => $user] = $this->seedClub();

        $member = ClubMember::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Μαρία',
            'last_name' => 'Παπαδοπούλου',
            'email' => 'maria@example.com',
            'phone' => '6900000000',
            'status' => 'active',
            'meta' => ['city' => 'Θεσσαλονίκη'],
        ]);

        $this->actingAs($user)
            ->get(route('admin.transactions.index', [
                'sport' => '__members__',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Transactions/Index')
                ->where('showingMembers', true)
                ->has('members', 1)
                ->where('members.0.city', 'Θεσσαλονίκη')
            );

        $month = (int) now()->month;
        $year = (int) now()->year;

        $this->actingAs($user)
            ->post(route('admin.transactions.store'), [
                'club_member_id' => $member->id,
                'type' => 'charge',
                'amount' => 25,
                'month' => $month,
                'year' => $year,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('athlete_transactions', [
            'club_member_id' => $member->id,
            'athlete_id' => null,
            'type' => 'charge',
            'amount' => 25,
        ]);

        $this->assertSame(
            1,
            AthleteTransaction::query()->withoutGlobalScopes()
                ->where('club_member_id', $member->id)
                ->count()
        );
    }

    public function test_finance_cash_account_create(): void
    {
        ['user' => $user] = $this->seedClub();

        $this->actingAs($user)
            ->post(route('admin.finance.accounts.store'), [
                'name' => 'Κύριο ταμείο',
                'opening_balance' => 100,
                'kind' => 'cash',
            ])
            ->assertRedirect(route('admin.finance.index', ['tab' => 'accounts']));

        $this->assertDatabaseHas('cash_accounts', [
            'name' => 'Κύριο ταμείο',
            'opening_balance' => 100,
        ]);

        $account = CashAccount::query()->withoutGlobalScopes()->first();
        $this->assertSame(1, CashAccount::query()->withoutGlobalScopes()->count());
        $this->assertSame('cash', $account?->meta['kind'] ?? null);
    }

    public function test_transactions_athletes_include_registry_columns(): void
    {
        ['club' => $club, 'user' => $user] = $this->seedClub();

        Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Γιάννης',
            'last_name' => 'Παπαδόπουλος',
            'birth_date' => '2012-05-10',
            'status' => 'active',
            'gdpr_consent' => 'pending',
            'amka_encrypted' => 'encrypted-amka',
            'meta' => [
                'registration_number' => 'A-100',
                'father_first_name' => 'Κώστας',
            ],
        ]);

        $this->actingAs($user)
            ->get(route('admin.transactions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Transactions/Index')
                ->has('athletes', 1)
                ->where('athletes.0.amka', '********')
                ->where('athletes.0.registration_number', 'A-100')
                ->where('athletes.0.father_name', 'Κώστας')
                ->where('athletes.0.birth_date', '2012-05-10')
            );
    }

    public function test_rentals_save_availability_persists_rules_json(): void
    {
        ['club' => $club, 'user' => $user] = $this->seedClub();

        $facility = Facility::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'name' => 'Κλειστό Α',
            'is_rentable' => false,
            'meta' => ['sports' => ['Βόλεϊ', 'Μπάσκετ']],
        ]);

        $this->actingAs($user)
            ->post(route('admin.rentals.save-availability'), [
                'public_enabled' => true,
                'notes' => 'Ελάχιστη 1 ώρα',
                'rules' => [
                    [
                        'facilityId' => $facility->id,
                        'enabled' => true,
                        'slotMinutes' => 60,
                        'hourlyRateFull' => 40,
                        'hourlyRateHalf' => 25,
                        'lockerRoomAvailable' => true,
                        'lockerRoomFee' => 5,
                        'windows' => [
                            [
                                'days' => [1, 2, 3, 4, 5],
                                'startTime' => '18:00',
                                'endTime' => '22:00',
                            ],
                        ],
                    ],
                ],
                'facilities' => [
                    [
                        'id' => $facility->id,
                        'photoUrl' => null,
                        'is_rentable' => true,
                        'sports' => ['Βόλεϊ', 'Μπάσκετ'],
                    ],
                ],
            ])
            ->assertRedirect(route('admin.rentals.index'));

        $settings = RentalSetting::query()->withoutGlobalScopes()->where('club_id', $club->id)->first();
        $this->assertNotNull($settings);
        $this->assertTrue((bool) $settings->public_enabled);
        $this->assertSame('Ελάχιστη 1 ώρα', $settings->rules['notes'] ?? null);
        $this->assertSame($facility->id, $settings->rules['rules'][0]['facilityId'] ?? null);
        $this->assertTrue((bool) ($settings->rules['rules'][0]['enabled'] ?? false));
        $this->assertEquals(40.0, (float) ($settings->rules['rules'][0]['hourlyRateFull'] ?? 0));

        $facility->refresh();
        $this->assertTrue((bool) $facility->is_rentable);
    }

    public function test_doctor_dashboard_view(): void
    {
        ['club' => $club, 'user' => $user] = $this->seedClub(Roles::DOCTOR);

        Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Νίκος',
            'last_name' => 'Ιατρός',
            'status' => 'active',
            'health_card' => true,
            'gdpr_consent' => 'pending',
            'amka_encrypted' => '12345678901',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('view', 'doctor')
                ->where('doctor.activeAthletes', 1)
                ->where('doctor.withAmka', 1)
                ->where('doctor.withHealthCard', 1)
                ->has('doctor.athletes', 1)
            );
    }
}
