<?php

namespace App\Mail;

use App\Models\Competition;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CompetitionReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Competition $competition) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Don\'t miss out — ' . $this->competition->name,
        );
    }

    public function content(): Content
    {
        $org    = $this->competition->organisation;
        $portal = config('app.scheme') . '://' . $org->slug . '.' . config('app.domain', 'kompetic.com') . '/portal';

        return new Content(
            markdown: 'emails.competition-reminder',
            with: [
                'competition' => $this->competition,
                'org'         => $org,
                'portalUrl'   => $portal,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
