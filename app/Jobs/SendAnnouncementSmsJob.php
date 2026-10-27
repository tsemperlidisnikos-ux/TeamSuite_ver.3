<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Models\ClubSetting;
use App\Services\Sms\ClubSmsSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendAnnouncementSmsJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $phones
     */
    public function __construct(
        public string $clubId,
        public string $announcementId,
        public string $title,
        public string $body,
        public array $phones,
    ) {}

    public function handle(ClubSmsSender $sms): void
    {
        $settings = ClubSetting::query()->where('club_id', $this->clubId)->first();
        if (! $settings || ! $sms->isConfigured($settings->sms)) {
            Log::warning('Announcement SMS skipped — SMS not configured', [
                'club_id' => $this->clubId,
                'announcement_id' => $this->announcementId,
            ]);

            return;
        }

        $text = mb_substr(trim($this->title."\n".$this->body), 0, 480);
        $sent = [];
        $failed = [];

        foreach ($this->phones as $phone) {
            if (! is_string($phone) || strlen(preg_replace('/\s+/', '', $phone) ?? '') < 8) {
                continue;
            }

            try {
                $sms->send($settings, $phone, $text);
                $sent[] = ['phone' => $phone, 'status' => 'sent'];
            } catch (\Throwable $e) {
                $failed[] = ['phone' => $phone, 'status' => 'failed', 'error' => $e->getMessage()];
                Log::warning('Announcement SMS failed', [
                    'club_id' => $this->clubId,
                    'announcement_id' => $this->announcementId,
                    'phone' => $phone,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $row = Announcement::query()->withoutGlobalScopes()->find($this->announcementId);
        if (! $row) {
            return;
        }

        $meta = is_array($row->meta) ? $row->meta : [];
        $meta['sms_stats'] = [
            'sentCount' => count($sent),
            'failedCount' => count($failed),
            'recipients' => array_merge($sent, $failed),
            'queued_at' => now()->toIso8601String(),
        ];
        $row->meta = $meta;
        $row->save();
    }
}
