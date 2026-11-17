<?php

namespace Tests\Feature;

use App\Jobs\SendAnnouncementEmailsJob;
use App\Models\Announcement;
use App\Models\Association;
use App\Models\Athlete;
use App\Models\ClothingPackage;
use App\Models\Club;
use App\Models\ClubSetting;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AnnouncementAudienceScopeFiltersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{club: Club, user: User, athleteMatch: Athlete, athleteOther: Athlete, package: ClothingPackage}
     */
    private function seedClub(): array
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Announcement Scope Club',
            'slug' => 'ann-scope-'.Str::random(5),
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
            'permissions' => json_encode(['announcements', 'dashboard']),
        ]);

        ClubSetting::query()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'profile' => ['custom_charge_label' => 'Έκτακτη χρέωση'],
        ]);

        Sport::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'name' => 'Μπάσκετ',
        ]);

        Association::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'name' => 'Ε.Ο.Κ.',
            'meta' => ['active' => true],
        ]);

        $package = ClothingPackage::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'name' => 'Basic',
            'price' => 40,
        ]);

        $athleteMatch = Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Μαρία',
            'last_name' => 'Παπα',
            'email' => 'match-parent@example.com',
            'status' => 'active',
            'sport' => 'Μπάσκετ',
            'gdpr_consent' => 'pending',
            'meta' => [
                'club_name' => 'Ε.Ο.Κ.',
                'custom_charge' => true,
                'clothing_package_ids' => [$package->id],
            ],
        ]);

        $athleteOther = Athlete::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'first_name' => 'Γιάννης',
            'last_name' => 'Άλλος',
            'email' => 'other-parent@example.com',
            'status' => 'active',
            'sport' => 'Βόλεϊ',
            'gdpr_consent' => 'pending',
            'meta' => [
                'club_name' => 'Άλλο Σωματείο',
                'custom_charge' => false,
                'clothing_package_ids' => [],
            ],
        ]);

        return compact('club', 'user', 'athleteMatch', 'athleteOther', 'package');
    }

    public function test_announcements_index_exposes_vercel_style_scope_filters(): void
    {
        ['user' => $user, 'package' => $package] = $this->seedClub();

        $this->actingAs($user)
            ->get(route('admin.announcements.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Announcements/Index')
                ->where('customChargeLabel', 'Έκτακτη χρέωση')
                ->where('sports', fn ($sports) => collect($sports)->contains('Μπάσκετ'))
                ->where('associations', fn ($items) => collect($items)->contains('Ε.Ο.Κ.'))
                ->has('clothingPackages', 1)
                ->where('clothingPackages.0.id', $package->id)
            );
    }

    public function test_announcement_send_respects_association_and_clothing_scope(): void
    {
        Queue::fake();
        ['user' => $user, 'athleteMatch' => $athleteMatch, 'athleteOther' => $athleteOther, 'package' => $package] = $this->seedClub();

        $this->actingAs($user)
            ->post(route('admin.announcements.store'), [
                'title' => 'Scoped send',
                'body' => 'Body',
                'roles' => ['parent'],
                'association' => 'Ε.Ο.Κ.',
                'custom_charge_filter' => 'yes',
                'clothing_package_id' => $package->id,
                'publish' => true,
                'send_email' => true,
                'send_sms' => false,
                'send_push' => false,
            ])
            ->assertRedirect();

        $row = Announcement::query()->withoutGlobalScopes()
            ->where('title', 'Scoped send')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('Ε.Ο.Κ.', $row->audience['association'] ?? null);
        $this->assertSame('yes', $row->audience['custom_charge_filter'] ?? null);
        $this->assertSame($package->id, $row->audience['clothing_package_id'] ?? null);

        Queue::assertPushed(SendAnnouncementEmailsJob::class, function (SendAnnouncementEmailsJob $job) use ($athleteMatch, $athleteOther) {
            return in_array($athleteMatch->email, $job->emails, true)
                && ! in_array($athleteOther->email, $job->emails, true);
        });
    }
}
