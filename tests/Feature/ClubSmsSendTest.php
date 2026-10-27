<?php

namespace Tests\Feature;

use App\Jobs\SendAnnouncementSmsJob;
use App\Models\Announcement;
use App\Models\Club;
use App\Models\ClubSetting;
use App\Services\Sms\ClubSmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClubSmsSendTest extends TestCase
{
    use RefreshDatabase;

    public function test_club_sms_sender_posts_to_sms_to(): void
    {
        Http::fake([
            'https://api.sms.to/sms/send' => Http::response(['success' => true], 200),
        ]);

        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'SMS Club',
            'slug' => 'sms-club',
            'is_active' => true,
        ]);
        $settings = ClubSetting::query()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'sms' => [
                'enabled' => true,
                'provider' => 'sms_to',
                'apiKey' => 'test-key',
                'sender' => 'TeamSuite',
            ],
        ]);

        app(ClubSmsSender::class)->send($settings, '+306912345678', 'Γεια σας');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.sms.to/sms/send'
            && $request['to'] === '+306912345678');
    }

    public function test_announcement_sms_job_stores_stats(): void
    {
        Http::fake([
            'https://api.sms.to/sms/send' => Http::response(['success' => true], 200),
        ]);

        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'SMS Club 2',
            'slug' => 'sms-club-2',
            'is_active' => true,
        ]);
        ClubSetting::query()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'sms' => [
                'enabled' => true,
                'provider' => 'sms.to',
                'apiKey' => 'test-key',
                'sender' => 'Club',
            ],
        ]);
        $announcement = Announcement::query()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'title' => 'Τεστ',
            'body' => 'Μήνυμα',
            'audience' => ['roles' => ['parent']],
            'published_at' => now(),
            'meta' => [],
        ]);

        (new SendAnnouncementSmsJob(
            $club->id,
            $announcement->id,
            'Τεστ',
            'Μήνυμα',
            ['+306911111111'],
        ))->handle(app(ClubSmsSender::class));

        $meta = $announcement->fresh()->meta;
        $this->assertSame(1, $meta['sms_stats']['sentCount'] ?? null);
    }
}
