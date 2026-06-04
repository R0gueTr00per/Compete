<?php
namespace App\Services;

use App\Models\Competition;
use App\Models\Result;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfReportService
{
    public function generateCompetitionResults(Competition $competition, array $filters = []): string
    {
        $competition->load([
            'competitionEvents.divisions.enrolmentEvents.enrolment.competitor',
            'competitionEvents.divisions.enrolmentEvents.result.judgeScores',
        ]);

        $onlyPlacings  = (bool) ($filters['only_placings'] ?? false);
        $search        = strtolower(trim($filters['search'] ?? ''));
        $selectedDojo  = $filters['selected_dojo'] ?? null;
        $selectedEvent = $filters['selected_event'] ?? null;

        $events = $competition->competitionEvents
            ->sortBy('running_order')
            ->filter(fn ($e) => ! in_array($e->status, ['combined']));

        if ($selectedEvent) {
            $events = $events->filter(fn ($e) => $e->id === (int) $selectedEvent);
        }

        $events = $events->map(function ($compEvent) use ($onlyPlacings, $search, $selectedDojo) {
            $divisions = $compEvent->divisions
                ->filter(fn ($d) => $d->status === 'complete')
                ->map(function ($division) use ($onlyPlacings, $search, $selectedDojo) {
                    $entries = $division->enrolmentEvents
                        ->filter(fn ($ee) => ! $ee->removed)
                        ->sortBy(fn ($ee) => $ee->result?->placement ?? 999);

                    if ($onlyPlacings) {
                        $entries = $entries->filter(
                            fn ($ee) => $ee->result?->placement && $ee->result->placement <= 3 && ! $ee->result->disqualified
                        );
                    }

                    if ($selectedDojo !== null && $selectedDojo !== '') {
                        $entries = $entries->filter(function ($ee) use ($selectedDojo) {
                            $dojo = $ee->enrolment->dojo_type === 'guest'
                                ? ($ee->enrolment->guest_style ?? '')
                                : ($ee->enrolment->dojo_name ?? '');
                            return $dojo === $selectedDojo;
                        });
                    }

                    if ($search !== '') {
                        $entries = $entries->filter(function ($ee) use ($search) {
                            $profile = $ee->enrolment->competitor;
                            $name    = strtolower($profile?->full_name ?? '');
                            $dojo = strtolower($ee->enrolment->dojo_type === 'guest'
                                ? ($ee->enrolment->guest_style ?? '')
                                : ($ee->enrolment->dojo_name ?? ''));
                            return str_contains($name, $search) || str_contains($dojo, $search);
                        });
                    }

                    $division->setRelation('enrolmentEvents', $entries);
                    return $division;
                })
                ->filter(fn ($d) => $d->enrolmentEvents->isNotEmpty());

            $compEvent->setRelation('divisions', $divisions);
            return $compEvent;
        })->filter(fn ($e) => $e->divisions->isNotEmpty());

        $pdf = Pdf::loadView('pdf.competition-results', compact('competition', 'events', 'filters'))
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'margin_top'    => 28,
                'margin_right'  => 28,
                'margin_bottom' => 28,
                'margin_left'   => 28,
            ]);

        return $pdf->output();
    }

    public function generateMedalTallyByCompetitor(Competition $competition): string
    {
        $tally = $this->buildMedalTally(
            $competition,
            groupBy: fn ($r) => $r->enrolmentEvent->enrolment->competitor_profile_id,
            label: function ($group) {
                $first      = $group->first();
                $enrolment  = $first->enrolmentEvent->enrolment;
                $competitor = $enrolment->competitor;
                $dojo       = $enrolment->dojo_type === 'guest'
                    ? ($enrolment->guest_style ?? 'Guest')
                    : ($enrolment->dojo_name ?? '—');
                return ['name' => $competitor?->full_name ?? '—', 'dojo' => $dojo];
            }
        );

        $pdf = Pdf::loadView('pdf.medal-tally-by-competitor', compact('competition', 'tally'))
            ->setPaper('a4', 'portrait')
            ->setOptions(['margin_top' => 28, 'margin_right' => 28, 'margin_bottom' => 28, 'margin_left' => 28]);

        return $pdf->output();
    }

    public function generateMedalTallyByDojo(Competition $competition): string
    {
        $tally = $this->buildMedalTally(
            $competition,
            groupBy: fn ($r) => $r->enrolmentEvent->enrolment->dojo_type === 'guest'
                ? ($r->enrolmentEvent->enrolment->guest_style ?? 'Guest')
                : ($r->enrolmentEvent->enrolment->dojo_name ?? '—'),
            label: fn ($group, $key) => ['name' => $key]
        );

        $pdf = Pdf::loadView('pdf.medal-tally-by-dojo', compact('competition', 'tally'))
            ->setPaper('a4', 'portrait')
            ->setOptions(['margin_top' => 28, 'margin_right' => 28, 'margin_bottom' => 28, 'margin_left' => 28]);

        return $pdf->output();
    }

    private function buildMedalTally(Competition $competition, callable $groupBy, callable $label): \Illuminate\Support\Collection
    {
        $results = Result::whereHas('enrolmentEvent.enrolment', fn ($q) => $q->where('competition_id', $competition->id))
            ->whereNotNull('placement')
            ->whereBetween('placement', [1, 3])
            ->where('disqualified', false)
            ->with('enrolmentEvent.enrolment.competitor')
            ->get();

        $tally = $results->groupBy($groupBy)
            ->map(function ($group, $key) use ($label) {
                $meta = $label($group, $key);
                return array_merge($meta, [
                    'gold'   => $group->where('placement', 1)->count(),
                    'silver' => $group->where('placement', 2)->count(),
                    'bronze' => $group->where('placement', 3)->count(),
                ]);
            })
            ->sortByDesc(fn ($t) => [$t['gold'], $t['silver'], $t['bronze']])
            ->values();

        $rank = 1;
        $prev = null;

        return $tally->map(function ($entry) use (&$rank, &$prev) {
            $key = [$entry['gold'], $entry['silver'], $entry['bronze']];
            if ($prev !== null && $key !== $prev) {
                $rank++;
            }
            $entry['rank'] = $rank;
            $prev          = $key;
            return $entry;
        });
    }
}
