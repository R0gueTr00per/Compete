<?php
namespace App\Services;

use App\Models\Competition;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfReportService
{
    public function generateCompetitionResults(Competition $competition, array $filters = []): string
    {
        $competition->load([
            'competitionEvents.divisions.enrolmentEvents.enrolment.competitor.competitorProfile',
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
                            fn ($ee) => $ee->result?->placement && $ee->result->placement <= 3
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
                            $profile = $ee->enrolment->competitor?->competitorProfile;
                            $name = strtolower($profile
                                ? "{$profile->first_name} {$profile->surname}"
                                : ($ee->enrolment->competitor?->name ?? ''));
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
}
