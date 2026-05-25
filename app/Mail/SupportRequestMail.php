<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $fromName,
        public readonly string $fromEmail,
        public readonly string $area,
        public readonly string $notes,
        public readonly string $organisationName,
        public readonly string $organisationSlug,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromEmail, $this->fromName),
            subject: 'Support Request — ' . $this->area . ' [' . $this->fromName . ']',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.support-request',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
