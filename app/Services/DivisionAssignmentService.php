<?php

namespace App\Services;

use App\Models\AgeBand;
use App\Models\Competition;
use App\Models\CompetitionEvent;
use App\Models\Division;
use App\Models\EnrolmentEvent;
use App\Models\RankBand;
use App\Models\WeightClass;
use Illuminate\Support\Facades\DB;

class DivisionAssignmentService
{
    /**
     * Generate all divisions for every event in a competition.
     * Existing divisions are left untouched; only missing ones are created.
     * Returns the number of new divisions created.
     */
    public function buildDivisionsForCompetition(Competition $competition): int
    {
        $ageBands    = $competition->ageBands()->orderBy('sort_order')->get();
        $rankBands   = $competition->rankBands()->orderBy('sort_order')->get();
        $weightClasses = $competition->weightClasses()->orderBy('sort_order')->get();
        $created = 0;

        foreach ($competition->competitionEvents as $event) {
            $created += $this->buildDivisionsForEvent($event, $ageBands, $rankBands, $weightClasses);
        }

        return $created;
    }

    /**
     * Auto-assign sequential division codes for any division in this competition that has no code.
     * Pattern: 2-char event prefix + 2-digit sequence (e.g. KA01, KA02).
     * Returns number of codes assigned.
     */
    public function assignCodesForCompetition(Competition $competition): int
    {
        $assigned = 0;

        foreach ($competition->competitionEvents()->get() as $event) {
            $prefix  = $this->eventPrefix($event->name);
            $counter = Division::where('competition_event_id', $event->id)->whereNotNull('code')->count();

            Division::where('competition_event_id', $event->id)
                ->whereNull('code')
                ->orderBy('id')
                ->get()
                ->each(function (Division $div) use ($prefix, &$counter, &$assigned) {
                    $counter++;
                    $div->updateQuietly(['code' => $prefix . str_pad($counter, 2, '0', STR_PAD_LEFT)]);
                    $assigned++;
                });
        }

        return $assigned;
    }

    private function eventPrefix(string $name): string
    {
        $words = preg_split('/\s+/', trim($name));
        if (count($words) === 1) {
            return strtoupper(substr($name, 0, 2));
        }
        return strtoupper(
            implode('', array_map(fn ($w) => substr($w, 0, 1), array_slice($words, 0, 2)))
        );
    }

    private function buildDivisionsForEvent(
        CompetitionEvent $event,
        $ageBands,
        $rankBands,
        $weightClasses
    ): int {
        $filter = $event->effectiveDivisionFilter();
        $sexes  = ['M', 'F'];

        $combinations = match ($filter) {
            'age_rank_sex'   => $this->crossProduct($ageBands, $rankBands, $sexes),
            'age_sex'        => $this->crossProductAgeAndSex($ageBands, $sexes),
            'weight_sex'     => $this->crossProductWeightAndSex($weightClasses, $sexes),
            'age_rank'       => $this->crossProductAgeAndRank($ageBands, $rankBands),
            'age_only'       => $this->crossProductAgeOnly($ageBands),
            'age_weight'     => $this->crossProductAgeAndWeight($ageBands, $weightClasses),
            'age_weight_sex' => $this->crossProductAgeWeightAndSex($ageBands, $weightClasses, $sexes),
            default          => [],
        };

        $nextOrder = (Division::where('competition_event_id', $event->id)->max('running_order') ?? 0) + 1;
        $created   = 0;

        foreach ($combinations as $combo) {
            $exists = Division::where('competition_event_id', $event->id)
                ->where('age_band_id', $combo['age_band_id'])
                ->where('rank_band_id', $combo['rank_band_id'])
                ->where('weight_class_id', $combo['weight_class_id'])
                ->where('sex', $combo['sex'])
                ->exists();

            if (! $exists) {
                Division::create([
                    'competition_event_id' => $event->id,
                    'age_band_id'          => $combo['age_band_id'],
                    'rank_band_id'         => $combo['rank_band_id'],
                    'weight_class_id'      => $combo['weight_class_id'],
                    'sex'                  => $combo['sex'],
                    'label'                => $combo['label'],
                    'running_order'        => $nextOrder++,
                    'status'               => 'pending',
                ]);
                $created++;
            }
        }

        return $created;
    }

    private function crossProduct($ageBands, $rankBands, array $sexes): array
    {
        $result = [];
        foreach ($ageBands as $age) {
            foreach ($rankBands as $rank) {
                foreach ($sexes as $sex) {
                    $result[] = [
                        'age_band_id'    => $age->id,
                        'rank_band_id'   => $rank->id,
                        'weight_class_id' => null,
                        'sex'            => $sex,
                        'label'          => "{$age->label} / {$rank->label} / " . ($sex === 'M' ? 'Male' : 'Female'),
                    ];
                }
            }
        }
        return $result;
    }

    private function crossProductAgeAndSex($ageBands, array $sexes): array
    {
        $result = [];
        foreach ($ageBands as $age) {
            foreach ($sexes as $sex) {
                $result[] = [
                    'age_band_id'    => $age->id,
                    'rank_band_id'   => null,
                    'weight_class_id' => null,
                    'sex'            => $sex,
                    'label'          => "{$age->label} / " . ($sex === 'M' ? 'Male' : 'Female'),
                ];
            }
        }
        return $result;
    }

    private function crossProductWeightAndSex($weightClasses, array $sexes): array
    {
        $result = [];
        foreach ($weightClasses as $wc) {
            foreach ($sexes as $sex) {
                $result[] = [
                    'age_band_id'    => null,
                    'rank_band_id'   => null,
                    'weight_class_id' => $wc->id,
                    'sex'            => $sex,
                    'label'          => "{$wc->label} / " . ($sex === 'M' ? 'Male' : 'Female'),
                ];
            }
        }
        return $result;
    }

    private function crossProductAgeAndRank($ageBands, $rankBands): array
    {
        $result = [];
        foreach ($ageBands as $age) {
            foreach ($rankBands as $rank) {
                $result[] = [
                    'age_band_id'     => $age->id,
                    'rank_band_id'    => $rank->id,
                    'weight_class_id' => null,
                    'sex'             => 'mixed',
                    'label'           => "{$age->label} / {$rank->label} / Mixed",
                ];
            }
        }
        return $result;
    }

    private function crossProductAgeOnly($ageBands): array
    {
        $result = [];
        foreach ($ageBands as $age) {
            $result[] = [
                'age_band_id'     => $age->id,
                'rank_band_id'    => null,
                'weight_class_id' => null,
                'sex'             => 'mixed',
                'label'           => "{$age->label} / Mixed",
            ];
        }
        return $result;
    }

    private function crossProductAgeAndWeight($ageBands, $weightClasses): array
    {
        $result = [];
        foreach ($ageBands as $age) {
            foreach ($weightClasses as $wc) {
                $result[] = [
                    'age_band_id'     => $age->id,
                    'rank_band_id'    => null,
                    'weight_class_id' => $wc->id,
                    'sex'             => 'mixed',
                    'label'           => "{$age->label} / {$wc->label} / Mixed",
                ];
            }
        }
        return $result;
    }

    private function crossProductAgeWeightAndSex($ageBands, $weightClasses, array $sexes): array
    {
        $result = [];
        foreach ($ageBands as $age) {
            foreach ($weightClasses as $wc) {
                foreach ($sexes as $sex) {
                    $result[] = [
                        'age_band_id'     => $age->id,
                        'rank_band_id'    => null,
                        'weight_class_id' => $wc->id,
                        'sex'             => $sex,
                        'label'           => "{$age->label} / {$wc->label} / " . ($sex === 'M' ? 'Male' : 'Female'),
                    ];
                }
            }
        }
        return $result;
    }

    /**
     * Copy the full structure (bands, events, divisions) from a source competition to a target.
     * Division band/event IDs are remapped to the new competition's IDs.
     * Called automatically when a new competition is created.
     */
    public function copyDivisionsFromCompetition(Competition $source, Competition $target): void
    {
        $source->load(['ageBands', 'rankBands', 'weightClasses', 'competitionEvents.divisions']);

        DB::transaction(function () use ($source, $target) {
            // Copy locations to target if it has none
            if ($source->competitionLocations()->exists() && $target->competitionLocations()->doesntExist()) {
                foreach ($source->competitionLocations()->get() as $loc) {
                    $target->competitionLocations()->create(['name' => $loc->name, 'sort_order' => $loc->sort_order]);
                }
            }

            // Copy age bands, track old → new ID mapping
            $ageBandMap = [];
            foreach ($source->ageBands->sortBy('sort_order') as $band) {
                $new = $target->ageBands()->create($band->only(['label', 'min_age', 'max_age', 'sort_order']));
                $ageBandMap[$band->id] = $new->id;
            }

            // Copy rank bands, track old → new ID mapping
            $rankBandMap = [];
            foreach ($source->rankBands->sortBy('sort_order') as $band) {
                $new = $target->rankBands()->create($band->only(['label', 'description', 'sort_order', 'rank_min', 'rank_max']));
                $rankBandMap[$band->id] = $new->id;
            }

            // Copy weight classes, track old → new ID mapping
            $weightClassMap = [];
            foreach ($source->weightClasses->sortBy('sort_order') as $wc) {
                $new = $target->weightClasses()->create($wc->only(['label', 'max_kg', 'sort_order']));
                $weightClassMap[$wc->id] = $new->id;
            }

            // Copy competition events, track old → new event ID mapping
            $eventMap = [];
            foreach ($source->competitionEvents->sortBy('running_order') as $event) {
                $new = $target->competitionEvents()->create([
                    'name'                 => $event->name,
                    'running_order'        => $event->running_order,
                    'location_label'       => $event->location_label,
                    'target_score'         => $event->target_score,
                    'scoring_method'       => $event->scoring_method,
                    'tournament_format'    => $event->tournament_format,
                    'division_filter'      => $event->division_filter,
                    'judge_count'          => $event->judge_count,
                    'requires_partner'     => $event->requires_partner,
                    'status'               => 'scheduled',
                ]);
                $eventMap[$event->id] = $new->id;
            }

            // Collect all division rows and bulk-insert to avoid per-row model overhead
            $now = now();
            $rows = [];
            foreach ($source->competitionEvents as $sourceEvent) {
                $newEventId = $eventMap[$sourceEvent->id] ?? null;
                if (! $newEventId) {
                    continue;
                }

                foreach ($sourceEvent->divisions as $division) {
                    $rows[] = [
                        'competition_event_id' => $newEventId,
                        'code'                 => $division->code,
                        'age_band_id'          => $division->age_band_id ? ($ageBandMap[$division->age_band_id] ?? null) : null,
                        'rank_band_id'         => $division->rank_band_id ? ($rankBandMap[$division->rank_band_id] ?? null) : null,
                        'weight_class_id'      => $division->weight_class_id ? ($weightClassMap[$division->weight_class_id] ?? null) : null,
                        'sex'                  => $division->sex,
                        'label'                => $division->label,
                        'running_order'        => $division->running_order,
                        'location_label'       => $division->location_label,
                        'target_score'         => $division->target_score,
                        'status'               => 'pending',
                        'created_at'           => $now,
                        'updated_at'           => $now,
                    ];
                }
            }

            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('divisions')->insert($chunk);
            }
        });
    }

    /**
     * Find the correct division for an enrolment event.
     * Uses enrolment-level rank/weight and profile-level DOB/gender.
     */
    public function assignDivision(EnrolmentEvent $ee): ?Division
    {
        $compEvent = $ee->competitionEvent;
        $filter    = $compEvent->effectiveDivisionFilter();
        $profile   = $ee->enrolment->competitor->competitorProfile;

        if (! $profile) {
            return null;
        }

        $ctx = $this->buildContext($profile, $ee->enrolment, $ee->weight_confirmed_kg);
        $sex = $ctx->gender;

        return match ($filter) {
            'age_rank_sex'   => $this->assignAgeRankSex($compEvent->id, $sex, $ctx),
            'age_sex'        => $this->assignAgeSex($compEvent->id, $sex, $ctx),
            'weight_sex'     => $this->assignWeightSex($compEvent->id, $sex, $ctx),
            'age_rank'       => $this->assignAgeRank($compEvent->id, $ctx),
            'age_only'       => $this->assignAgeOnly($compEvent->id, $ctx),
            'age_weight'     => $this->assignAgeWeight($compEvent->id, $ctx),
            'age_weight_sex' => $this->assignAgeWeightSex($compEvent->id, $sex, $ctx),
            default          => null,
        };
    }

    /**
     * Build a context object combining profile (DOB/gender) with enrolment-level rank/weight.
     * @param  \App\Models\CompetitorProfile  $profile
     * @param  \App\Models\Enrolment|null     $enrolment
     * @param  float|null                     $confirmedWeight  Weight confirmed at check-in
     */
    public function buildContext($profile, $enrolment = null, ?float $confirmedWeight = null): object
    {
        return (object) [
            'gender'            => $profile->gender,
            'age'               => $profile->age,
            'rank_type'         => $enrolment?->rank_type,
            'rank_kyu'          => $enrolment?->rank_kyu,
            'rank_dan'          => $enrolment?->rank_dan,
            'experience_years'  => $enrolment?->experience_years,
            'experience_months' => $enrolment?->experience_months,
            'weight_kg'         => $confirmedWeight ?? $enrolment?->weight_kg,
        ];
    }

    private function assignAgeRankSex(int $eventId, string $sex, $ctx): ?Division
    {
        $age  = $ctx->age;
        $rank = $this->normalizeRank($ctx);

        // Try sex-specific divisions first, then fall back to Mixed
        foreach ([$sex, 'mixed'] as $trySex) {
            $base = Division::where('competition_event_id', $eventId)
                ->where('sex', $trySex)
                ->whereIn('status', ['pending', 'assigned']);

            // 1. Exact: matching age band + rank band
            if ($rank !== null) {
                $match = (clone $base)
                    ->whereHas('ageBand', fn ($q) => $q
                        ->where(fn ($q2) => $q2->whereNull('min_age')->orWhere('min_age', '<=', $age))
                        ->where(fn ($q2) => $q2->whereNull('max_age')->orWhere('max_age', '>=', $age))
                    )
                    ->whereHas('rankBand', fn ($q) => $q
                        ->where(fn ($q2) => $q2->whereNull('rank_min')->orWhere('rank_min', '<=', $rank))
                        ->where(fn ($q2) => $q2->whereNull('rank_max')->orWhere('rank_max', '>=', $rank))
                    )
                    ->first();
                if ($match) {
                    return $match;
                }
            }

            // 2. Age-only match (rank band not set up or rank is null)
            $ageMatch = (clone $base)
                ->whereNull('rank_band_id')
                ->whereHas('ageBand', fn ($q) => $q
                    ->where(fn ($q2) => $q2->whereNull('min_age')->orWhere('min_age', '<=', $age))
                    ->where(fn ($q2) => $q2->whereNull('max_age')->orWhere('max_age', '>=', $age))
                )
                ->first();
            if ($ageMatch) {
                return $ageMatch;
            }

            // 3. Fully open division (no age, no rank restriction)
            $open = (clone $base)->whereNull('age_band_id')->whereNull('rank_band_id')->first();
            if ($open) {
                return $open;
            }
        }

        return null;
    }

    private function assignAgeSex(int $eventId, string $sex, $ctx): ?Division
    {
        $age = $ctx->age;

        // Try sex-specific divisions first, then fall back to Mixed
        foreach ([$sex, 'mixed'] as $trySex) {
            $base = Division::where('competition_event_id', $eventId)
                ->where('sex', $trySex)
                ->whereIn('status', ['pending', 'assigned']);

            // 1. Exact age band match
            $match = (clone $base)
                ->whereHas('ageBand', fn ($q) => $q
                    ->where(fn ($q2) => $q2->whereNull('min_age')->orWhere('min_age', '<=', $age))
                    ->where(fn ($q2) => $q2->whereNull('max_age')->orWhere('max_age', '>=', $age))
                )
                ->first();
            if ($match) {
                return $match;
            }

            // 2. Open division (no age restriction)
            $open = (clone $base)->whereNull('age_band_id')->first();
            if ($open) {
                return $open;
            }
        }

        return null;
    }

    private function assignWeightSex(int $eventId, string $sex, $ctx): ?Division
    {
        $weight = $ctx->weight_kg;

        $base = Division::where('competition_event_id', $eventId)
            ->where('sex', $sex)
            ->whereIn('status', ['pending', 'assigned']);

        if ($weight) {
            $match = (clone $base)
                ->whereHas('weightClass', fn ($q) => $q
                    ->where(fn ($q2) => $q2->whereNull('max_kg')->orWhere('max_kg', '>=', $weight))
                    ->orderBy('max_kg')
                )
                ->join('weight_classes', 'divisions.weight_class_id', '=', 'weight_classes.id')
                ->orderByRaw('CASE WHEN weight_classes.max_kg IS NULL THEN 1 ELSE 0 END')
                ->orderBy('weight_classes.max_kg')
                ->select('divisions.*')
                ->first();
            if ($match) {
                return $match;
            }
        }

        return (clone $base)->whereNull('weight_class_id')->first();
    }

    private function assignAgeRank(int $eventId, $ctx): ?Division
    {
        $age  = $ctx->age;
        $rank = $this->normalizeRank($ctx);

        $base = Division::where('competition_event_id', $eventId)
            ->where('sex', 'mixed')
            ->whereIn('status', ['pending', 'assigned']);

        if ($rank !== null) {
            $match = (clone $base)
                ->whereHas('ageBand', fn ($q) => $q
                    ->where(fn ($q2) => $q2->whereNull('min_age')->orWhere('min_age', '<=', $age))
                    ->where(fn ($q2) => $q2->whereNull('max_age')->orWhere('max_age', '>=', $age))
                )
                ->whereHas('rankBand', fn ($q) => $q
                    ->where(fn ($q2) => $q2->whereNull('rank_min')->orWhere('rank_min', '<=', $rank))
                    ->where(fn ($q2) => $q2->whereNull('rank_max')->orWhere('rank_max', '>=', $rank))
                )
                ->first();
            if ($match) {
                return $match;
            }
        }

        $ageMatch = (clone $base)
            ->whereNull('rank_band_id')
            ->whereHas('ageBand', fn ($q) => $q
                ->where(fn ($q2) => $q2->whereNull('min_age')->orWhere('min_age', '<=', $age))
                ->where(fn ($q2) => $q2->whereNull('max_age')->orWhere('max_age', '>=', $age))
            )
            ->first();
        if ($ageMatch) {
            return $ageMatch;
        }

        return (clone $base)->whereNull('age_band_id')->whereNull('rank_band_id')->first();
    }

    private function assignAgeOnly(int $eventId, $ctx): ?Division
    {
        $age = $ctx->age;

        $base = Division::where('competition_event_id', $eventId)
            ->where('sex', 'mixed')
            ->whereIn('status', ['pending', 'assigned']);

        $match = (clone $base)
            ->whereHas('ageBand', fn ($q) => $q
                ->where(fn ($q2) => $q2->whereNull('min_age')->orWhere('min_age', '<=', $age))
                ->where(fn ($q2) => $q2->whereNull('max_age')->orWhere('max_age', '>=', $age))
            )
            ->first();
        if ($match) {
            return $match;
        }

        return (clone $base)->whereNull('age_band_id')->first();
    }

    private function assignAgeWeight(int $eventId, $ctx): ?Division
    {
        $age    = $ctx->age;
        $weight = $ctx->weight_kg;

        $base = Division::where('competition_event_id', $eventId)
            ->where('sex', 'mixed')
            ->whereIn('status', ['pending', 'assigned']);

        if ($weight) {
            $match = (clone $base)
                ->whereHas('ageBand', fn ($q) => $q
                    ->where(fn ($q2) => $q2->whereNull('min_age')->orWhere('min_age', '<=', $age))
                    ->where(fn ($q2) => $q2->whereNull('max_age')->orWhere('max_age', '>=', $age))
                )
                ->whereHas('weightClass', fn ($q) => $q
                    ->where(fn ($q2) => $q2->whereNull('max_kg')->orWhere('max_kg', '>=', $weight))
                    ->orderBy('max_kg')
                )
                ->join('weight_classes', 'divisions.weight_class_id', '=', 'weight_classes.id')
                ->orderByRaw('CASE WHEN weight_classes.max_kg IS NULL THEN 1 ELSE 0 END')
                ->orderBy('weight_classes.max_kg')
                ->select('divisions.*')
                ->first();
            if ($match) {
                return $match;
            }
        }

        return (clone $base)
            ->whereHas('ageBand', fn ($q) => $q
                ->where(fn ($q2) => $q2->whereNull('min_age')->orWhere('min_age', '<=', $age))
                ->where(fn ($q2) => $q2->whereNull('max_age')->orWhere('max_age', '>=', $age))
            )
            ->whereNull('weight_class_id')
            ->first();
    }

    private function assignAgeWeightSex(int $eventId, string $sex, $ctx): ?Division
    {
        $age    = $ctx->age;
        $weight = $ctx->weight_kg;

        foreach ([$sex, 'mixed'] as $trySex) {
            $base = Division::where('competition_event_id', $eventId)
                ->where('sex', $trySex)
                ->whereIn('status', ['pending', 'assigned']);

            if ($weight) {
                $match = (clone $base)
                    ->whereHas('ageBand', fn ($q) => $q
                        ->where(fn ($q2) => $q2->whereNull('min_age')->orWhere('min_age', '<=', $age))
                        ->where(fn ($q2) => $q2->whereNull('max_age')->orWhere('max_age', '>=', $age))
                    )
                    ->whereHas('weightClass', fn ($q) => $q
                        ->where(fn ($q2) => $q2->whereNull('max_kg')->orWhere('max_kg', '>=', $weight))
                    )
                    ->join('weight_classes', 'divisions.weight_class_id', '=', 'weight_classes.id')
                    ->orderByRaw('CASE WHEN weight_classes.max_kg IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('weight_classes.max_kg')
                    ->select('divisions.*')
                    ->first();
                if ($match) {
                    return $match;
                }
            }

            $open = (clone $base)
                ->whereHas('ageBand', fn ($q) => $q
                    ->where(fn ($q2) => $q2->whereNull('min_age')->orWhere('min_age', '<=', $age))
                    ->where(fn ($q2) => $q2->whereNull('max_age')->orWhere('max_age', '>=', $age))
                )
                ->whereNull('weight_class_id')
                ->first();
            if ($open) {
                return $open;
            }
        }

        return null;
    }

    /**
     * Return all divisions for a competition event that a competitor is eligible to enter.
     * Accepts a context object with: gender, age, rank_type, rank_kyu, rank_dan, weight_kg.
     * This may be a CompetitorProfile, an Enrolment-derived stdClass, or a form-built stdClass.
     */
    public function getEligibleDivisions(CompetitionEvent $compEvent, $ctx): \Illuminate\Database\Eloquent\Collection
    {
        $filter = $compEvent->effectiveDivisionFilter();

        $base = Division::where('competition_event_id', $compEvent->id)
            ->whereIn('status', ['pending', 'assigned']);

        if (! $ctx) {
            return $base->orderBy('label')->get();
        }

        // Load all candidates with relationships; filter in PHP to avoid N correlated subqueries
        $divisions = $base->with(['ageBand', 'rankBand', 'weightClass'])->get();

        $sex    = $ctx->gender;
        $age    = $ctx->age;
        $rank   = $this->normalizeRank($ctx);
        $weight = $ctx->weight_kg;

        // Sex filter
        if (in_array($filter, ['age_rank_sex', 'age_sex', 'weight_sex', 'age_weight_sex'])) {
            if ($sex !== null) {
                $divisions = $divisions->filter(fn ($d) => $d->sex === $sex || $d->sex === null);
            }
        } else {
            $divisions = $divisions->filter(fn ($d) => $d->sex === 'mixed');
        }

        // Reusable eligibility checks
        $fitsAge = fn ($d) => ! $d->age_band_id
            || ($d->ageBand
                && ($d->ageBand->min_age === null || $d->ageBand->min_age <= $age)
                && ($d->ageBand->max_age === null || $d->ageBand->max_age >= $age));

        $fitsRank = fn ($d) => ! $d->rank_band_id
            || ($rank !== null && $d->rankBand
                && ($d->rankBand->rank_min === null || $d->rankBand->rank_min <= $rank)
                && ($d->rankBand->rank_max === null || $d->rankBand->rank_max >= $rank));

        // Derive best-fit weight class from already-loaded relationships (avoids extra DB query)
        $resolveBestMaxKg = fn ($pool) => $pool
            ->map(fn ($d) => $d->weightClass?->max_kg)
            ->filter(fn ($kg) => $kg !== null && $kg >= $weight)
            ->min();

        $fitsWeight = fn ($d, $bestMaxKg) => ! $d->weight_class_id
            || ($bestMaxKg !== null
                ? (float) ($d->weightClass?->max_kg) == (float) $bestMaxKg
                : $d->weightClass?->max_kg === null);

        if ($filter === 'age_rank_sex') {
            if ($age !== null) $divisions = $divisions->filter($fitsAge);
            $divisions = $divisions->filter($fitsRank);
        } elseif ($filter === 'age_sex') {
            if ($age !== null) $divisions = $divisions->filter($fitsAge);
        } elseif ($filter === 'weight_sex') {
            if ($weight !== null) {
                $bestMaxKg = $resolveBestMaxKg($divisions);
                $divisions = $divisions->filter(fn ($d) => $fitsWeight($d, $bestMaxKg));
            }
            // weight_sex uses ascending max_kg order (unlimited class last)
            return new \Illuminate\Database\Eloquent\Collection(
                $divisions->sortBy(fn ($d) => $d->weightClass?->max_kg ?? PHP_FLOAT_MAX)->values()->all()
            );
        } elseif ($filter === 'age_rank') {
            if ($age !== null) $divisions = $divisions->filter($fitsAge);
            $divisions = $divisions->filter($fitsRank);
        } elseif ($filter === 'age_only') {
            if ($age !== null) $divisions = $divisions->filter($fitsAge);
        } elseif (in_array($filter, ['age_weight', 'age_weight_sex'])) {
            if ($age !== null) $divisions = $divisions->filter($fitsAge);
            if ($weight !== null) {
                $bestMaxKg = $resolveBestMaxKg($divisions);
                $divisions = $divisions->filter(fn ($d) => $fitsWeight($d, $bestMaxKg));
            }
        }

        return new \Illuminate\Database\Eloquent\Collection(
            $divisions->sortBy('label')->values()->all()
        );
    }

    /**
     * Find the minimum max_kg weight class that can accommodate the given weight.
     * Returns null if no capped class fits (person falls into the "over" / unlimited class).
     */
    private function bestFitMaxKg(int $competitionId, ?float $weight): ?float
    {
        if (! $weight) {
            return null;
        }

        return \App\Models\WeightClass::where('competition_id', $competitionId)
            ->whereNotNull('max_kg')
            ->where('max_kg', '>=', $weight)
            ->min('max_kg');
    }

    /**
     * Convert rank fields to a signed integer for range comparison.
     * 9th kyu = -9 … 1st kyu = -1, experience = 0, 1st dan = 1 … 10th dan = 10.
     * Accepts any object with rank_type, rank_kyu, rank_dan.
     */
    private function normalizeRank($ctx): ?int
    {
        return match ($ctx->rank_type ?? null) {
            'kyu'        => $ctx->rank_kyu ? -$ctx->rank_kyu : null,
            'dan'        => $ctx->rank_dan ?? null,
            'experience' => 0,
            default      => null,
        };
    }
}
