<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class HostingBalanceAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(public array $snapshot) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'TeamSuite: χαμηλό υπόλοιπο Hetzner',
        );
    }

    public function content(): Content
    {
        $snap = $this->snapshot;
        $text = "Το εκτιμώμενο υπόλοιπο hosting (Hetzner) είναι χαμηλό.\n\n"
            .'Κατάσταση: '.($snap['status_label'] ?? '')."\n"
            .'Μηνιαίο κόστος (εκτίμηση): €'.number_format((float) ($snap['monthly_estimate_eur'] ?? 0), 2, ',', '.')."\n"
            .'Υπόλοιπο: €'.number_format((float) ($snap['remaining_eur'] ?? 0), 2, ',', '.')."\n"
            .'Ημέρες περίπου: '.($snap['days_remaining'] ?? '—')."\n\n"
            ."Πρόσθεσε χρήματα στο Hetzner Cloud Console → Billing.\n"
            .'Μετά ενημέρωσε το υπόλοιπο στο TeamSuite → Platform → Κόστος hosting.';

        return new Content(
            htmlString: '<pre style="font-family:sans-serif;white-space:pre-wrap">'.e($text).'</pre>',
        );
    }
}
