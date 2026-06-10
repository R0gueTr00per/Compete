<?php

namespace App\Services;

use App\Models\Competition;
use App\Models\Enrolment;

class EnrolmentSummaryService
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/openai';
    private const MODEL    = 'gemini-2.5-flash';

    public function generateForCompetition(Competition $competition): void
    {
        $enrolments = Enrolment::where('competition_id', $competition->id)
            ->whereNotIn('status', ['withdrawn', 'draft'])
            ->whereNull('ai_summary')
            ->with([
                'competitor',
                'activeEvents.competitionEvent',
                'activeEvents.division',
                'activeEvents.result',
            ])
            ->get();

        if ($enrolments->isEmpty()) {
            return;
        }

        $payload = $enrolments->map(function (Enrolment $enrolment) {
            $events = $enrolment->activeEvents->map(function ($ee) {
                $result = $ee->result;
                return [
                    'event'        => $ee->competitionEvent?->name,
                    'division'     => $ee->division?->label,
                    'place'        => $result?->placement,
                    'score'        => $result?->total_score ? (float) $result->total_score : null,
                    'disqualified' => $result?->disqualified ?? false,
                    'forfeited'    => $result?->forfeited ?? false,
                    'win_loss'     => $result?->win_loss,
                ];
            })->values()->all();

            return [
                'id'     => $enrolment->id,
                'name'   => $enrolment->competitor?->first_name,
                'events' => $events,
            ];
        })->values()->all();

        $systemPrompt = <<<PROMPT
Write a short, natural-sounding competition summary for each martial arts competitor. Guidelines:
- 3–5 sentences total. Write as flowing prose, not a list.
- Use the competitor's first name once near the start, then vary naturally (e.g. "they", "their").
- Lead with their strongest results. Weave in unplaced events briefly ("also competing in...").
- For placed events: name the event and placement (e.g. "took 1st in Kata"). No need to quote the full division label — keep it light.
- For events with no recorded placement: briefly acknowledge participation without dwelling on it.
- Skip disqualified or forfeited events entirely.
- Close with genuine, specific encouragement tied to their actual performance.
- Never use the word "competed" more than once. Never start consecutive sentences with the same word.

Return ONLY a valid JSON array. Format: [{"id": 123, "summary": "..."}, ...]
PROMPT;

        $factory = \OpenAI::factory()
            ->withApiKey(config('services.google_ai.api_key'))
            ->withBaseUri(self::BASE_URL);

        if (app()->isLocal()) {
            $factory = $factory->withHttpClient(new \GuzzleHttp\Client(['verify' => false]));
        }

        $client = $factory->make();

        foreach (array_chunk($payload, 5) as $i => $chunk) {
            if ($i > 0) sleep(3);

            $content = null;
            foreach (range(1, 4) as $attempt) {
                try {
                    $response = $client->chat()->create([
                        'model'    => self::MODEL,
                        'messages' => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user',   'content' => 'Generate summaries for these competitors: ' . json_encode($chunk)],
                        ],
                        'max_tokens' => 16000,
                    ]);
                    $content = trim($response->choices[0]->message->content ?? '');
                    break;
                } catch (\Throwable $e) {
                    if ($attempt === 4) {
                        \Illuminate\Support\Facades\Log::warning('[EnrolmentSummary] chunk failed after 4 attempts', ['i' => $i, 'error' => $e->getMessage()]);
                        continue 2;
                    }
                    sleep($attempt * 5);
                }
            }

            $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
            $content = preg_replace('/\s*```\s*$/i', '', $content);
            $content = trim($content);

            $summaries = json_decode($content, true);
            if (! is_array($summaries)) {
                \Illuminate\Support\Facades\Log::warning('[EnrolmentSummary] json_decode failed', ['i' => $i]);
                continue;
            }

            $map = collect($summaries)->keyBy('id');
            foreach ($enrolments as $enrolment) {
                $summary = $map->get($enrolment->id)['summary'] ?? null;
                if ($summary) {
                    $enrolment->update(['ai_summary' => $summary]);
                }
            }
        }
    }
}
