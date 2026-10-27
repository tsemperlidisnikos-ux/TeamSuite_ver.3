<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\AthleteTransaction;
use App\Models\ClubSetting;
use App\Models\ParentAthleteLink;
use App\Services\Payments\OnlineCheckoutService;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class OnlineCheckoutController extends Controller
{
    public function __construct(
        private readonly OnlineCheckoutService $checkouts,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $club = $request->attributes->get('currentClub') ?? $user?->activeClub;
        abort_unless($user && $club, 403);

        $role = $user->is_platform_admin
            ? Roles::PLATFORM_ADMIN
            : $user->roleInClub($club);
        abort_unless($role === Roles::PARENT || $user->is_platform_admin, 403);

        $data = $request->validate([
            'athlete_id' => ['required', 'uuid'],
            'provider' => ['required', 'in:viva,stripe,eurobank'],
            'amount' => ['nullable', 'numeric', 'min:0.3'],
        ]);

        $linked = ParentAthleteLink::query()
            ->withoutGlobalScopes()
            ->where('club_id', $club->id)
            ->where('parent_user_id', $user->id)
            ->where('athlete_id', $data['athlete_id'])
            ->exists();

        abort_unless($linked || $user->is_platform_admin, 403, 'Ο αθλητής δεν συνδέεται με τον λογαριασμό σας.');

        $athlete = Athlete::query()->withoutGlobalScopes()
            ->where('club_id', $club->id)
            ->whereKey($data['athlete_id'])
            ->firstOrFail();

        $amountEuro = array_key_exists('amount', $data) && $data['amount'] !== null
            ? round((float) $data['amount'], 2)
            : $this->balanceFor($athlete->id);

        if ($amountEuro < 0.3) {
            return response()->json(['ok' => false, 'error' => 'Δεν υπάρχει ανοιχτό υπόλοιπο προς πληρωμή.'], 422);
        }

        $settings = ClubSetting::query()->firstOrCreate(['club_id' => $club->id]);

        try {
            $result = $this->checkouts->start($club, $settings, [
                'athlete' => $athlete,
                'provider' => $data['provider'],
                'amountEuro' => $amountEuro,
                'customerEmail' => (string) $user->email,
                'customerName' => (string) $user->name,
                'successUrl' => route('portal.parent', ['tab' => 'payments', 'paid' => 1]),
                'cancelUrl' => route('portal.parent', ['tab' => 'payments', 'cancelled' => 1]),
                'description' => 'Οφειλή — '.$athlete->last_name.' '.$athlete->first_name,
            ]);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'error' => 'Αποτυχία έναρξης πληρωμής.'], 502);
        }

        return response()->json($result);
    }

    private function balanceFor(string $athleteId): float
    {
        $balance = AthleteTransaction::query()->withoutGlobalScopes()
            ->where('athlete_id', $athleteId)
            ->get(['type', 'amount'])
            ->reduce(
                fn (float $sum, AthleteTransaction $row) => $sum + ($row->type === 'charge'
                    ? (float) $row->amount
                    : -(float) $row->amount),
                0.0,
            );

        return round(max(0, $balance), 2);
    }
}
