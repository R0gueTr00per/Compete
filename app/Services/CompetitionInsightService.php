<?php

namespace App\Services;

use App\Models\Competition;
use App\Models\CompetitionInsight;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CompetitionInsightService
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/openai';

    // Preferred model keywords in priority order (first match wins)
    private const MODEL_PREFERENCES = [
        'gemini-2.5-flash',
        'gemini-2.0-flash',
        'gemini',
    ];

    private function resolveModel(): string
    {
        return Cache::remember('google_ai_model_v4', now()->addHours(24), function () {
            $apiKey = config('services.google_ai.api_key');

            $response = Http::timeout(10)->get(
                self::BASE_URL . '/models',
                ['key' => $apiKey]
            );

            if (! $response->successful()) {
                return 'gemini-2.5-flash';
            }

            // Strip models/ prefix Google sometimes includes in IDs
            $ids = collect($response->json('data', []))
                ->pluck('id')
                ->map(fn ($id) => str_starts_with($id, 'models/') ? substr($id, 7) : $id);

            foreach (self::MODEL_PREFERENCES as $preference) {
                $match = $ids->first(fn ($id) => str_starts_with($id, $preference));
                if ($match) {
                    return $match;
                }
            }

            return $ids->first() ?? 'gemini-2.0-flash';
        });
    }

    public function generate(Competition $competition, int $generation = 0): ?CompetitionInsight
    {
        $model  = $this->resolveModel();
        $status = $competition->status;

        if (in_array($status, ['planning', 'advertise'])) {
            $data         = $this->buildPlanningDataContext($competition);
            $systemPrompt = $this->buildPlanningSystemPrompt($competition);
            $userPrompt   = $this->buildPlanningUserPrompt($data);
        } elseif (in_array($status, ['enrolments_closed', 'running'])) {
            $data         = $this->buildRunningDataContext($competition);
            $systemPrompt = $this->buildRunningSystemPrompt($competition);
            $userPrompt   = $this->buildRunningUserPrompt($data);
        } elseif ($status === 'complete') {
            $data         = $this->buildCompleteDataContext($competition);
            $systemPrompt = $this->buildCompleteSystemPrompt($competition);
            $userPrompt   = $this->buildCompleteUserPrompt($data);
        } elseif ($status === 'enrolments_closed') {
            $data         = $this->buildDataContext($competition);
            $systemPrompt = $this->buildClosedSystemPrompt($competition);
            $userPrompt   = $this->buildClosedUserPrompt($data);
        } else {
            $data         = $this->buildDataContext($competition);
            $systemPrompt = $this->buildSystemPrompt($competition);
            $userPrompt   = $this->buildUserPrompt($data);
        }

        $factory = \OpenAI::factory()
            ->withApiKey(config('services.google_ai.api_key'))
            ->withBaseUri(self::BASE_URL);

        if (app()->isLocal()) {
            $factory = $factory->withHttpClient(new \GuzzleHttp\Client(['verify' => false]));
        }

        $client = $factory->make();

        $response = $client->chat()->create([
            'model'    => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userPrompt],
            ],
            'max_tokens' => $status === 'complete' ? 8000 : 4000,
        ]);

        $content = $response->choices[0]->message->content;

        // If a generation number was provided, verify we are still the latest request.
        // A newer dispatchFor() call will have incremented the counter past our number.
        if ($generation > 0 && (int) Cache::get("insights_gen_{$competition->id}") !== $generation) {
            return null;
        }

        return CompetitionInsight::updateOrCreate(
            ['competition_id' => $competition->id],
            [
                'content'       => strip_tags($content ?? ''),
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
        $carts            = $enrolments->map(fn ($e) => $e->cart)->filter()->unique('id');
        $outstandingEnrol = $enrolments->whereNotIn('status', ['withdrawn'])->filter(fn ($e) => ! $e->cart?->isPaid());
        $receivedEnrol    = $enrolments->filter(fn ($e) => $e->cart?->isPaid());
        $outstandingAmt   = $outstandingEnrol->sum('fee_calculated');
        $receivedAmt      = $receivedEnrol->sum('fee_calculated');

        // --- Competitor demographics ---
        $activeEnrolments  = $enrolments->whereNotIn('status', ['withdrawn']);
        $competitors       = $activeEnrolments->map(fn ($e) => $e->competitor)->filter()->unique('id');
        $checkedInIdSet    = $enrolments->where('checked_in', true)->pluck('id')->flip()->all();

        // Enrolments over time
        $enrolledLast7Days  = $activeEnrolments->filter(fn ($e) => $e->created_at >= now()->subDays(7))->count();
        $enrolledLast30Days = $activeEnrolments->filter(fn ($e) => $e->created_at >= now()->subDays(30))->count();
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
            $count = $competitors->filter(fn ($c) => $c && $c->age !== null && $band->matchesAge($c->age))->count();
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
        $isPastPlanning = ! in_array($competition->status, ['planning', 'advertise']);

        $eventData = $competition->competitionEvents->map(function ($event) use ($checkedInIdSet, $isPastPlanning) {
            $divisions      = $isPastPlanning
                ? $event->divisions->filter(fn ($d) => !empty($d->location_label) || $d->status === 'combined')
                : $event->divisions;
            $totalDivisions = $divisions->count();

            if ($isPastPlanning && $totalDivisions === 0) {
                return null;
            }
            $allEventEEs    = $divisions->flatMap(fn ($d) => $d->activeEnrolmentEvents);
            $competitorIds  = $allEventEEs->unique('enrolment_id');
            $emptyDivisions = $divisions->filter(fn ($d) => $d->activeEnrolmentEvents->isEmpty())->count();

            $eventTarget       = $event->default_max_competitors;
            $divisionsAtTarget = $eventTarget
                ? $divisions->filter(fn ($d) => $d->activeEnrolmentEvents->isNotEmpty() && $d->activeEnrolmentEvents->count() >= ($d->max_competitors ?? $eventTarget))->count()
                : null;

            $checkedIn = $competitorIds->filter(fn ($ee) => isset($checkedInIdSet[$ee->enrolment_id]))->count();

            return [
                'name'                  => $event->name,
                'format'                => $event->effectiveTournamentFormat(),
                'scoring'               => $event->effectiveScoringMethod(),
                'status'                => $event->status,
                'total_divisions'       => $totalDivisions,
                'empty_divisions'       => $emptyDivisions,
                'competitor_count'      => $competitorIds->count(),
                'checked_in'            => $checkedIn,
                'target_per_division'   => $eventTarget,
                'divisions_at_target'   => $divisionsAtTarget,
            ];
        })->filter()->values()->all();

        // --- Division summary ---
        // Past planning: unscheduled divisions are effectively cancelled — exclude them.
        $allDivisionsLoaded = $competition->competitionEvents->flatMap(fn ($e) => $e->divisions);
        $activeDivisions    = $isPastPlanning
            ? $allDivisionsLoaded->filter(fn ($d) => !empty($d->location_label) || $d->status === 'combined')
            : $allDivisionsLoaded;
        $divUnassigned      = $isPastPlanning ? 0 : $allDivisionsLoaded->whereNull('location_label')->whereNotIn('status', ['combined'])->count();
        $divTotal           = $activeDivisions->count();

        $sizeGroups = $activeDivisions->groupBy(fn ($d) => match (true) {
            $d->activeEnrolmentEvents->count() === 0 => 'empty',
            $d->activeEnrolmentEvents->count() === 1 => 'solo',
            $d->activeEnrolmentEvents->count() <= 3  => 'small',
            default                                  => 'healthy',
        });
        $divEmpty   = ($sizeGroups['empty'] ?? collect())->count();
        $divSolo    = ($sizeGroups['solo'] ?? collect())->count();
        $divSmall   = ($sizeGroups['small'] ?? collect())->count();
        $divHealthy = ($sizeGroups['healthy'] ?? collect())->count();

        // --- Competition metadata ---
        $daysUntil = now()->startOfDay()->diffInDays($competition->competition_date, false);

        return [
            'name'              => $competition->name,
            'date'              => $competition->competition_date->format('d M Y'),
            'days_until'        => (int) $daysUntil,
            'status'            => $competition->status,
            'enrolment_due'     => $competition->enrolment_due_date?->format('d M Y') ?? 'not set',
            'locations'         => $competition->competitionLocations->pluck('name')->join(', ') ?: 'not set',
            'target_competitors' => $competition->target_competitors,
            'enrolments'        => [
                'total'              => $enrolments->count(),
                'active'             => $pending,
                'withdrawn'          => $withdrawn,
                'late'               => $lateCount,
                'last_7_days'        => $enrolledLast7Days,
                'last_30_days'       => $enrolledLast30Days,
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
                'solo'       => $divSolo,
                'small'      => $divSmall,
                'healthy'    => $divHealthy,
                'unassigned' => $divUnassigned,
            ],
        ];
    }

    private static array $TONE_PRESET_TEXT = [
        'humorous'       => 'Use light humour and keep the tone fun and upbeat.',
        'sensei'         => 'Write as a martial arts instructor addressing a student. Use instructional language and a mentoring tone.',
        'motivational'   => 'Use an energetic, motivational coaching tone — high-energy and encouraging.',
        'traditional'    => 'Use formal language that reflects traditional martial arts values: honour, discipline, and respect.',
        'brief'          => 'Be concise. Use short sentences and avoid unnecessary elaboration.',
        'parent_friendly' => 'Use warm, community-focused language appropriate for parents and families.',
    ];

    private function buildOrgContext(Competition $competition): string
    {
        $parts = [];

        if ($text = $competition->organisation?->ai_context) {
            $parts[] = $text;
        }

        $presets = $competition->organisation?->ai_tone_presets ?? [];
        foreach ($presets as $preset) {
            if (isset(self::$TONE_PRESET_TEXT[$preset])) {
                $parts[] = self::$TONE_PRESET_TEXT[$preset];
            }
        }

        return $parts ? "\n\n" . implode(' ', $parts) : '';
    }

    private function buildSystemPrompt(Competition $competition): string
    {
        $orgContext = $this->buildOrgContext($competition);

        return <<<PROMPT
You are an AI assistant for Kompetic, a martial arts competition management platform.{$orgContext}

You provide structured insights to competition organisers while enrolments are open. Always respond using exactly these five section headings in markdown — no other headings:

## ✅ Action Items
Check each of the following and raise a bullet point for every issue found — do not skip any that apply. Reference actual numbers in every bullet. Pair each issue with a concrete recommendation:
- Divisions with zero competitors (empty) — risk of not running
- Divisions with only one competitor — cannot compete in standard format; consider combining or contacting the competitor
- Event types where empty divisions outnumber active ones
- Overall enrolment significantly below target competitor count (if set)
- Enrolment due date within 14 days with low overall numbers — urgency to promote
- Outstanding payments: who owes, how much
- Enrolments in the last 7 days are very low compared to the last 30 days — velocity is slowing
- Any single dojo representing more than 40% of total competitors — competitive balance concern
- Large number of late enrolments relative to total

## 🌟 What's Going Well
Present as bullet points — 2–4 genuine positives: strong enrolment numbers, good dojo diversity, healthy divisions, strong payment collection, good enrolment velocity, or anything else that reflects well on the organiser's efforts. Be specific and reference actual numbers.

## 📊 Participation Patterns
Present as bullet points — competitor demographics: age bands, rank distribution, gender balance, dojo spread. Note any imbalances or surprises.

## 🏆 Event Type & Division Readiness
One bullet per event type: competitor count, empty vs active divisions, format, any risk of not running. Include a recommendation for each at-risk event type.

## 💰 Financial Summary
Present as bullet points: fees received vs outstanding, projected total if all outstanding are collected, any notable patterns.

Rules:
- Use bullet points in every section — never prose paragraphs
- Be thorough — work through the Action Items checklist systematically, do not stop after 2–3 items if more apply
- Format each Action Items bullet as: **Issue name**: explanation and recommendation
- Reference actual numbers in every bullet point
- Pair every issue with a concrete recommendation
- If a section genuinely has nothing notable, say so in one bullet
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
            $targetNote  = $e['target_per_division']
                ? ", target/div: {$e['target_per_division']} ({$e['divisions_at_target']} at/above target)"
                : '';
            $checkinNote = $e['competitor_count'] > 0
                ? " | Checked in: {$e['checked_in']}/{$e['competitor_count']}"
                : '';
            return "- {$e['name']} | Format: {$e['format']} | Scoring: {$e['scoring']} | "
                . "Competitors: {$e['competitor_count']}{$checkinNote} | "
                . "Divisions: {$e['total_divisions']} total, {$e['empty_divisions']} empty{$targetNote}";
        })->join("\n");

        $e   = $data['enrolments'];
        $c   = $data['competitors'];
        $d   = $data['divisions'];

        $eventCount = count($data['events']);

        $targetLine = $data['target_competitors']
            ? "\nTarget competitors: {$data['target_competitors']}"
            : '';

        return <<<PROMPT
Analyse this competition and provide organiser insights.

COMPETITION
Name: {$data['name']}
Date: {$data['date']} ({$daysLabel})
Status: {$data['status']}
Enrolment closes: {$data['enrolment_due']}
Locations: {$data['locations']}{$targetLine}

EVENT TYPES ({$eventCount} total)
{$events}

ENROLMENTS
Total: {$e['total']} | Active: {$e['active']} | Withdrawn: {$e['withdrawn']}
Late enrolments: {$e['late']}
Enrolled in last 7 days: {$e['last_7_days']} | Last 30 days: {$e['last_30_days']}
Payments outstanding: {$e['payment_outstanding_count']} enrolments | \${$e['payment_outstanding_amount']}
Payments received: {$e['payment_received_count']} enrolments | \${$e['payment_received_amount']}

COMPETITOR DEMOGRAPHICS
Unique competitors: {$c['total']} ({$c['male']} male, {$c['female']} female)
Age bands: {$ageBands}
Rank bands: {$rankBands}{$noRankNote}
Dojos represented: {$c['dojos']} | Top: {$topDojos}

DIVISION STATUS
Total: {$d['total']}
Size breakdown: Empty: {$d['empty']} | Solo (1 competitor): {$d['solo']} | Small (2–3): {$d['small']} | Healthy (4+): {$d['healthy']}
PROMPT;
    }

    public function buildPlanningDataContext(Competition $competition): array
    {
        $competition->load([
            'competitionEvents.divisions',
            'ageBands',
            'rankBands',
            'weightClasses',
            'competitionLocations',
            'organisation',
        ]);

        $daysUntil = now()->startOfDay()->diffInDays($competition->competition_date, false);

        $eventData = $competition->competitionEvents->map(function ($event) {
            $divisions  = $event->divisions;
            $scheduled  = $divisions->filter(fn ($d) => $d->location_label !== null)->count();
            $unscheduled = $divisions->filter(fn ($d) => $d->location_label === null && $d->status !== 'combined')->count();

            return [
                'name'                => $event->name,
                'format'              => $event->effectiveTournamentFormat(),
                'scoring'             => $event->effectiveScoringMethod(),
                'divisions_total'     => $divisions->count(),
                'divisions_scheduled' => $scheduled,
                'divisions_unscheduled' => $unscheduled,
                'target_per_division' => $event->default_max_competitors,
            ];
        })->values()->all();

        $allDivisions    = $competition->competitionEvents->flatMap(fn ($e) => $e->divisions);
        $divTotal        = $allDivisions->count();
        $divScheduled    = $allDivisions->filter(fn ($d) => $d->location_label !== null)->count();
        $divUnscheduled  = $allDivisions->filter(fn ($d) => $d->location_label === null && $d->status !== 'combined')->count();

        return [
            'name'               => $competition->name,
            'date'               => $competition->competition_date->format('d M Y'),
            'days_until'         => (int) $daysUntil,
            'status'             => 'planning',
            'start_time'         => $competition->start_time ? \Carbon\Carbon::parse($competition->start_time)->format('g:i a') : null,
            'checkin_time'       => $competition->competitionDays->first()?->checkin_time
                                        ? \Carbon\Carbon::parse($competition->competitionDays->first()->checkin_time)->format('g:i a')
                                        : null,
            'enrolment_due'      => $competition->enrolment_due_date?->format('d M Y'),
            'location_name'      => $competition->location_name,
            'target_competitors' => $competition->target_competitors,
            'locations'          => $competition->competitionLocations->pluck('name')->all(),
            'age_bands'          => $competition->ageBands->pluck('label')->all(),
            'rank_bands'         => $competition->rankBands->pluck('label')->all(),
            'weight_classes'     => $competition->weightClasses->pluck('label')->all(),
            'events'             => $eventData,
            'divisions'          => [
                'total'       => $divTotal,
                'scheduled'   => $divScheduled,
                'unscheduled' => $divUnscheduled,
            ],
        ];
    }

    private function buildPlanningSystemPrompt(Competition $competition): string
    {
        $orgContext = $this->buildOrgContext($competition);

        return <<<PROMPT
You are an AI assistant for Kompetic, a martial arts competition management platform.{$orgContext}

You provide structured insights to help competition organisers prepare for an upcoming competition that is currently in the planning stage. Always respond using exactly these five section headings in markdown — no other headings:

## ✅ Action Items
List specific things requiring immediate attention before the competition can open for enrolments, ordered by urgency. Be direct and reference actual numbers. Pair every issue with a concrete recommendation.

## 🌟 What's Going Well
Present as bullet points — 2–4 genuine positives about the competition setup so far: events configured, divisions generated, bands set up, venue set, or any other solid foundations already in place. Be specific and encouraging.

## 🏗️ Structure Overview
Use bullet points to summarise the event types and division setup. Include a bullet for: total events and their formats, total divisions generated (by event where notable), how many are scheduled vs unscheduled, and whether the structure looks complete.

## 📋 Readiness Checklist
Present as bullet points — one bullet per item reviewed: enrolment due date, start time, check-in time, venue/location, location zones, age/rank/weight bands, target competitor count. Mark each as set or missing.

## 🎯 Capacity Check
Present as bullet points — assess whether the division structure looks appropriate for the target competitor count. Flag any events with no divisions or where targets look too high or low.

Rules:
- Use bullet points in every section — never prose paragraphs
- Be thorough and specific — reference actual numbers and include multiple bullet points per section where relevant
- For any issue identified, always pair it with a concrete recommendation
- If a section has nothing notable, say so in one bullet
- Do not invent information not present in the data
- Use plain language suitable for a non-technical sports administrator
PROMPT;
    }

    private function buildPlanningUserPrompt(array $data): string
    {
        $daysLabel = $data['days_until'] >= 0
            ? "{$data['days_until']} days away"
            : abs($data['days_until']) . ' days ago';

        $locations   = $data['locations'] ? implode(', ', $data['locations']) : 'none configured';
        $ageBands    = $data['age_bands'] ? implode(', ', $data['age_bands']) : 'none';
        $rankBands   = $data['rank_bands'] ? implode(', ', $data['rank_bands']) : 'none';
        $weightClasses = $data['weight_classes'] ? implode(', ', $data['weight_classes']) : 'none';

        $events = collect($data['events'])->map(function ($e) {
            $target = $e['target_per_division'] ? ", target/div: {$e['target_per_division']}" : '';
            return "- {$e['name']} | Format: {$e['format']} | Scoring: {$e['scoring']} | "
                . "Divisions: {$e['divisions_total']} total, {$e['divisions_scheduled']} scheduled, {$e['divisions_unscheduled']} unscheduled{$target}";
        })->join("\n");

        $eventCount = count($data['events']);
        $d = $data['divisions'];

        return <<<PROMPT
Analyse this competition that is currently in the PLANNING stage and provide readiness insights.

COMPETITION
Name: {$data['name']}
Date: {$data['date']} ({$daysLabel})
Venue: {$data['location_name']}
Start time: {$data['start_time']}
Check-in time: {$data['checkin_time']}
Enrolment closes: {$data['enrolment_due']}
Target competitors: {$data['target_competitors']}

EVENT TYPES ({$eventCount} configured)
{$events}

DIVISION SETUP
Total divisions: {$d['total']} | Scheduled to location: {$d['scheduled']} | Unscheduled: {$d['unscheduled']}

BAND CONFIGURATION
Age bands: {$ageBands}
Rank bands: {$rankBands}
Weight classes: {$weightClasses}

LOCATION ZONES
{$locations}
PROMPT;
    }

    // ──────────────────────────────────────────────────────────────
    // CLOSED status
    // ──────────────────────────────────────────────────────────────

    private function buildClosedSystemPrompt(Competition $competition): string
    {
        $orgContext = $this->buildOrgContext($competition);

        return <<<PROMPT
You are an AI assistant for Kompetic, a martial arts competition management platform.{$orgContext}

You provide structured insights to help organisers prepare for a competition that has CLOSED for enrolments and is approaching competition day. Always respond using exactly these five section headings in markdown — no other headings:

## ✅ Action Items
Check ONLY the following — raise one bullet for each that applies, skip items that are not an issue. Do not add anything outside this list:
- Outstanding payments: how many enrolments owe, how much in total, recommend they collect on competition day
- Any logistical gaps not yet addressed (venue access confirmation, check-in table setup)
If none of these apply, write a single bullet: "No urgent action items — competition day prep is on track."

## 🌟 What's Going Well
Present as bullet points — 2–4 genuine positives: strong final enrolment numbers, good payment collection rate, healthy division sizes, strong competitor turnout relative to target, or good dojo diversity. Be specific and reference actual numbers.

## 👥 Competitor & Division Readiness
Present as bullet points: overall enrolment summary, then note any empty or solo divisions with a competition-day recommendation (e.g. merge on the day or cancel). Highlight divisions with very few competitors that may not be viable to run in standard format.

## 🏆 Event Type Overview
One bullet per event type: competitor count, division breakdown, any concerns. Flag event types where empty divisions outnumber active ones.

## 💰 Financial Summary
Present as bullet points: outstanding vs received payments, any patterns worth noting, recommended follow-up needed before competition day.

Rules:
- Use bullet points in every section — never prose paragraphs
- Be thorough and specific — reference actual numbers and include multiple bullet points per section where relevant
- For any issue or risk, always pair it with a concrete recommendation
- Enrolments are closed — the Action Items checklist is fixed; stay strictly within it
- If a section has nothing notable, say so in one bullet
- Do not invent information not present in the data
- Use plain language suitable for a non-technical sports administrator
PROMPT;
    }

    private function buildClosedUserPrompt(array $data): string
    {
        $daysLabel = $data['days_until'] >= 0
            ? "{$data['days_until']} days away"
            : abs($data['days_until']) . ' days ago';

        $events = collect($data['events'])->map(function ($e) {
            $targetNote = $e['target_per_division']
                ? ", target/div: {$e['target_per_division']} ({$e['divisions_at_target']} at/above target)"
                : '';
            return "- {$e['name']} | Format: {$e['format']} | Scoring: {$e['scoring']} | "
                . "Competitors: {$e['competitor_count']} | "
                . "Divisions: {$e['total_divisions']} total, {$e['empty_divisions']} empty{$targetNote}";
        })->join("\n");

        $e = $data['enrolments'];
        $c = $data['competitors'];
        $d = $data['divisions'];

        $topDojos = collect($data['competitors']['top_dojos'])
            ->map(fn ($count, $name) => "{$name} ({$count})")
            ->join(', ');

        $ageBands = collect($data['age_bands'])
            ->map(fn ($count, $label) => "{$label}: {$count}")
            ->join(', ') ?: 'none configured';

        $targetLine = $data['target_competitors']
            ? "\nTarget competitors: {$data['target_competitors']}"
            : '';

        $eventCount = count($data['events']);

        return <<<PROMPT
Analyse this competition that has CLOSED for enrolments and provide pre-competition day insights.

COMPETITION
Name: {$data['name']}
Date: {$data['date']} ({$daysLabel})
Status: closed (enrolments no longer accepted)
Locations: {$data['locations']}{$targetLine}

EVENT TYPES ({$eventCount} total)
{$events}

ENROLMENTS (final)
Active: {$e['active']} | Withdrawn: {$e['withdrawn']} | Late enrolments: {$e['late']}
Payments outstanding: {$e['payment_outstanding_count']} enrolments | \${$e['payment_outstanding_amount']}
Payments received: {$e['payment_received_count']} enrolments | \${$e['payment_received_amount']}

COMPETITOR DEMOGRAPHICS
Unique competitors: {$c['total']} ({$c['male']} male, {$c['female']} female)
Age bands: {$ageBands}
Dojos represented: {$c['dojos']} | Top: {$topDojos}

DIVISION STATUS
Total: {$d['total']}
Size breakdown: Empty: {$d['empty']} | Solo (1 competitor): {$d['solo']} | Small (2–3): {$d['small']} | Healthy (4+): {$d['healthy']}
PROMPT;
    }

    // ──────────────────────────────────────────────────────────────
    // CHECK_IN + RUNNING statuses
    // ──────────────────────────────────────────────────────────────

    public function buildRunningDataContext(Competition $competition): array
    {
        $data = $this->buildDataContext($competition);

        // Add per-event scoring progress (divisions with completed_at set)
        $scoringProgress = $competition->competitionEvents->map(function ($event) {
            $activeDivisions = $event->divisions->filter(
                fn ($d) => !empty($d->location_label) || $d->status === 'combined'
            );
            $scored = $activeDivisions->filter(fn ($d) => $d->completed_at !== null)->count();
            $total  = $activeDivisions->count();

            return [
                'name'   => $event->name,
                'scored' => $scored,
                'total'  => $total,
            ];
        })->values()->all();

        $data['scoring'] = [
            'per_event'    => $scoringProgress,
            'total_scored' => collect($scoringProgress)->sum('scored'),
            'total_active' => collect($scoringProgress)->sum('total'),
        ];

        $data['timing'] = $this->buildTimingData($competition);

        return $data;
    }

    private function buildTimingData(Competition $competition): array
    {
        $scheduler = app(\App\Services\ScheduleCalculatorService::class);

        $activeDivisions = $competition->competitionEvents->flatMap(function ($event) {
            return $event->divisions
                ->filter(fn ($d) => !empty($d->location_label) || $d->status === 'combined')
                ->map(fn ($d) => ['event_name' => $event->name, 'div' => $d]);
        });

        $withPlanned = $activeDivisions->filter(fn ($item) => $item['div']->planned_start_at !== null);
        $started     = $withPlanned->filter(fn ($item) => $item['div']->actual_start_at !== null);

        $driftValues = $started->map(fn ($item) =>
            (int) round($item['div']->planned_start_at->diffInMinutes($item['div']->actual_start_at, false))
        );

        $avgDrift = $driftValues->isNotEmpty() ? round($driftValues->avg(), 1) : null;

        $perEvent = $activeDivisions
            ->groupBy('event_name')
            ->map(function ($items, $eventName) use ($scheduler) {
                $divRows = $items->map(function ($item) use ($scheduler) {
                    $div  = $item['div'];
                    $drift = ($div->planned_start_at && $div->actual_start_at)
                        ? (int) round($div->planned_start_at->diffInMinutes($div->actual_start_at, false))
                        : null;
                    $actualDuration = ($div->actual_start_at && $div->actual_end_at)
                        ? (int) round($div->actual_start_at->diffInMinutes($div->actual_end_at))
                        : null;
                    $plannedDuration = $div->planned_start_at
                        ? ($scheduler->divisionSlotMinutes($div) ?: null)
                        : null;
                    $durationDeviation = ($actualDuration !== null && $plannedDuration !== null)
                        ? $actualDuration - $plannedDuration
                        : null;

                    return [
                        'code'                      => $div->code,
                        'status'                    => $div->status,
                        'planned_start'             => $div->planned_start_at?->format('H:i'),
                        'actual_start'              => $div->actual_start_at?->format('H:i'),
                        'drift_minutes'             => $drift,
                        'planned_duration_minutes'  => $plannedDuration,
                        'actual_duration_minutes'   => $actualDuration,
                        'duration_deviation_minutes' => $durationDeviation,
                    ];
                })->values()->all();

                $drifts    = collect($divRows)->whereNotNull('drift_minutes')->pluck('drift_minutes');
                $durations = collect($divRows)->whereNotNull('actual_duration_minutes')->pluck('actual_duration_minutes');
                $durDevs   = collect($divRows)->whereNotNull('duration_deviation_minutes')->pluck('duration_deviation_minutes');

                return [
                    'event_name'              => $eventName,
                    'divisions'               => $divRows,
                    'avg_drift_minutes'       => $drifts->isNotEmpty()    ? round($drifts->avg(), 1)    : null,
                    'avg_actual_duration'     => $durations->isNotEmpty() ? round($durations->avg(), 1) : null,
                    'avg_duration_deviation'  => $durDevs->isNotEmpty()   ? round($durDevs->avg(), 1)   : null,
                ];
            })->values()->all();

        return [
            'divisions_with_planned'      => $withPlanned->count(),
            'divisions_started'           => $started->count(),
            'divisions_completed_actuals' => $activeDivisions->filter(fn ($i) => $i['div']->actual_end_at !== null)->count(),
            'avg_start_drift_minutes'     => $avgDrift,
            'behind_5min_count'           => $driftValues->filter(fn ($v) => $v > 5)->count(),
            'ahead_5min_count'            => $driftValues->filter(fn ($v) => $v < -5)->count(),
            'per_event'                   => $perEvent,
        ];
    }

    private function buildRunningSystemPrompt(Competition $competition): string
    {
        $orgContext = $this->buildOrgContext($competition);

        return <<<PROMPT
You are an AI assistant for Kompetic, a martial arts competition management platform.{$orgContext}

You provide real-time insights to organisers during an active competition (check-in or running). Always respond using exactly these six section headings in markdown — no other headings:

## ✅ Action Items
Urgent items requiring immediate attention right now. Check each of the following and raise a bullet for every issue found:
- Any event where check-in is zero (competitors enrolled but none checked in) — may indicate a no-show or check-in table issue
- Outstanding payments that need collecting today
- Any event types with no active competitors (empty) that may still be on the run sheet
- Any mat or event running significantly behind schedule (>15 min drift) — flag which division/event and by how much

## 🌟 What's Going Well
Present as bullet points — 2–4 genuine positives about how the competition is running: strong check-in rates, good competitor turnout, events running smoothly, scoring progressing well, or the schedule holding to time. Keep it brief and encouraging.

## 🏃 Scoring Progress
Present as bullet points — one bullet per event type: how many divisions are scored vs remaining. State the counts factually — do not characterise low percentages as a problem, since competitions score sequentially and progress naturally increases throughout the day. Only flag an event type if it has fallen notably behind all other event types for no apparent reason.

## ⏱️ Schedule Status
Present as bullet points: overall average start drift (positive = running late, negative = running early), one bullet per event type noting average drift and any divisions that stand out as significantly late or early. If no planned times are set, say so in one bullet.

## 👥 Check-in Status
Present as bullet points: overall check-in rate, then one bullet per event type noting the check-in count vs enrolled. Keep observations factual — check-in typically happens close to event start time, so low rates early in the day are normal.

## 💰 Outstanding Payments
Present as bullet points: any payments still outstanding on competition day, and whether these need to be collected now or can be followed up post-event.

Rules:
- Use bullet points in every section — never prose paragraphs
- This is an active competition — prioritise immediacy and actionability over speculation
- Report counts and rates factually; do not frame normal in-progress states as problems
- Format each Action Items bullet as: **Issue name**: explanation and recommendation
- Reference actual numbers in every bullet
- Pair every genuine issue with a concrete recommendation
- If a section has nothing notable, say so in one bullet
- Do not invent information not present in the data
- Use plain language suitable for a non-technical sports administrator
PROMPT;
    }

    private function buildRunningUserPrompt(array $data): string
    {
        $daysLabel = $data['days_until'] >= 0
            ? "{$data['days_until']} days away"
            : (abs($data['days_until']) === 0 ? 'today' : abs($data['days_until']) . ' days ago');

        $s = $data['scoring'];
        $t = $data['timing'];

        $scoringTotal = $s['total_active'] > 0
            ? round(($s['total_scored'] / $s['total_active']) * 100) . '%'
            : 'n/a';

        $scoringLines = collect($s['per_event'])
            ->map(fn ($e) => "- {$e['name']}: {$e['scored']}/{$e['total']} divisions scored")
            ->join("\n");

        $events = collect($data['events'])->map(function ($e) {
            $checkinRate = $e['competitor_count'] > 0
                ? round(($e['checked_in'] / $e['competitor_count']) * 100) . '%'
                : 'n/a';
            return "- {$e['name']} | Competitors: {$e['competitor_count']} | "
                . "Checked in: {$e['checked_in']} ({$checkinRate}) | "
                . "Divisions: {$e['total_divisions']} total, {$e['empty_divisions']} empty";
        })->join("\n");

        $en = $data['enrolments'];
        $eventCount = count($data['events']);

        $avgDriftStr = $t['avg_start_drift_minutes'] !== null
            ? ($t['avg_start_drift_minutes'] >= 0 ? '+' . $t['avg_start_drift_minutes'] : (string) $t['avg_start_drift_minutes']) . ' min'
            : 'no data';

        $timingEventLines = collect($t['per_event'])->map(function ($e) {
            $avgDrift = $e['avg_drift_minutes'] !== null
                ? ($e['avg_drift_minutes'] >= 0 ? '+' . $e['avg_drift_minutes'] : (string) $e['avg_drift_minutes']) . ' min avg drift'
                : 'no timing data';
            $avgDur = $e['avg_actual_duration'] !== null ? ", avg actual duration: {$e['avg_actual_duration']} min" : '';
            $avgDev = $e['avg_duration_deviation'] !== null
                ? ', avg duration deviation: ' . ($e['avg_duration_deviation'] >= 0 ? '+' : '') . $e['avg_duration_deviation'] . ' min vs plan'
                : '';

            $divLines = collect($e['divisions'])
                ->filter(fn ($d) => $d['planned_start'] !== null || $d['actual_start'] !== null)
                ->map(function ($d) {
                    $driftStr = $d['drift_minutes'] !== null
                        ? ($d['drift_minutes'] >= 0 ? '+' . $d['drift_minutes'] : (string) $d['drift_minutes']) . 'min'
                        : '—';
                    $durStr = $d['actual_duration_minutes'] !== null
                        ? ", ran {$d['actual_duration_minutes']}min" . ($d['planned_duration_minutes'] !== null ? " (plan: {$d['planned_duration_minutes']}min)" : '')
                        : '';
                    $devStr = $d['duration_deviation_minutes'] !== null
                        ? ', dev: ' . ($d['duration_deviation_minutes'] >= 0 ? '+' : '') . $d['duration_deviation_minutes'] . 'min'
                        : '';
                    $planned = $d['planned_start'] ?? '—';
                    $actual  = $d['actual_start'] ?? 'not started';
                    return "  - {$d['code']} ({$d['status']}): planned {$planned} → actual {$actual} ({$driftStr}{$durStr}{$devStr})";
                })->join("\n");

            return "- {$e['event_name']}: {$avgDrift}{$avgDur}{$avgDev}" . ($divLines ? "\n{$divLines}" : '');
        })->join("\n");

        return <<<PROMPT
Analyse this competition that is currently IN PROGRESS and provide real-time insights.

COMPETITION
Name: {$data['name']}
Date: {$data['date']} ({$daysLabel})
Status: {$data['status']}
Locations: {$data['locations']}

SCORING PROGRESS
Overall: {$s['total_scored']}/{$s['total_active']} divisions scored ({$scoringTotal})
{$scoringLines}

SCHEDULE TIMING
{$t['divisions_with_planned']} divisions with planned times | {$t['divisions_started']} started | {$t['divisions_completed_actuals']} completed with actuals
Overall avg start drift: {$avgDriftStr} | Behind >5min: {$t['behind_5min_count']} | Ahead >5min: {$t['ahead_5min_count']}
{$timingEventLines}

CHECK-IN & EVENT TYPES ({$eventCount} total)
{$events}

PAYMENTS
Outstanding: {$en['payment_outstanding_count']} enrolments | \${$en['payment_outstanding_amount']}
Received: {$en['payment_received_count']} enrolments | \${$en['payment_received_amount']}
PROMPT;
    }

    // ──────────────────────────────────────────────────────────────
    // COMPLETE status
    // ──────────────────────────────────────────────────────────────

    public function buildCompleteDataContext(Competition $competition): array
    {
        $data = $this->buildDataContext($competition);

        // Add division outcome breakdown
        $allDivisions = $competition->competitionEvents->flatMap(fn ($e) => $e->divisions);
        $activeDivisions = $allDivisions->filter(fn ($d) => !empty($d->location_label) || $d->status === 'combined');

        $scored    = $activeDivisions->filter(fn ($d) => $d->completed_at !== null)->count();
        $unscored  = $activeDivisions->filter(fn ($d) => $d->completed_at === null && $d->activeEnrolmentEvents->isNotEmpty())->count();
        $cancelled = $allDivisions->filter(fn ($d) => $d->location_label === null && $d->status !== 'combined')->count();

        $data['outcomes'] = [
            'divisions_scored'    => $scored,
            'divisions_unscored'  => $unscored,
            'divisions_cancelled' => $cancelled,
        ];

        $data['timing'] = $this->buildTimingData($competition);

        return $data;
    }

    private function buildCompleteSystemPrompt(Competition $competition): string
    {
        $orgContext = $this->buildOrgContext($competition);

        return <<<PROMPT
You are an AI assistant for Kompetic, a martial arts competition management platform.{$orgContext}

You provide a retrospective analysis of a completed competition to help organisers understand outcomes and improve future events. Always respond using exactly these six section headings in markdown — no other headings. Use bullet points in every section — no prose paragraphs.

## 🌟 Highlights
Present 3–5 genuine achievements as bullet points. Reference actual numbers. Cover any of: strong or above-target turnout, high division completion rate, good payment collection, dojo diversity, high check-in rate, schedule discipline. Skip any that don't genuinely apply.

## 📊 Results Summary
Summarise outcomes as bullet points covering:
- Final competitor and enrolment counts vs target (if set)
- Withdrawal and late enrolment rates
- Demographic highlights (age bands, rank spread, gender balance, dojo count)
- Any surprises or notable patterns

## 🏆 Event Type Outcomes
One bullet per event type. Format: **Event type name**: final competitor count, divisions that ran vs cancelled, whether it over- or under-performed. Note any event types with a high cancelled division rate.

## ⏱️ Timing Analysis
Present as bullet points:
- Overall schedule discipline: average start drift across all divisions (positive = late, negative = early)
- One bullet per event type: average start drift and average actual division duration where data exists
- Flag any event type whose actual duration suggests the per-division time estimate needs adjusting
- If no timing data is available, say so in a single bullet

## 💰 Financial Outcome
Present as bullet points:
- Total fees received and outstanding
- Projected total if all outstanding are collected
- Any notable patterns (e.g. high outstanding rate)
- Recommended follow-up for unpaid enrolments

## 🔍 Recommendations for Next Competition
3–5 bullet points. Format each as: **Recommendation**: one-sentence explanation referencing the data. Cover division structure, enrolment timing, capacity planning, and time allocation — especially if event types consistently ran over their scheduled slot.

Rules:
- This is a retrospective — focus on what happened, not what might happen
- Use bullet points in every section — never prose paragraphs
- Format named bullets as: **Item name**: explanation
- Reference actual numbers in every bullet
- If a section genuinely has nothing notable, say so in one bullet
- Do not invent information not present in the data
- Use plain language suitable for a non-technical sports administrator
PROMPT;
    }

    private function buildCompleteUserPrompt(array $data): string
    {
        $events = collect($data['events'])->map(function ($e) {
            $targetNote = $e['target_per_division']
                ? ", target/div: {$e['target_per_division']} ({$e['divisions_at_target']} at/above target)"
                : '';
            return "- {$e['name']} | Format: {$e['format']} | Scoring: {$e['scoring']} | "
                . "Competitors: {$e['competitor_count']} | "
                . "Divisions: {$e['total_divisions']} total, {$e['empty_divisions']} empty{$targetNote}";
        })->join("\n");

        $e  = $data['enrolments'];
        $c  = $data['competitors'];
        $d  = $data['divisions'];
        $o  = $data['outcomes'];
        $t  = $data['timing'];

        $topDojos = collect($data['competitors']['top_dojos'])
            ->map(fn ($count, $name) => "{$name} ({$count})")
            ->join(', ');

        $ageBands = collect($data['age_bands'])
            ->map(fn ($count, $label) => "{$label}: {$count}")
            ->join(', ') ?: 'none configured';

        $rankBands = collect($data['rank_bands'])
            ->map(fn ($count, $label) => "{$label}: {$count}")
            ->join(', ') ?: 'none recorded';

        $targetLine = $data['target_competitors']
            ? "\nTarget competitors: {$data['target_competitors']}"
            : '';

        $eventCount = count($data['events']);

        $avgDriftStr = $t['avg_start_drift_minutes'] !== null
            ? ($t['avg_start_drift_minutes'] >= 0 ? '+' . $t['avg_start_drift_minutes'] : (string) $t['avg_start_drift_minutes']) . ' min'
            : 'no data';

        $timingEventLines = collect($t['per_event'])->map(function ($ev) {
            $avgDrift = $ev['avg_drift_minutes'] !== null
                ? ($ev['avg_drift_minutes'] >= 0 ? '+' . $ev['avg_drift_minutes'] : (string) $ev['avg_drift_minutes']) . ' min avg drift'
                : 'no timing data';
            $avgDur = $ev['avg_actual_duration'] !== null ? ", avg actual duration: {$ev['avg_actual_duration']} min" : '';
            $avgDev = $ev['avg_duration_deviation'] !== null
                ? ', avg duration deviation: ' . ($ev['avg_duration_deviation'] >= 0 ? '+' : '') . $ev['avg_duration_deviation'] . ' min vs plan'
                : '';

            return "- {$ev['event_name']}: {$avgDrift}{$avgDur}{$avgDev}";
        })->join("\n");

        return <<<PROMPT
Analyse this COMPLETED competition and provide a retrospective.

COMPETITION
Name: {$data['name']}
Date: {$data['date']}
Locations: {$data['locations']}{$targetLine}

EVENT TYPES ({$eventCount} total)
{$events}

ENROLMENTS (final)
Total enrolled: {$e['total']} | Active: {$e['active']} | Withdrawn: {$e['withdrawn']} | Late: {$e['late']}
Payments outstanding: {$e['payment_outstanding_count']} enrolments | \${$e['payment_outstanding_amount']}
Payments received: {$e['payment_received_count']} enrolments | \${$e['payment_received_amount']}

COMPETITOR DEMOGRAPHICS
Unique competitors: {$c['total']} ({$c['male']} male, {$c['female']} female)
Age bands: {$ageBands}
Rank bands: {$rankBands}
Dojos represented: {$c['dojos']} | Top: {$topDojos}

DIVISION OUTCOMES
Scored (ran): {$o['divisions_scored']} | Unscored: {$o['divisions_unscored']} | Cancelled (unscheduled): {$o['divisions_cancelled']}
Size breakdown: Empty: {$d['empty']} | Solo (1 competitor): {$d['solo']} | Small (2–3): {$d['small']} | Healthy (4+): {$d['healthy']}

SCHEDULE TIMING
{$t['divisions_with_planned']} divisions with planned times | {$t['divisions_started']} started | {$t['divisions_completed_actuals']} completed with actuals
Overall avg start drift: {$avgDriftStr} | Behind >5min: {$t['behind_5min_count']} | Ahead >5min: {$t['ahead_5min_count']}
{$timingEventLines}
PROMPT;
    }
}
