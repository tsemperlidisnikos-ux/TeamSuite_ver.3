<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Services\Mail\ClubSmtpMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SendAnnouncementEmailsJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $emails
     */
    public function __construct(
        public string $clubId,
        public string $announcementId,
        public string $title,
        public string $body,
        public array $emails,
        public ?string $imagePath = null,
        public bool $mergeStats = false,
    ) {}

    public function handle(ClubSmtpMailer $mailer): void
    {
        $sent = [];
        $failed = [];
        $attachmentPath = $this->resolveAttachmentPath();

        try {
            $outbound = $mailer->mailer($this->clubId);
        } catch (\Throwable $e) {
            foreach ($this->emails as $email) {
                if (! is_string($email) || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
                $failed[] = ['email' => $email, 'status' => 'failed', 'error' => $e->getMessage()];
            }
            Log::warning('Announcement email SMTP not configured', [
                'club_id' => $this->clubId,
                'announcement_id' => $this->announcementId,
                'error' => $e->getMessage(),
            ]);
            $this->persistStats($sent, $failed);

            return;
        }

        foreach ($this->emails as $email) {
            if (! is_string($email) || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            try {
                $outbound->raw($this->body, function ($message) use ($email, $attachmentPath) {
                    $message->to($email)->subject($this->title);
                    if ($attachmentPath !== null) {
                        $message->attach($attachmentPath);
                    }
                });
                $sent[] = ['email' => $email, 'status' => 'sent'];
            } catch (\Throwable $e) {
                $failed[] = ['email' => $email, 'status' => 'failed', 'error' => $e->getMessage()];
                Log::warning('Announcement email failed', [
                    'club_id' => $this->clubId,
                    'announcement_id' => $this->announcementId,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->persistStats($sent, $failed);
    }

    /**
     * @param  list<array{email: string, status: string}>  $sent
     * @param  list<array{email: string, status: string, error?: string}>  $failed
     */
    private function persistStats(array $sent, array $failed): void
    {
        $row = Announcement::query()->withoutGlobalScopes()->find($this->announcementId);
        if (! $row) {
            return;
        }

        $meta = is_array($row->meta) ? $row->meta : [];
        $meta['email_stats'] = $this->mergeStats
            ? $this->mergedStats($meta, $sent, $failed)
            : [
                'sentCount' => count($sent),
                'failedCount' => count($failed),
                'recipients' => array_merge($sent, $failed),
                'queued_at' => now()->toIso8601String(),
            ];
        $row->meta = $meta;
        $row->save();
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  list<array{email: string, status: string}>  $sent
     * @param  list<array{email: string, status: string, error?: string}>  $failed
     * @return array{sentCount: int, failedCount: int, recipients: list<array<string, mixed>>, queued_at: string}
     */
    private function mergedStats(array $meta, array $sent, array $failed): array
    {
        $existing = $meta['email_stats'] ?? $meta['emailStats'] ?? [];
        if (! is_array($existing)) {
            $existing = [];
        }

        $byEmail = [];
        foreach ($existing['recipients'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            if ($email !== '') {
                $byEmail[$email] = $row;
            }
        }

        foreach (array_merge($sent, $failed) as $row) {
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            if ($email === '') {
                continue;
            }
            $byEmail[$email] = $row;
        }

        $recipients = array_values($byEmail);
        $sentCount = 0;
        $failedCount = 0;
        foreach ($recipients as $row) {
            if (($row['status'] ?? '') === 'failed') {
                $failedCount++;
            } else {
                $sentCount++;
            }
        }

        return [
            'sentCount' => $sentCount,
            'failedCount' => $failedCount,
            'recipients' => $recipients,
            'queued_at' => now()->toIso8601String(),
        ];
    }

    private function resolveAttachmentPath(): ?string
    {
        if (! is_string($this->imagePath) || $this->imagePath === '') {
            return null;
        }

        if (! Storage::disk('public')->exists($this->imagePath)) {
            return null;
        }

        return Storage::disk('public')->path($this->imagePath);
    }
}
