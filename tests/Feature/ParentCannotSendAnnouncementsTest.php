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
     * @return array{club: Club, parentUser: User, athlete: Athlete}
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
            'permissions' => json_encode(['dashboard', 'fees', 'announcements', 'settings']),
        ]);

        $athlete = Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Μαρία',
            'last_name' => 'Παπα',
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

        return compact('club', 'parentUser', 'athlete');
    }

    public function test_parent_permissions_never_include_announcements_module(): void
    {
        ['parentUser' => $user, 'club' => $club] = $this->seedParent();

        $this->assertNotContains('announcements', $user->permissionsInClub($club));
    }

    public function test_parent_is_forbidden_from_admin_announcement_routes(): void
    {
        ['parentUser' => $user, 'club' => $club] = $this->seedParent();

        $row = Announcement::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'created_by' => $user->id,
            'title' => 'Υπάρχουσα',
            'body' => 'Σώμα',
            'audience' => ['roles' => ['parent']],
            'published_at' => now(),
            'meta' => [],
        ]);

        $this->actingAs($user)
            ->get(route('admin.announcements.index'))
            ->assertForbidden();

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
            'title' => 'Υπάρχουσα',
        ]);
        $this->assertDatabaseMissing('announcements', [
            'title' => 'Νέα από γονέα',
        ]);
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
