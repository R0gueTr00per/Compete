<?php

namespace Database\Seeders;

use App\Models\AgeBand;
use App\Models\Competition;
use App\Models\CompetitionEvent;
use App\Models\Organisation;
use App\Models\RankBand;
use App\Models\WeightClass;
use App\Services\DivisionAssignmentService;
use Illuminate\Database\Seeder;

/**
 * Creates (or updates) a demo competition for the most-recently-created organisation
 * whose slug starts with "demo".
 *
 * Covers every division_filter type (one event each):
 *   age_rank, age_rank_sex, age_sex, age_only, weight_sex, age_weight, age_weight_sex
 *
 * All divisions are built automatically via DivisionAssignmentService from the bands defined here.
 * Safe to re-run — uses updateOrCreate throughout.
 */
class DemoCompetitionSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organisation::where('slug', 'like', 'demo%')->latest('id')->first();

        if (! $org) {
            $this->command->warn('No demo organisation found (slug starting with "demo"). Skipping.');
            return;
        }

        $this->command->info("Org: {$org->name} (slug: {$org->slug})");

        // ── Competition ───────────────────────────────────────────────────────────
        $competition = Competition::firstOrCreate(
            ['organisation_id' => $org->id, 'name' => 'Demo Tournament'],
            [
                'competition_date'              => '2027-06-15',
                'start_time'                    => '09:00:00',
                'enrolment_due_date'            => '2027-06-01',
                'location_name'                 => 'Demo Sports Centre',
                'location_address'              => '1 Demo Street, Melbourne VIC 3000',
                'fee_first_event'               => 38.00,
                'fee_additional_event'          => 12.00,
                'late_surcharge'                => 15.00,
                'fee_official_first_event'      => 0.00,
                'fee_official_additional_event' => 0.00,
                'status'                        => 'open',
            ]
        );

        // ── Age bands ─────────────────────────────────────────────────────────────
        foreach ([
            ['label' => '8 & Under',   'min_age' => null, 'max_age' => 8,    'sort_order' => 1],
            ['label' => '9-11 Years',  'min_age' => 9,    'max_age' => 11,   'sort_order' => 2],
            ['label' => '12-14 Years', 'min_age' => 12,   'max_age' => 14,   'sort_order' => 3],
            ['label' => '15+ Years',   'min_age' => 15,   'max_age' => 39,   'sort_order' => 4],
            ['label' => '40+ Years',   'min_age' => 40,   'max_age' => null, 'sort_order' => 5],
        ] as $data) {
            AgeBand::firstOrCreate(
                ['competition_id' => $competition->id, 'label' => $data['label']],
                $data + ['competition_id' => $competition->id]
            );
        }

        // ── Rank bands ────────────────────────────────────────────────────────────
        // rank_min/rank_max use the signed integer scale: 9th kyu = -9 … 1st dan = 1 … 10th dan = 10
        foreach ([
            ['label' => 'Open',        'description' => 'All ranks',                     'rank_min' => null, 'rank_max' => null, 'sort_order' => 1],
            ['label' => '10-6 Kyu',    'description' => '10th to 6th kyu (beginner)',    'rank_min' => -10,  'rank_max' => -6,   'sort_order' => 2],
            ['label' => '5 Kyu-Black', 'description' => '5th kyu through black belt',    'rank_min' => -5,   'rank_max' => null, 'sort_order' => 3],
            ['label' => '5-1 Kyu',     'description' => '5th to 1st kyu (intermediate)', 'rank_min' => -5,   'rank_max' => -1,   'sort_order' => 4],
            ['label' => 'Black Belt',  'description' => '1st dan and above',             'rank_min' => 1,    'rank_max' => null, 'sort_order' => 5],
        ] as $data) {
            RankBand::firstOrCreate(
                ['competition_id' => $competition->id, 'label' => $data['label']],
                $data + ['competition_id' => $competition->id]
            );
        }

        // ── Weight classes ────────────────────────────────────────────────────────
        foreach ([
            ['label' => 'Under 25 kg', 'max_kg' => 25.0,  'sort_order' => 1],
            ['label' => '26-30 kg',    'max_kg' => 30.0,  'sort_order' => 2],
            ['label' => '31-40 kg',    'max_kg' => 40.0,  'sort_order' => 3],
            ['label' => '41-50 kg',    'max_kg' => 50.0,  'sort_order' => 4],
            ['label' => '51-60 kg',    'max_kg' => 60.0,  'sort_order' => 5],
            ['label' => '61-70 kg',    'max_kg' => 70.0,  'sort_order' => 6],
            ['label' => '71-80 kg',    'max_kg' => 80.0,  'sort_order' => 7],
            ['label' => '80+ kg',      'max_kg' => null,  'sort_order' => 8],
        ] as $data) {
            WeightClass::firstOrCreate(
                ['competition_id' => $competition->id, 'label' => $data['label']],
                $data + ['competition_id' => $competition->id]
            );
        }

        // ── Events — one per division_filter type ─────────────────────────────────
        // Divisions are auto-generated from the bands above via DivisionAssignmentService.
        // Expected division counts per filter (5 age × 5 rank × 2 sex):
        //   age_rank        = 5×5     = 25 (mixed)
        //   age_rank_sex    = 5×5×2   = 50
        //   age_sex         = 5×2     = 10
        //   age_only        = 5       =  5 (mixed)
        //   weight_sex      = 8×2     = 16
        //   age_weight      = 5×8     = 40 (mixed)
        //   age_weight_sex  = 5×8×2   = 80
        //   Total:                    226
        foreach ([
            [
                'name'              => 'Kata',
                'event_code'        => 'KA',
                'running_order'     => 1,
                'location_label'    => null,
                'scoring_method'    => 'judges_total',
                'tournament_format' => 'once_off',
                'division_filter'   => 'age_rank',
                'judge_count'       => 3,
                'target_score'      => null,
                'requires_partner'  => false,
            ],
            [
                'name'              => 'Point Sparring',
                'event_code'        => 'PS',
                'running_order'     => 2,
                'location_label'    => null,
                'scoring_method'    => 'first_to_n',
                'tournament_format' => 'single_elimination',
                'division_filter'   => 'age_rank_sex',
                'judge_count'       => 0,
                'target_score'      => 5,
                'requires_partner'  => false,
            ],
            [
                'name'              => 'Semi Contact',
                'event_code'        => 'SC',
                'running_order'     => 3,
                'location_label'    => null,
                'scoring_method'    => 'win_loss',
                'tournament_format' => 'single_elimination',
                'division_filter'   => 'age_sex',
                'judge_count'       => 0,
                'target_score'      => null,
                'requires_partner'  => false,
            ],
            [
                'name'              => 'Yakusuko',
                'event_code'        => 'YA',
                'running_order'     => 4,
                'location_label'    => null,
                'scoring_method'    => 'judges_total',
                'tournament_format' => 'once_off',
                'division_filter'   => 'age_only',
                'judge_count'       => 3,
                'target_score'      => null,
                'requires_partner'  => true,
            ],
            [
                'name'              => 'Sumo',
                'event_code'        => 'SU',
                'running_order'     => 5,
                'location_label'    => null,
                'scoring_method'    => 'win_loss',
                'tournament_format' => 'single_elimination',
                'division_filter'   => 'weight_sex',
                'judge_count'       => 0,
                'target_score'      => null,
                'requires_partner'  => false,
            ],
            [
                'name'              => 'Weapons Kata',
                'event_code'        => 'WK',
                'running_order'     => 6,
                'location_label'    => null,
                'scoring_method'    => 'judges_total',
                'tournament_format' => 'once_off',
                'division_filter'   => 'age_weight',
                'judge_count'       => 3,
                'target_score'      => null,
                'requires_partner'  => false,
            ],
            [
                'name'              => 'Creative Solo',
                'event_code'        => 'CS',
                'running_order'     => 7,
                'location_label'    => null,
                'scoring_method'    => 'judges_total',
                'tournament_format' => 'once_off',
                'division_filter'   => 'age_weight_sex',
                'judge_count'       => 3,
                'target_score'      => null,
                'requires_partner'  => false,
            ],
        ] as $cfg) {
            CompetitionEvent::firstOrCreate(
                ['competition_id' => $competition->id, 'name' => $cfg['name']],
                [
                    'event_code'         => $cfg['event_code'],
                    'running_order'      => $cfg['running_order'],
                    'location_label'     => null,
                    'scoring_method'     => $cfg['scoring_method'],
                    'tournament_format'  => $cfg['tournament_format'],
                    'division_filter'    => $cfg['division_filter'],
                    'judge_count'        => $cfg['judge_count'],
                    'target_score'       => $cfg['target_score'],
                    'requires_partner'   => $cfg['requires_partner'],
                    'status'             => 'scheduled',
                ]
            );
        }

        // ── Build all divisions from bands ────────────────────────────────────────
        $competition->load('competitionEvents');
        $service = app(DivisionAssignmentService::class);
        $created = $service->buildDivisionsForCompetition($competition);
        $service->assignCodesForCompetition($competition);

        $total = $competition->allDivisions()->count();
        $this->command->info(
            "Competition \"{$competition->name}\": 7 events, {$created} new divisions ({$total} total)."
        );
    }
}
