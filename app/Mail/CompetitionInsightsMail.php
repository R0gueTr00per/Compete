<?php

namespace App\Mail;

use App\Models\Competition;
use App\Models\CompetitionInsight;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CompetitionInsightsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Competition $competition,
        public readonly CompetitionInsight $insight,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'AI Insights — ' . $this->competition->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.competition-insights',
            with: [
                'competition' => $this->competition,
                'insight'     => $this->insight,
                'sections'    => $this->parseSections($this->insight->content),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }

    private function parseSections(string $content): array
    {
        $raw = preg_split('/(?=^## )/m', $content, -1, PREG_SPLIT_NO_EMPTY);
        return collect($raw)->map(function ($section) {
            $lines   = explode("\n", trim($section), 2);
            $heading = strip_tags(ltrim(trim($lines[0] ?? ''), '# '));
            $body    = strip_tags(trim($lines[1] ?? ''));
            return compact('heading', 'body');
        })->all();
    }
}
