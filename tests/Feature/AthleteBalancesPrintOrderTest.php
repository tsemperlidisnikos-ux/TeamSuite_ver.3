<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AthleteBalancesPrintOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_prints_athletes_are_ordered_by_last_name(): void
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Balances Order Club',
            'slug' => 'balances-order-'.Str::random(4),
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'active_club_id' => $club->id,
        ]);
        $user->clubs()->attach($club->id, [
            'role' => 'admin',
            'permissions' => json_encode(['prints']),
            'finance_own_entries_only' => false,
            'is_active' => true,
        ]);

        Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Νίκος',
            'last_name' => 'Χρήστου',
            'status' => 'active',
            'gdpr_consent' => 'pending',
        ]);
        Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Μαρία',
            'last_name' => 'Αντωνίου',
            'status' => 'active',
            'gdpr_consent' => 'pending',
        ]);
        Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Γιάννης',
            'last_name' => 'Βασιλείου',
            'status' => 'active',
            'gdpr_consent' => 'pending',
        ]);

        $this->actingAs($user)
            ->get(route('admin.prints.index', [
                'type' => 'athlete-balances',
                'view' => 'results',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Prints/Index')
                ->has('athletes', 3)
                ->where('athletes.0.last_name', 'Αντωνίου')
                ->where('athletes.1.last_name', 'Βασιλείου')
                ->where('athletes.2.last_name', 'Χρήστου')
            );
    }
}
