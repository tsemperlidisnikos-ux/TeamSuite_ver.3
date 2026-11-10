<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Athlete;
use App\Models\Club;
use App\Models\ParentAthleteLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ParentCannotSendAnnouncementsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{club: Club, parentUser: User, athlete: Athlete, otherAthlete: Athlete}
     */
    private function seedParent(): array
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Parent Announcements Club',
            'slug' => 'parent-ann-'.Str::random(6),
            'is_active' => true,
        ]);

        $parentUser = User::factory()->create([
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'active_club_id' => $club->id,
        ]);
        $parentUser->clubs()->attach($club->id, [
            'role' => 'parent',
            'is_active' => true,
            'permissions' => json_encode(['dashboard', 'fees', 'settings']),
        ]);

        $athlete = Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Μαρία',
            'last_name' => 'Παπα',
            'status' => 'active',
            'gdpr_consent' => 'pending',
        ]);

        $otherAthlete = Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Γιάννης',
            'last_name' => 'Άλλος',
            'status' => 'active',
            'gdpr_consent' => 'pending',
        ]);

        ParentAthleteLink::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'parent_user_id' => $parentUser->id,
            'athlete_id' => $athlete->id,
            'relation' => 'parent',
        ]);

        return compact('club', 'parentUser', 'athlete', 'otherAthlete');
    }

    public function test_parent_permissions_include_announcements_for_viewing(): void
    {
        ['parentUser' => $user, 'club' => $club] = $this->seedParent();

        $this->assertContains('announcements', $user->permissionsInClub($club));
    }

    public function test_parent_can_view_but_not_send_announcements(): void
    {
        ['parentUser' => $user, 'club' => $club] = $this->seedParent();

        $row = Announcement::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'created_by' => $user->id,
            'title' => 'Για γονείς',
            'body' => 'Σώμα',
            'audience' => ['roles' => ['parent']],
            'published_at' => now(),
            'meta' => [],
        ]);

        $this->actingAs($user)
            ->get(route('admin.announcements.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Announcements/Index')
                ->where('parentReadOnly', true)
                ->has('items.data', 1)
                ->where('items.data.0.title', 'Για γονείς')
            );

        $this->actingAs($user)
            ->post(route('admin.announcements.store'), [
                'title' => 'Νέα από γονέα',
                'body' => 'Δεν πρέπει να αποθηκευτεί.',
                'roles' => ['parent'],
                'class_ids' => [],
                'publish' => true,
                'send_email' => false,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->patch(route('admin.announcements.update', $row->id), [
                'title' => 'Αλλαγή',
                'body' => 'Απαγορευμένη',
                'roles' => ['parent'],
                'class_ids' => [],
                'publish' => true,
                'send_email' => false,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('admin.announcements.destroy', $row->id))
            ->assertForbidden();

        $this->assertDatabaseHas('announcements', [
            'id' => $row->id,
            'title' => 'Για γονείς',
        ]);
        $this->assertDatabaseMissing('announcements', [
            'title' => 'Νέα από γονέα',
        ]);
    }

    public function test_parent_fees_show_only_linked_children(): void
    {
        ['parentUser' => $user, 'athlete' => $athlete, 'otherAthlete' => $other] = $this->seedParent();

        $this->actingAs($user)
            ->get(route('admin.fees.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Fees/Index')
                ->where('parentReadOnly', true)
                ->has('rows', 1)
                ->where('rows.0.id', $athlete->id)
                ->where('rows.0.first_name', 'Μαρία')
            );

        $this->assertNotSame($other->id, $athlete->id);
    }

    public function test_parent_portal_still_shows_announcements_read_only(): void
    {
        ['parentUser' => $user, 'club' => $club] = $this->seedParent();

        Announcement::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'created_by' => $user->id,
            'title' => 'Προβολή γονέα',
            'body' => 'Μόνο ανάγνωση',
            'audience' => ['roles' => ['parent']],
            'published_at' => now(),
            'meta' => [],
        ]);

        $this->actingAs($user)
            ->get(route('portal.parent'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portals/ParentHome')
                ->has('announcements', 1)
                ->where('announcements.0.title', 'Προβολή γονέα')
            );
    }
}
