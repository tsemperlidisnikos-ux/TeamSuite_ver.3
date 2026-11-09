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

class MonthSubscriptionPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_prints_include_parent_emails_for_month_subscription(): void
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Prints Club',
            'slug' => 'prints-'.Str::random(4),
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
            'first_name' => 'Γεώργιος',
            'last_name' => 'Παπαδόπουλος',
            'status' => 'active',
            'guardian_phone' => '6900000000',
            'meta' => [
                'father_email' => 'father@example.com',
                'mother_email' => 'mother@example.com',
            ],
        ]);

        $this->actingAs($user)
            ->get(route('admin.prints.index', [
                'type' => 'month-subscription',
                'month' => now()->month,
                'year' => now()->year,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Prints/Index')
                ->has('athletes', 1)
                ->where('athletes.0.father_email', 'father@example.com')
                ->where('athletes.0.mother_email', 'mother@example.com')
            );
    }
}
