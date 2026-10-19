<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ReceiptIssue;
use App\Models\ReceiptSeries;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReceiptsRegistryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{club: Club, user: User}
     */
    private function seedAdmin(): array
    {
        $club = Club::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Receipts Club',
            'slug' => 'receipts-'.Str::random(5),
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

    public function test_receipts_index_lists_restored_issues(): void
    {
        ['club' => $club, 'user' => $user] = $this->seedAdmin();

        $series = ReceiptSeries::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'series' => 'Γ',
            'range_from' => 1,
            'range_to' => 50,
            'next_number' => 3,
            'is_active' => true,
        ]);

        ReceiptIssue::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'receipt_series_id' => $series->id,
            'series' => 'Γ',
            'number' => 1,
            'status' => 'issued',
            'issued_at' => '2026-09-14 16:19:07',
            'meta' => [
                'amount' => 40,
                'receivedFrom' => 'ΔΗΜΗΤΡΙΟΥ ΝΙΚΟΣ',
                'reason' => 'ΕΓΓΡΑΦΗ',
                'kind' => 'subscription',
                'emailedAt' => '2026-09-14T16:20:00Z',
            ],
        ]);

        ReceiptIssue::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'receipt_series_id' => $series->id,
            'series' => 'Γ',
            'number' => 2,
            'status' => 'cancelled',
            'issued_at' => '2026-09-15 10:00:00',
            'cancelled_at' => '2026-09-16 12:00:00',
            'meta' => [
                'amount' => 105,
                'receivedFrom' => 'ΠΑΠΑΔΟΠΟΥΛΟΥ ΜΑΡΙΑ',
                'kind' => 'subscription',
                'voidReason' => 'Διαγραφή',
            ],
        ]);

        $this->actingAs($user)
            ->get(route('admin.receipts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Receipts/Index')
                ->where('tab', 'registry')
                ->has('issues', 2)
                ->where('summary.issued_count', 1)
                ->where('summary.cancelled_count', 1)
                ->where('summary.total_amount', 40)
                ->where('summary.emailed_count', 1)
                ->where('issues.0.label', 'Γ-2')
                ->where('issues.0.is_cancelled', true)
                ->where('issues.1.label', 'Γ-1')
                ->where('issues.1.received_from', 'ΔΗΜΗΤΡΙΟΥ ΝΙΚΟΣ')
                ->has('receiptSeries', 1)
            );
    }

    public function test_cancel_marks_issue_cancelled(): void
    {
        ['club' => $club, 'user' => $user] = $this->seedAdmin();

        $issue = ReceiptIssue::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $club->id,
            'series' => 'Α',
            'number' => 10,
            'status' => 'issued',
            'issued_at' => now(),
            'meta' => [
                'amount' => 20,
                'receivedFrom' => 'TEST User',
                'kind' => 'other',
            ],
        ]);

        $this->actingAs($user)
            ->post(route('admin.receipts.cancel', $issue->id))
            ->assertRedirect();

        $issue->refresh();
        $this->assertSame('cancelled', $issue->status);
        $this->assertNotNull($issue->cancelled_at);
    }
}
