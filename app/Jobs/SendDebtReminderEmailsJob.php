<?php

namespace App\Jobs;

use App\Services\Mail\ClubSmtpMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendDebtReminderEmailsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 1700;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    /**
     * @param  list<array{athlete_id: string, name: string, email: string, amount: float}>  $recipients
     */
    public function __construct(
        public string $clubId,
        public array $recipients,
    ) {}

    public function handle(ClubSmtpMailer $mailer): void
    {
        try {
            $outbound = $mailer->mailer($this->clubId);
        } catch (\Throwable $e) {
            Log::warning('Debt reminder SMTP not configured', [
                'club_id' => $this->clubId,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        foreach ($this->recipients as $row) {
            $email = $row['email'] ?? null;
            if (! is_string($email) || $email === '') {
                continue;
            }

            $name = (string) ($row['name'] ?? 'Αθλητής');
            $amount = number_format((float) ($row['amount'] ?? 0), 2, ',', '.');
            $subject = 'Υπενθύμιση οφειλής — '.$name;
            $body = "Αγαπητέ/ή κηδεμόνα,\n\n"
                ."Υπάρχει εκκρεμής οφειλή για τον/την αθλητή/τρια {$name} "
                ."ύψους {$amount} €.\n\n"
                ."Παρακαλούμε τακτοποιήστε την οφειλή το συντομότερο δυνατό.\n\n"
                .'Με εκτίμηση';

            try {
                $outbound->raw($body, function ($message) use ($email, $subject) {
                    $message->to($email)->subject($subject);
                });
            } catch (\Throwable $e) {
                Log::warning('Debt reminder email failed', [
                    'club_id' => $this->clubId,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
