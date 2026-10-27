<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Models\ClubSetting;
use App\Models\PushSubscription;
use App\Services\Push\WebPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendAnnouncementPushJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<int>  $userIds
     */
    public function __construct(
        public string $clubId,
        public string $announcementId,
        public string $title,
        public string $body,
        public array $userIds,
        public ?string $url = null,
    ) {}

    public function handle(WebPushService $push): void
    {
        $settings = ClubSetting::query()->where('club_id', $this->clubId)->first();
        if (! $push->isEnabled($settings) || $this->userIds === []) {
            return;
        }

        $subs = PushSubscription::query()
            ->withoutGlobalScopes()
            ->where('club_id', $this->clubId)
            ->whereIn('user_id', $this->userIds)
            ->get();

        $stats = $push->sendToSubscriptions($subs, $this->title, $this->body, $this->url);

        $row = Announcement::query()->withoutGlobalScopes()->find($this->announcementId);
        if (! $row) {
            return;
        }

        $meta = is_array($row->meta) ? $row->meta : [];
        $meta['push_stats'] = array_merge($stats, ['queued_at' => now()->toIso8601String()]);
        $row->meta = $meta;
        $row->save();
    }
}
