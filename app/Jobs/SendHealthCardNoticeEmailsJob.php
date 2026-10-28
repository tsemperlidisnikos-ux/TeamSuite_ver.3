<?php

namespace App\Jobs;

use App\Services\Mail\ClubSmtpMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendHealthCardNoticeEmailsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 1700;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    /**
     * @param  list<array{athlete_id: string, name: string, email: string, expires: string, status: string}>  $recipients
     * @param  list<string>  $staffEmails
     */
    public function __construct(
        public string $clubId,
        public string $clubName,
        public array $recipients,
        public array $staffEmails = [],
    ) {}

    public function handle(ClubSmtpMailer $mailer): void
    {
        try {
            $outbound = $mailer->mailer($this->clubId);
        } catch (\Throwable $e) {
            Log::warning('Health card notice SMTP not configured', [
                'club_id' => $this->clubId,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $day = now()->toDateString();
        $sent = 0;
        $skipped = 0;

        foreach ($this->recipients as $row) {
            $email = $row['email'] ?? null;
            $athleteId = (string) ($row['athlete_id'] ?? '');
            if (! is_string($email) || $email === '' || $athleteId === '') {
                $skipped++;

                continue;
            }

            $cacheKey = "healthcard-notice:{$this->clubId}:{$athleteId}:{$day}";
            if (Cache::has($cacheKey)) {
                $skipped++;

                continue;
            }

            $name = (string) ($row['name'] ?? 'Αθλητής');
            $expires = (string) ($row['expires'] ?? '');
            $status = (string) ($row['status'] ?? 'soon');
            $when = $status === 'expired'
                ? "έληξε στις {$expires}"
                : "λήγει στις {$expires}";
            $subject = 'Κάρτα υγείας — '.$name;
            $body = "{$this->clubName}: η κάρτα υγείας του/της {$name} {$when}. "
                ."Ανανεώστε την και ενημερώστε τον σύλλογο.\n";

            try {
                $outbound->raw($body, function ($message) use ($email, $subject) {
                    $message->to($email)->subject($subject);
                });
                Cache::put($cacheKey, true, now()->endOfDay());
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('Health card notice email failed', [
                    'club_id' => $this->clubId,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
                $skipped++;
            }
        }

        $staffSent = 0;
        if ($this->staffEmails !== [] && $this->recipients !== []) {
            $lines = collect($this->recipients)->map(function (array $row) {
                $name = (string) ($row['name'] ?? '—');
                $expires = (string) ($row['expires'] ?? '—');
                $status = ($row['status'] ?? '') === 'expired' ? 'ληγμένη' : 'λήγει σύντομα';

                return "- {$name}: {$expires} ({$status})";
            })->implode("\n");

            $summary = "Σύνοψη καρτών υγείας — {$this->clubName}\n\n{$lines}\n";
            foreach ($this->staffEmails as $staffEmail) {
                if (! is_string($staffEmail) || $staffEmail === '') {
                    continue;
                }
                try {
                    $outbound->raw($summary, function ($message) use ($staffEmail) {
                        $message->to($staffEmail)->subject('Σύνοψη λήξεων καρτών υγείας');
                    });
                    $staffSent++;
                } catch (\Throwable $e) {
                    Log::warning('Health card staff summary failed', [
                        'club_id' => $this->clubId,
                        'email' => $staffEmail,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info('SendHealthCardNoticeEmailsJob finished', [
            'club_id' => $this->clubId,
            'sent' => $sent,
            'skipped' => $skipped,
            'staff' => $staffSent,
        ]);
    }
}
