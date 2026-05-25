<?php

namespace App\Services;

use App\Models\Competition;
use App\Models\CompetitionInsight;
use App\Models\Division;
use App\Models\EnrolmentEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CompetitionInsightService
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/openai';

    // Preferred model keywords in priority order (first match wins)
    private const MODEL_PREFERENCES = [
        'gemini-2.5-flash',
        'gemini-2.5-pro',
        'gemini-2.0-flash',
        'gemini-1.5-flash',
        'gemini-1.5-pro',
        'gemini',
    ];

    private function resolveModel(): string
    {
        return Cache::remember('google_ai_model', now()->addHours(24), function () {
            $apiKey = config('services.google_ai.api_key');

            $response = Http::get(
                self::BASE_URL . '/models',
                ['key' => $apiKey]
            );

            if (! $response->successful()) {
                return 'gemini-2.5-flash';
            }

            $ids = collect($response->json('data', []))->pluck('id');

            foreach (self::MODEL_PREFERENCES as $preference) {
                $match = $ids->first(fn ($id) => str_starts_with($id, $preference));
                if ($match) {
                    return $match;
                }
            }

            return $ids->first() ?? 'gemini-2.5-flash';
        });
    }

    public function generate(Competition $competition): CompetitionInsight
    {
        $model = $this->resolveModel();
        $data  = $this->buildDataContext($competition);

        $client = \OpenAI::factory()
            ->withApiKey(config('services.google_ai.api_key'))
            ->withBaseUri(self::BASE_URL)
            ->make();

        $systemPrompt = $this->buildSystemPrompt($competition);
        $userPrompt   = $this->buildUserPrompt($data);

        $response = $client->chat()->create([
            'model'    => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userPrompt],
            ],
            'max_tokens' => 3000,
        ]);

        $content = $response->choices[0]->message->content;

        return CompetitionInsight::updateOrCreate(
            ['competition_id' => $competition->id],
            [
                'content'       => $content,
                'data_snapshot' => $data,
                'model_used'    => $model,
                'generated_at'  => now(),
            ]
        );
    }

    public function buildDataContext(Competition $competition): array
    {
        $competition->load([
            'competitionEvents.divisions.activeEnrolmentEvents',
            'ageBands',
            'rankBands',
            'enrolments.competitor',
            'enrolments.rank',
            'enrolments.activeEvents',
            'competitionLocations',
            'organisation',
        ]);

        // --- Enrolment stats ---
        $enrolments       = $competition->enrolments;
        $pending          = $enrolments->whereNotIn('status', ['withdrawn'])->count();
        $withdrawn        = $enrolments->where('status', 'withdrawn')->count();
        $lateCount        = $enrolments->where('is_late', true)->count();
        $outstandingEnrol = $enrolments->where('payment_status', 'outstanding')->where('status', '!=', 'withdrawn');
        $receivedEnrol    = $enrolments->where('payment_status', 'received');
        $outstandingAmt   = $outstandingEnrol->sum('fee_calculated');
        $receivedAmt      = $receivedEnrol->sum('fee_calculated');

        // --- Competitor demographics ---
        $activeEnrolments = $enrolments->whereNotIn('status', ['withdrawn']);
        $competitors      = $activeEnrolments->map(fn ($e) => $e->competitor)->filter()->unique('id');
        $maleCount        = $competitors->where('gender', 'M')->count();
        $femaleCount      = $competitors->where('gender', 'F')->count();

        // Dojo breakdown
        $dojos = $activeEnrolments->groupBy('dojo_name')
            ->map(fn ($g) => $g->count())
            ->sortDesc()
            ->filter(fn ($c, $name) => !empty($name));

        // Age band breakdown
        $ageBandBreakdown = [];
        foreach ($competition->ageBands as $band) {
            $count = $competitors->filter(fn ($c) => $c && $band->matchesAge($c->age))->count();
            if ($count > 0) {
                $ageBandBreakdown[$band->label] = $count;
            }
        }

        // Rank band breakdown — only enrolments with a recorded rank
        $enrolmentsWithRank    = $activeEnrolments->filter(fn ($e) => $e->rank_id !== null);
        $enrolmentsWithoutRank = $activeEnrolments->filter(fn ($e) => $e->rank_id === null)->count();

        $rankBandBreakdown = $enrolmentsWithRank
            ->groupBy(fn ($e) => $e->rank->name)
            ->map(fn ($g) => $g->count())
            ->sortDesc()
            ->all();

        // --- Event/division stats ---
        $eventData = $competition->competitionEvents->map(function ($event) {
            $divisions      = $event->divisions;
            $totalDivisions = $divisions->count();
            $emptyDivisions = $divisions->filter(fn ($d) => $d->activeEnrolmentEvents->isEmpty())->count();
            $competitorIds  = $divisions->flatMap(fn ($d) => $d->activeEnrolmentEvents)->unique('enrolment_id');

            return [
                'name'              => $event->name,
                'format'            => $event->effectiveTournamentFormat(),
                'scoring'           => $event->effectiveScoringMethod(),
                'status'            => $event->status,
                'total_divisions'   => $totalDivisions,
                'empty_divisions'   => $emptyDivisions,
                'competitor_count'  => $competitorIds->count(),
            ];
        })->values()->all();

        // --- Division summary ---
        $eventIds       = $competition->competitionEvents->pluck('id');
        $allDivisions   = Division::whereIn('competition_event_id', $eventIds)->get();
        $divTotal       = $allDivisions->count();
        $divEmpty       = $allDivisions->filter(fn ($d) => EnrolmentEvent::where('division_id', $d->id)->where('removed', false)->doesntExist())->count();
        $divUnassigned  = $allDivisions->whereNull('location_label')->whereNotIn('status', ['combined'])->count();

        // --- Competition metadata ---
        $daysUntil = now()->startOfDay()->diffInDays($competition->competition_date, false);

        return [
            'name'              => $competition->name,
            'date'              => $competition->competition_date->format('d M Y'),
            'days_until'        => (int) $daysUntil,
            'status'            => $competition->status,
            'enrolment_due'     => $competition->enrolment_due_date?->format('d M Y') ?? 'not set',
            'locations'         => $competition->competitionLocations->pluck('name')->join(', ') ?: 'not set',
            'enrolments'        => [
                'total'              => $enrolments->count(),
                'active'             => $pending,
                'withdrawn'          => $withdrawn,
                'late'               => $lateCount,
                'payment_outstanding_count'  => $outstandingEnrol->count(),
                'payment_outstanding_amount' => round($outstandingAmt, 2),
                'payment_received_count'     => $receivedEnrol->count(),
                'payment_received_amount'    => round($receivedAmt, 2),
            ],
            'competitors'       => [
                'total'   => $competitors->count(),
                'male'    => $maleCount,
                'female'  => $femaleCount,
                'dojos'   => $dojos->count(),
                'top_dojos' => $dojos->take(5)->all(),
            ],
            'age_bands'         => $ageBandBreakdown,
            'rank_bands'             => $rankBandBreakdown,
            'competitors_no_rank'    => $enrolmentsWithoutRank,
            'events'            => $eventData,
            'divisions'         => [
                'total'      => $divTotal,
                'empty'      => $divEmpty,
                'unassigned' => $divUnassigned,
            ],
        ];
    }

    private function buildSystemPrompt(Competition $competition): string
    {
        $orgContext = $competition->organisation?->ai_context
            ? "\n\n" . $competition->organisation->ai_context
            : '';

        return <<<PROMPT
You are an AI assistant for Kompetic, a martial arts competition management platform.{$orgContext}

You provide structured insights to competition organisers. Always respond using exactly these four section headings in markdown — no other headings:

## ✅ Action Items
List specific things requiring immediate organiser attention, ordered by urgency. Be direct and reference actual numbers. For each item, include a concrete actionable recommendation on how to address it.

## 📊 Participation Patterns
Summarise competitor demographics: age bands, rank distribution, gender balance, dojo spread.

## 🏆 Event & Division Readiness
Assess each event: competitor counts, empty divisions, format implications. Flag any events at risk of not running, and recommend what the organiser should do about each.

## 💰 Financial Summary
Fees received, outstanding, projected total. Note any patterns.

Rules:
- Be thorough and specific — reference actual numbers from the data, and include multiple bullet points per section where relevant
- For any issue or risk identified anywhere in the response, always pair it with a concrete recommendation
- If a section has nothing notable, say so in one sentence
- Do not invent information not present in the data
- Use plain language suitable for a non-technical sports administrator
PROMPT;
    }

    private function buildUserPrompt(array $data): string
    {
        $daysLabel = $data['days_until'] >= 0
            ? "{$data['days_until']} days away"
            : abs($data['days_until']) . ' days ago';

        $topDojos = collect($data['competitors']['top_dojos'])
            ->map(fn ($count, $name) => "{$name} ({$count})")
            ->join(', ');

        $ageBands = collect($data['age_bands'])
            ->map(fn ($count, $label) => "{$label}: {$count}")
            ->join(', ') ?: 'none configured';

        $rankBands = collect($data['rank_bands'])
            ->map(fn ($count, $label) => "{$label}: {$count}")
            ->join(', ') ?: 'none recorded';

        $noRankNote = '';

        $events = collect($data['events'])->map(function ($e) {
            return "- {$e['name']} | Format: {$e['format']} | Scoring: {$e['scoring']} | "
                . "Competitors: {$e['competitor_count']} | "
                . "Divisions: {$e['total_divisions']} total, {$e['empty_divisions']} empty";
        })->join("\n");

        $e   = $data['enrolments'];
        $c   = $data['competitors'];
        $d   = $data['divisions'];

        $eventCount = count($data['events']);

        return <<<PROMPT
Analyse this competition and provide organiser insights.

COMPETITION
Name: {$data['name']}
Date: {$data['date']} ({$daysLabel})
Status: {$data['status']}
Enrolment closes: {$data['enrolment_due']}
Locations: {$data['locations']}

EVENTS ({$eventCount} total)
{$events}

ENROLMENTS
Total: {$e['total']} | Active: {$e['active']} | Withdrawn: {$e['withdrawn']}
Late enrolments: {$e['late']}
Payments outstanding: {$e['payment_outstanding_count']} enrolments | \${$e['payment_outstanding_amount']}
Payments received: {$e['payment_received_count']} enrolments | \${$e['payment_received_amount']}

COMPETITOR DEMOGRAPHICS
Unique competitors: {$c['total']} ({$c['male']} male, {$c['female']} female)
Age bands: {$ageBands}
Rank bands: {$rankBands}{$noRankNote}
Dojos represented: {$c['dojos']} | Top: {$topDojos}

DIVISION STATUS
Total: {$d['total']} | Empty: {$d['empty']} | Unassigned to location: {$d['unassigned']}
PROMPT;
    }
}
