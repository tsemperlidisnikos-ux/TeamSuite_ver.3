<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ClubSetting;
use App\Models\PushSubscription;
use App\Services\Push\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PushSubscriptionController extends Controller
{
    public function __construct(
        private readonly WebPushService $push,
    ) {}

    public function vapid(Request $request): JsonResponse
    {
        $club = $request->attributes->get('currentClub') ?? $request->user()?->activeClub;
        $settings = $club
            ? ClubSetting::query()->where('club_id', $club->id)->first()
            : null;

        return response()->json([
            'publicKey' => $this->push->publicKey($settings),
            'enabled' => $this->push->isEnabled($settings),
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $user = $request->user();
        $club = $request->attributes->get('currentClub') ?? $user?->activeClub;
        abort_unless($user && $club, 403);

        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:2048'],
            'keys.p256dh' => ['nullable', 'string', 'max:255'],
            'keys.auth' => ['nullable', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'string', 'max:32'],
        ]);

        $sub = PushSubscription::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('endpoint', $data['endpoint'])
            ->first();

        if (! $sub) {
            $sub = new PushSubscription;
            $sub->id = (string) Str::uuid();
        }

        $sub->fill([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'endpoint' => $data['endpoint'],
            'p256dh' => $data['keys']['p256dh'] ?? null,
            'auth' => $data['keys']['auth'] ?? null,
            'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
            'role' => $user->roleInClub($club),
        ]);
        $sub->save();

        return response()->json(['ok' => true, 'id' => $sub->id]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $data = $request->validate([
            'endpoint' => ['nullable', 'url', 'max:2048'],
        ]);

        $query = PushSubscription::query()->withoutGlobalScopes()->where('user_id', $user->id);
        if (! empty($data['endpoint'])) {
            $query->where('endpoint', $data['endpoint']);
        }
        $query->delete();

        return response()->json(['ok' => true]);
    }
}
