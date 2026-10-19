<?php

namespace Tests\Feature;

use App\Models\Association;
use App\Models\Club;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssociationsSportsPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{club: Club, user: User}
     */
    private function seedAdmin(): array
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Assoc Sports Club',
            'slug' => 'assoc-'.Str::random(5),
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

    public function test_associations_index_and_store(): void
    {
        ['user' => $user, 'club' => $club] = $this->seedAdmin();

        $this->actingAs($user)
            ->get(route('admin.associations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Associations/Index')
                ->where('title', 'Σωματείο')
            );

        $this->actingAs($user)
            ->post(route('admin.associations.store'), [
                'name' => 'Α.Ο. Παράδειγμα',
                'city' => 'Αθήνα',
                'phone' => '2100000000',
                'email' => 'info@example.com',
                'address' => 'Οδός 1',
                'active' => '1',
            ])
            ->assertRedirect(route('admin.associations.index'));

        $association = Association::query()->withoutGlobalScopes()
            ->where('club_id', $club->id)
            ->first();

        $this->assertNotNull($association);
        $this->assertSame('Α.Ο. Παράδειγμα', $association->name);
        $this->assertSame('Αθήνα', $association->meta['city'] ?? null);
        $this->assertTrue((bool) ($association->meta['active'] ?? false));
    }

    public function test_sports_lives_under_settings_and_store_works(): void
    {
        ['user' => $user, 'club' => $club] = $this->seedAdmin();

        $this->actingAs($user)
            ->get(route('admin.sports.index'))
            ->assertRedirect('/admin/settings?tab=sports');

        $this->actingAs($user)
            ->get('/admin/settings?tab=sports')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Settings/Index')
            );

        $this->actingAs($user)
            ->post(route('admin.sports.store'), ['name' => 'Μπάσκετ'])
            ->assertRedirect('/admin/settings?tab=sports');

        $this->assertSame(
            1,
            Sport::query()->withoutGlobalScopes()->where('club_id', $club->id)->where('name', 'Μπάσκετ')->count()
        );
    }

    public function test_legacy_paths_redirect_to_admin(): void
    {
        ['user' => $user] = $this->seedAdmin();

        $this->actingAs($user)
            ->get('/associations')
            ->assertRedirect('/admin/associations');

        $this->actingAs($user)
            ->get('/sports')
            ->assertRedirect('/admin/settings?tab=sports');
    }
}
