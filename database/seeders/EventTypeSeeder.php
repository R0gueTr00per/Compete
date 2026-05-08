<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EventTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Kata',
                'scoring_method' => 'judges_total',
                'division_filter' => 'age_rank',
                'requires_partner' => false,
                'requires_weight_check' => false,
                'default_target_score' => null,
                'judge_count' => 3,
            ],
            [
                'name' => 'Tile Breaking',
                'scoring_method' => 'judges_total',
                'division_filter' => 'age_rank',
                'requires_partner' => false,
                'requires_weight_check' => false,
                'default_target_score' => null,
                'judge_count' => 3,
            ],
            [
                'name' => 'Yakusuko',
                'scoring_method' => 'judges_total',
                'division_filter' => 'age_only',
                'requires_partner' => true,
                'requires_weight_check' => false,
                'default_target_score' => null,
                'judge_count' => 3,
            ],
            [
                'name' => 'Semi Contact',
                'scoring_method' => 'win_loss',
                'division_filter' => 'age_sex',
                'requires_partner' => false,
                'requires_weight_check' => true,
                'default_target_score' => null,
                'judge_count' => 0,
            ],
            [
                'name' => 'Point Sparring',
                'scoring_method' => 'first_to_n',
                'division_filter' => 'age_rank_sex',
                'requires_partner' => false,
                'requires_weight_check' => false,
                'default_target_score' => 5,
                'judge_count' => 0,
            ],
            [
                'name' => 'Continuous Sparring',
                'scoring_method' => 'win_loss',
                'division_filter' => 'age_rank_sex',
                'requires_partner' => false,
                'requires_weight_check' => false,
                'default_target_score' => null,
                'judge_count' => 0,
            ],
            [
                'name' => 'Sumo',
                'scoring_method' => 'first_to_n',
                'division_filter' => 'weight_sex',
                'requires_partner' => false,
                'requires_weight_check' => true,
                'default_target_score' => 5,
                'judge_count' => 0,
            ],
        ];

        foreach ($types as $type) {
            DB::table('event_types')->updateOrInsert(
                ['name' => $type['name']],
                array_merge($type, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
