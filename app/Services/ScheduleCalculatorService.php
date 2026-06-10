<?php

namespace App\Services;

use App\Models\Competition;
use App\Models\Division;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ScheduleCalculatorService
{
    /**
     * Recalculate planned_start_at for all divisions on a specific location.
     * Called when a division on that location is reordered or event timing config changes.
     */
    public function recalculateForLocation(Competition $competition, string $locationLabel): void
    {
        $breaks    = $competition->breaks;
        $divisions = $this->divisionsForLocation($competition, $locationLabel);
        $this->writeStartTimes($competition, $divisions, $breaks);
    }

    /**
     * Recalculate planned_start_at for all divisions on all locations.
     * Called when a break is added, edited, or deleted.
     */
    public function recalculateAll(Competition $competition): void
    {
        $breaks    = $competition->breaks;
        $locations = $this->allLocations($competition);

        foreach ($locations as $locationLabel) {
            $divisions = $this->divisionsForLocation($competition, $locationLabel);
            $this->writeStartTimes($competition, $divisions, $breaks);
        }
    }

    /**
     * Divisions on a given location, ordered by running_order, with their event eager-loaded.
     */
    private function divisionsForLocation(Competition $competition, string $locationLabel): Collection
    {
        return Division::whereHas('competitionEvent', fn ($q) => $q->where('competition_id', $competition->id))
            ->where('location_label', $locationLabel)
            ->whereNotIn('status', ['combined'])
            ->with('competitionEvent')
            ->withCount('activeEnrolmentEvents')
            ->orderBy('running_order')
            ->get();
    }

    private function allLocations(Competition $competition): array
    {
        return Division::whereHas('competitionEvent', fn ($q) =>
                $q->where('competition_id', $competition->id)
            )
            ->whereNotNull('location_label')
            ->distinct()
            ->pluck('location_label')
            ->toArray();
    }

    private function writeStartTimes(Competition $competition, Collection $divisions, Collection $breaks): void
    {
        if ($divisions->isEmpty()) {
            return;
        }

        $competitionStart = $competition->start_time
            ? Carbon::parse($competition->competition_date->format('Y-m-d') . ' ' . $competition->start_time)
            : Carbon::parse($competition->competition_date->format('Y-m-d') . ' 09:00');

        $breakWindows = $breaks->map(fn ($b) => [
            'start' => Carbon::parse($competition->competition_date->format('Y-m-d') . ' ' . $b->start_time),
            'end'   => Carbon::parse($competition->competition_date->format('Y-m-d') . ' ' . $b->start_time)
                            ->addMinutes($b->duration_minutes),
        ])->sortBy(fn ($w) => $w['start']->timestamp)->values();

        $clock   = $competitionStart->copy();
        $updates = [];

        foreach ($divisions as $div) {
            $slotMinutes = $this->divisionSlotMinutes($div);
            $clock = $this->advancePastBreaks($clock, $breakWindows, $slotMinutes);
            $updates[$div->id] = $clock->copy();
            $clock->addMinutes($slotMinutes);
        }

        DB::transaction(function () use ($updates) {
            foreach ($updates as $divisionId => $startAt) {
                Division::where('id', $divisionId)->update(['planned_start_at' => $startAt]);
            }
        });
    }

    /**
     * Advance $clock past any break window it currently falls inside, or would overflow into.
     * Repeats in case advancing into one break puts us inside or overflowing another.
     */
    private function advancePastBreaks(Carbon $clock, Collection $breakWindows, int $slotMinutes = 0): Carbon
    {
        $moved = true;
        while ($moved) {
            $moved = false;
            $end = $clock->copy()->addMinutes($slotMinutes);
            foreach ($breakWindows as $window) {
                // Clock start is inside a break
                if ($clock->gte($window['start']) && $clock->lt($window['end'])) {
                    $clock = $window['end']->copy();
                    $moved = true;
                    break;
                }
                // Event would run into a break (starts before, ends after break start)
                if ($slotMinutes > 0 && $clock->lt($window['start']) && $end->gt($window['start'])) {
                    $clock = $window['end']->copy();
                    $moved = true;
                    break;
                }
            }
        }
        return $clock;
    }

    /**
     * Slot duration in minutes for one division.
     * Count = actual active enrolments if any, else event's default_max_competitors.
     * Timing config (seconds_per_competitor, round_duration_seconds) comes from the event type.
     */
    public function divisionSlotMinutes(Division $div): int
    {
        $event = $div->competitionEvent;
        $count = ($div->active_enrolment_events_count >= 2)
            ? $div->active_enrolment_events_count
            : ($event->default_max_competitors ?? 0);

        if ($count === 0) {
            return (int) ceil(($event->transition_padding_seconds ?? 0) / 60);
        }

        // Partner events compete as pairs
        if ($event->requires_partner) {
            $count = (int) ceil($count / 2);
        }

        $paddingSeconds = (int) ($event->transition_padding_seconds ?? 0);

        // Bracket formats: total time = number of sequential matches × match duration
        if ($event->isTournament() && $event->round_duration_seconds !== null) {
            $matches = $this->expectedMatches($count, $event->effectiveTournamentFormat());
            return (int) ceil($matches * ($event->round_duration_seconds + $paddingSeconds) / 60);
        }

        // Individual/sequential formats (kata, etc.)
        if ($event->seconds_per_competitor !== null) {
            $secondsPerCompetitor = $event->seconds_per_competitor;
        } elseif ($event->round_duration_seconds !== null) {
            $secondsPerCompetitor = $event->round_duration_seconds + 180;
        } else {
            $secondsPerCompetitor = 180;
        }

        return (int) ceil($count * ($secondsPerCompetitor + $paddingSeconds) / 60);
    }

    /**
     * Expected number of sequential matches for a bracket division on a single mat.
     * Ignores byes, tiebreaks, and forfeits/DQs.
     */
    private function expectedMatches(int $count, string $format): int
    {
        if ($count <= 1) {
            return 0;
        }

        return match($format) {
            // Every competitor except winner loses once: N-1 matches total
            'single_elimination' => $count - 1,
            // SE matches + bronze final
            'se_3rd_place'       => $count,
            // Every competitor except winner loses twice: 2*(N-1) matches
            'double_elimination' => 2 * ($count - 1),
            // Repechage allows one second chance — similar total to double elimination
            'repechage'          => 2 * ($count - 1),
            // Each pair meets once: N*(N-1)/2 matches
            'round_robin'        => (int) ($count * ($count - 1) / 2),
            default              => $count - 1,
        };
    }
}
