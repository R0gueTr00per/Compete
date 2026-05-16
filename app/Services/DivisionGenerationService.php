<?php

namespace App\Services;

use App\Models\Competition;
use App\Models\CompetitionEvent;
use App\Models\Division;

class DivisionGenerationService
{
    /**
     * Regenerate all divisions for every scheduled event in a competition.
     * Divisions that already have enrolments are preserved.
     * Returns the total number of newly created divisions.
     */
    public function generateForCompetition(Competition $competition): int
    {
        $competition->loadMissing(['ageBands', 'rankBands', 'weightClasses']);
        $total = 0;

        foreach ($competition->competitionEvents()->where('status', 'scheduled')->get() as $event) {
            $total += $this->generateForEvent($event, $competition);
        }

        return $total;
    }

    /**
     * Regenerate divisions for a single competition event.
     * Empty divisions are deleted first; divisions with enrolments are kept.
     */
    public function generateForEvent(CompetitionEvent $event, Competition $competition): int
    {
        $competition->loadMissing(['ageBands', 'rankBands', 'weightClasses']);

        // Remove divisions that have no active enrolments
        $event->divisions()
            ->whereDoesntHave('activeEnrolmentEvents')
            ->delete();

        $ageBands     = $competition->ageBands->sortBy('sort_order');
        $rankBands    = $competition->rankBands->sortBy('sort_order');
        $weightClasses = $competition->weightClasses->sortBy('sort_order');

        $rows = $this->buildRows($event->effectiveDivisionFilter(), $ageBands, $rankBands, $weightClasses);

        // Find the highest existing running_order (from preserved divisions)
        $order   = $event->divisions()->max('running_order') ?? 0;
        $created = 0;

        foreach ($rows as $row) {
            // Skip if a matching division already exists (preserved from enrolments)
            if ($this->divisionExists($event->id, $row)) {
                continue;
            }

            $order++;
            Division::create(array_merge($row, [
                'competition_event_id' => $event->id,
                'code'                 => $event->event_code . str_pad($order, 2, '0', STR_PAD_LEFT),
                'running_order'        => $order,
                'status'               => 'pending',
            ]));
            $created++;
        }

        return $created;
    }

    private function divisionExists(int $eventId, array $row): bool
    {
        return Division::where('competition_event_id', $eventId)
            ->where('age_band_id', $row['age_band_id'])
            ->where('rank_band_id', $row['rank_band_id'])
            ->where('weight_class_id', $row['weight_class_id'])
            ->where('sex', $row['sex'])
            ->exists();
    }

    private function buildRows(string $filter, $ageBands, $rankBands, $weightClasses): array
    {
        $rows = [];

        switch ($filter) {
            case 'age_rank_sex':
                foreach ($ageBands as $ab) {
                    foreach ($rankBands as $rb) {
                        foreach (['M', 'F'] as $sex) {
                            $rows[] = $this->row($ab->id, $rb->id, null, $sex);
                        }
                    }
                }
                break;

            case 'age_rank':
                foreach ($ageBands as $ab) {
                    foreach ($rankBands as $rb) {
                        $rows[] = $this->row($ab->id, $rb->id, null, 'mixed');
                    }
                }
                break;

            case 'age_sex':
                foreach ($ageBands as $ab) {
                    foreach (['M', 'F'] as $sex) {
                        $rows[] = $this->row($ab->id, null, null, $sex);
                    }
                }
                break;

            case 'age_only':
                foreach ($ageBands as $ab) {
                    $rows[] = $this->row($ab->id, null, null, 'mixed');
                }
                break;

            case 'weight_sex':
                foreach ($weightClasses as $wc) {
                    foreach (['M', 'F'] as $sex) {
                        $rows[] = $this->row(null, null, $wc->id, $sex);
                    }
                }
                break;

            case 'age_weight':
                foreach ($ageBands as $ab) {
                    foreach ($weightClasses as $wc) {
                        $rows[] = $this->row($ab->id, null, $wc->id, 'mixed');
                    }
                }
                break;

            case 'age_weight_sex':
                foreach ($ageBands as $ab) {
                    foreach ($weightClasses as $wc) {
                        foreach (['M', 'F'] as $sex) {
                            $rows[] = $this->row($ab->id, null, $wc->id, $sex);
                        }
                    }
                }
                break;
        }

        return $rows;
    }

    private function row(?int $ageBandId, ?int $rankBandId, ?int $weightClassId, ?string $sex): array
    {
        return [
            'age_band_id'     => $ageBandId,
            'rank_band_id'    => $rankBandId,
            'weight_class_id' => $weightClassId,
            'sex'             => $sex,
        ];
    }
}
