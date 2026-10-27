<?php

namespace Tests\Feature;

use App\Jobs\CheckHostingBalanceJob;
use App\Mail\HostingBalanceAlertMail;
use App\Models\User;
use App\Services\Hosting\HostingCostService;
use App\Services\Platform\PlatformConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class HostingCostTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_sees_hosting_cost_on_dashboard(): void
    {
        $admin = User::factory()->create([
            'is_platform_admin' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('platform.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Platform/Dashboard')
                ->has('hostingCost')
                ->where('hostingCost.provider', 'hetzner'));
    }

    public function test_platform_admin_can_update_hosting_balance(): void
    {
        $admin = User::factory()->create([
            'is_platform_admin' => true,
            'email_verified_at' => now(),
            'email' => 'hosting-admin@example.com',
        ]);

        $this->actingAs($admin)
            ->post(route('platform.hosting.update'), [
                'monthly_estimate_eur' => 8,
                'prepaid_balance_eur' => 40,
                'low_balance_threshold_eur' => 15,
                'notify_email' => 'hosting-admin@example.com',
            ])
            ->assertRedirect();

        $snap = app(HostingCostService::class)->snapshot();
        $this->assertSame(8.0, $snap['monthly_estimate_eur']);
        $this->assertSame(40.0, $snap['prepaid_balance_eur']);
        $this->assertFalse($snap['needs_topup']);
    }

    public function test_low_balance_job_sends_mail_once_per_window(): void
    {
        Mail::fake();

        User::factory()->create([
            'is_platform_admin' => true,
            'email' => 'alert@example.com',
            'email_verified_at' => now(),
        ]);

        app(PlatformConfigService::class)->update([
            'hostingBilling' => [
                'monthly_estimate_eur' => 10,
                'prepaid_balance_eur' => 5,
                'low_balance_threshold_eur' => 15,
                'notify_email' => 'alert@example.com',
            ],
        ]);

        (new CheckHostingBalanceJob)->handle(app(HostingCostService::class));
        (new CheckHostingBalanceJob)->handle(app(HostingCostService::class));

        Mail::assertSent(HostingBalanceAlertMail::class, 1);
    }
}
