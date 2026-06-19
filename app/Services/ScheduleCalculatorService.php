<?php

namespace App\Services;

use App\Models\Competition;
use App\Models\CompetitionDay;
use App\Models\Division;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ScheduleCalculatorService
{
    public function recalculateForLocation(Competition $competition, string $locationLabel): void
    {
        $competition->competitionDays()->orderBy('date')->get()->each(function (CompetitionDay $day) use ($competition, $locationLabel) {
            $divisions = $this->divisionsForLocation($competition, $locationLabel, $day);
            $this->writeStartTimes($day, $divisions, $day->breaks);
        });
    }

    public function recalculateAll(Competition $competition): void
    {
        $competition->competitionDays()->orderBy('date')->get()->each(function (CompetitionDay $day) use ($competition) {
            foreach ($this->allLocationsForDay($competition, $day) as $locationLabel) {
                $divisions = $this->divisionsForLocation($competition, $locationLabel, $day);
                $this->writeStartTimes($day, $divisions, $day->breaks);
            }
        });
    }

    private function divisionsForLocation(Competition $competition, string $locationLabel, CompetitionDay $day): Collection
    {
        return Division::whereHas('competitionEvent', fn ($q) => $q->where('competition_id', $competition->id))
            ->where('competition_day_id', $day->id)
            ->where('location_label', $locationLabel)
            ->whereNotIn('status', ['combined'])
            ->with('competitionEvent')
            ->withCount('activeEnrolmentEvents')
            ->orderBy('running_order')
            ->get();
    }

    private function allLocationsForDay(Competition $competition, CompetitionDay $day): array
    {
        return Division::whereHas('competitionEvent', fn ($q) => $q->where('competition_id', $competition->id))
            ->where('competition_day_id', $day->id)
            ->whereNotNull('location_label')
            ->distinct()
            ->pluck('location_label')
            ->toArray();
    }

    private function writeStartTimes(CompetitionDay $day, Collection $divisions, Collection $breaks): void
    {
        if ($divisions->isEmpty()) {
            return;
        }

        $dateStr = $day->date->format('Y-m-d');

        $competitionStart = $day->start_time
            ? Carbon::parse($dateStr . ' ' . $day->start_time)
            : Carbon::parse($dateStr . ' 09:00');

        $breakWindows = $breaks->map(fn ($b) => [
            'start' => Carbon::parse($dateStr . ' ' . $b->start_time),
            'end'   => Carbon::parse($dateStr . ' ' . $b->start_time)->addMinutes($b->duration_minutes),
        ])->sortBy(fn ($w) => $w['start']->timestamp)->values();

        $clock   = $competitionStart->copy();
        $updates = [];

        foreach ($divisions as $div) {
            $slotMinutes       = $this->divisionSlotMinutes($div);
            $clock             = $this->advancePastBreaks($clock, $breakWindows, $slotMinutes);
            $updates[$div->id] = $clock->copy();
            $clock->addMinutes($slotMinutes);
        }

        DB::transaction(function () use ($updates) {
            foreach ($updates as $divisionId => $startAt) {
                Division::where('id', $divisionId)->update(['planned_start_at' => $startAt]);
            }
        });
    }

    private function advancePastBreaks(Carbon $clock, Collection $breakWindows, int $slotMinutes = 0): Carbon
    {
        $moved = true;
        while ($moved) {
            $moved = false;
            $end   = $clock->copy()->addMinutes($slotMinutes);
            foreach ($breakWindows as $window) {
                if ($clock->gte($window['start']) && $clock->lt($window['end'])) {
                    $clock = $window['end']->copy();
                    $moved = true;
                    break;
                }
                if ($slotMinutes > 0 && $clock->lt($window['start']) && $end->gt($window['start'])) {
                    $clock = $window['end']->copy();
                    $moved = true;
                    break;
                }
            }
        }
        return $clock;
    }

    public function divisionSlotMinutes(Division $div): int
    {
        $event = $div->competitionEvent;
        $count = ($div->active_enrolment_events_count >= 2)
            ? $div->active_enrolment_events_count
            : ($event->default_max_competitors ?? 0);

        if ($count === 0) {
            return (int) ceil(($event->transition_padding_seconds ?? 0) / 60);
        }

        if ($event->requires_partner) {
            $count = (int) ceil($count / 2);
        }

        $paddingSeconds = (int) ($event->transition_padding_seconds ?? 0);

        if ($event->isTournament() && $event->round_duration_seconds !== null) {
            $matches = $this->expectedMatches($count, $event->effectiveTournamentFormat());
            return (int) ceil($matches * ($event->round_duration_seconds + $paddingSeconds) / 60);
        }

        if ($event->seconds_per_competitor !== null) {
            $secondsPerCompetitor = $event->seconds_per_competitor;
        } elseif ($event->round_duration_seconds !== null) {
            $secondsPerCompetitor = $event->round_duration_seconds + 180;
        } else {
            $secondsPerCompetitor = 180;
        }

        return (int) ceil($count * ($secondsPerCompetitor + $paddingSeconds) / 60);
    }

    private function expectedMatches(int $count, string $format): int
    {
        if ($count <= 1) {
            return 0;
        }

        return match($format) {
            'single_elimination' => $count - 1,
            'se_3rd_place'       => $count,
            'double_elimination' => 2 * ($count - 1),
            'repechage'          => 2 * ($count - 1),
            'round_robin'        => (int) ($count * ($count - 1) / 2),
            default              => $count - 1,
        };
    }
}
