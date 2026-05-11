<?php

namespace Database\Seeders;

use App\Models\Competition;
use App\Models\CompetitionEvent;
use App\Models\Division;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LFPCompetitionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Clear all existing competition data
        DB::statement('PRAGMA foreign_keys = OFF');
        DB::table('round_robin_matches')->delete();
        DB::table('results')->delete();
        DB::table('enrolment_events')->delete();
        DB::table('enrolments')->delete();
        DB::table('divisions')->delete();
        DB::table('competition_events')->delete();
        DB::table('weight_classes')->delete();
        DB::table('rank_bands')->delete();
        DB::table('age_bands')->delete();
        DB::table('competitions')->delete();
        DB::statement('PRAGMA foreign_keys = ON');

        $this->command->info('Cleared all competition data.');

        // 2. Load PDF reference data
        $lfp = require database_path('data/lfp-entry-form.php');

        // 3. Competition
        $competition = Competition::create([
            'name'                 => 'LFP Invitational Tournament — Round 1',
            'competition_date'     => '2026-03-28',
            'start_time'           => '09:00:00',
            'status'               => 'draft',
            'fee_first_event'      => $lfp['fees']['first_event'],
            'fee_additional_event' => $lfp['fees']['additional_events'],
            'late_surcharge'       => 0,
        ]);

        $this->command->info("Created competition: {$competition->name}");

        // 4. Age bands
        $ab = [];
        foreach ([
            ['label' => '8 & Under',   'min_age' => null, 'max_age' => 8,    'sort_order' => 1],
            ['label' => '9–11 Years',  'min_age' => 9,    'max_age' => 11,   'sort_order' => 2],
            ['label' => '12–14 Years', 'min_age' => 12,   'max_age' => 14,   'sort_order' => 3],
            ['label' => '15+ Years',   'min_age' => 15,   'max_age' => null, 'sort_order' => 4],
            ['label' => '40+ Years',   'min_age' => 40,   'max_age' => null, 'sort_order' => 5],
        ] as $data) {
            $ab[$data['label']] = $competition->ageBands()->create($data);
        }

        // 5. Rank bands
        $rb = [];
        foreach ([
            ['label' => 'Open',        'rank_min' => null, 'rank_max' => null, 'sort_order' => 1],
            ['label' => '10–6 Kyu',    'rank_min' => -10,  'rank_max' => -6,   'sort_order' => 2],
            ['label' => '5 Kyu–Black', 'rank_min' => -5,   'rank_max' => null, 'sort_order' => 3],
            ['label' => '5–1 Kyu',     'rank_min' => -5,   'rank_max' => -1,   'sort_order' => 4],
            ['label' => 'Black Belt',  'rank_min' => 1,    'rank_max' => null, 'sort_order' => 5],
        ] as $data) {
            $rb[$data['label']] = $competition->rankBands()->create($data);
        }

        // 6. Weight classes (Sumo)
        $wc = [];
        foreach ([
            ['label' => 'Flyweight',    'max_kg' => 30,   'sort_order' => 1],
            ['label' => 'Featherweight','max_kg' => 37,   'sort_order' => 2],
            ['label' => 'Bantamweight', 'max_kg' => 45,   'sort_order' => 3],
            ['label' => 'Lightweight',  'max_kg' => 53,   'sort_order' => 4],
            ['label' => 'Welterweight', 'max_kg' => 60,   'sort_order' => 5],
            ['label' => 'Middleweight', 'max_kg' => 70,   'sort_order' => 6],
            ['label' => 'Cruiserweight','max_kg' => 80,   'sort_order' => 7],
            ['label' => 'Heavyweight',  'max_kg' => null, 'sort_order' => 8],
        ] as $data) {
            $wc[$data['label']] = $competition->weightClasses()->create($data);
        }

        $this->command->info('Created age bands, rank bands, and weight classes.');

        // 7. Competition events
        $kata = CompetitionEvent::create([
            'competition_id'        => $competition->id,
            'name'                  => 'Kata',
            'event_code'            => 'KA',
            'scoring_method'        => 'judges_total',
            'tournament_format'     => 'once_off',
            'division_filter'       => 'age_rank',
            'judge_count'           => 3,
            'requires_partner'      => false,

            'status'                => 'scheduled',
            'running_order'         => 1,
        ]);

        $tile = CompetitionEvent::create([
            'competition_id'        => $competition->id,
            'name'                  => 'Tile Breaking',
            'event_code'            => 'TB',
            'scoring_method'        => 'judges_total',
            'tournament_format'     => 'once_off',
            'division_filter'       => 'age_rank',
            'judge_count'           => 3,
            'requires_partner'      => false,

            'status'                => 'scheduled',
            'running_order'         => 2,
        ]);

        $yakusuko = CompetitionEvent::create([
            'competition_id'        => $competition->id,
            'name'                  => 'Yakusuko',
            'event_code'            => 'YA',
            'scoring_method'        => 'judges_total',
            'tournament_format'     => 'once_off',
            'division_filter'       => 'age_only',
            'judge_count'           => 3,
            'requires_partner'      => true,

            'status'                => 'scheduled',
            'running_order'         => 3,
        ]);

        $semiContact = CompetitionEvent::create([
            'competition_id'        => $competition->id,
            'name'                  => 'Semi Contact',
            'event_code'            => 'SC',
            'scoring_method'        => 'win_loss',
            'tournament_format'     => 'single_elimination',
            'division_filter'       => 'age_sex',
            'judge_count'           => 0,
            'requires_partner'      => false,

            'status'                => 'scheduled',
            'running_order'         => 4,
        ]);

        $pointSparring = CompetitionEvent::create([
            'competition_id'        => $competition->id,
            'name'                  => 'Point Sparring',
            'event_code'            => 'PS',
            'scoring_method'        => 'win_loss',
            'tournament_format'     => 'single_elimination',
            'division_filter'       => 'age_rank_sex',
            'judge_count'           => 0,
            'requires_partner'      => false,

            'status'                => 'scheduled',
            'running_order'         => 5,
        ]);

        $continuousSparring = CompetitionEvent::create([
            'competition_id'        => $competition->id,
            'name'                  => 'Continuous Sparring',
            'event_code'            => 'CS',
            'scoring_method'        => 'win_loss',
            'tournament_format'     => 'single_elimination',
            'division_filter'       => 'age_rank_sex',
            'judge_count'           => 0,
            'requires_partner'      => false,

            'status'                => 'scheduled',
            'running_order'         => 6,
        ]);

        $sumo = CompetitionEvent::create([
            'competition_id'        => $competition->id,
            'name'                  => 'Sumo',
            'event_code'            => 'SW',
            'scoring_method'        => 'win_loss',
            'tournament_format'     => 'single_elimination',
            'division_filter'       => 'weight_sex',
            'judge_count'           => 0,
            'requires_partner'      => false,

            'status'                => 'scheduled',
            'running_order'         => 7,
        ]);

        $this->command->info('Created 7 competition events.');

        // 8. Divisions — created directly from PDF data, exact codes and structure
        $sexMap = ['Mixed' => null, 'Female' => 'F', 'Male' => 'M'];

        // Rank label → rank band key (handles PDF variations like "5th–Black", "Black")
        $rankMap = [
            'Open'        => 'Open',
            '10–6 Kyu'    => '10–6 Kyu',
            '5 Kyu–Black' => '5 Kyu–Black',
            '5th–Black'   => '5 Kyu–Black',
            '5–1 Kyu'     => '5–1 Kyu',
            'Black'       => 'Black Belt',
        ];

        // Age label → age band key (handles short forms like "15+", "40+")
        $ageMap = [
            '8 & Under'   => '8 & Under',
            '9–11 Years'  => '9–11 Years',
            '12–14 Years' => '12–14 Years',
            '15+ Years'   => '15+ Years',
            '15+'         => '15+ Years',
            '40+ Years'   => '40+ Years',
            '40+'         => '40+ Years',
        ];

        // --- Kata (10 divisions) ---
        foreach ($lfp['kata'] as $i => $d) {
            Division::create([
                'competition_event_id' => $kata->id,
                'code'                 => $d['code'],
                'age_band_id'          => $ab[$ageMap[$d['age']]]->id,
                'rank_band_id'         => $rb[$rankMap[$d['rank']]]->id,
                'sex'                  => null,
                'running_order'        => $i + 1,
                'status'               => 'pending',
            ]);
        }
        $this->command->info('Created ' . count($lfp['kata']) . ' Kata divisions.');

        // --- Tile Breaking (8 divisions) ---
        foreach ($lfp['tile_breaking'] as $i => $d) {
            Division::create([
                'competition_event_id' => $tile->id,
                'code'                 => $d['code'],
                'age_band_id'          => $ab[$ageMap[$d['age']]]->id,
                'rank_band_id'         => $rb[$rankMap[$d['rank']]]->id,
                'sex'                  => null,
                'running_order'        => $i + 1,
                'status'               => 'pending',
            ]);
        }
        $this->command->info('Created ' . count($lfp['tile_breaking']) . ' Tile Breaking divisions.');

        // --- Yakusuko (4 divisions — age only, no rank band) ---
        foreach ($lfp['yakusuko'] as $i => $d) {
            Division::create([
                'competition_event_id' => $yakusuko->id,
                'code'                 => $d['code'],
                'age_band_id'          => $ab[$ageMap[$d['age']]]->id,
                'rank_band_id'         => null,
                'sex'                  => null,
                'running_order'        => $i + 1,
                'status'               => 'pending',
            ]);
        }
        $this->command->info('Created ' . count($lfp['yakusuko']) . ' Yakusuko divisions.');

        // --- Semi Contact (4 divisions — custom age groups not matching standard bands) ---
        // SC01/SC02 have no standard age band (Under 11 / Under 15); use raw DB insert to bypass auto-label.
        // SC01/SC02 have no standard age band — pass label explicitly (auto-gen skipped when all bands null)
        // SC03/SC04 have a band+sex set — label is auto-generated as "15+ Years / Female|Male"
        $scRows = [
            ['SC01', 'Under 11 — Mixed',   null,                    null],
            ['SC02', 'Under 15 — Mixed',   null,                    null],
            ['SC03', '15+ Years — Female', $ab['15+ Years']->id,    'F'],
            ['SC04', '15+ Years — Male',   $ab['15+ Years']->id,    'M'],
        ];
        foreach ($scRows as $i => [$code, $label, $ageBandId, $sex]) {
            Division::create([
                'competition_event_id' => $semiContact->id,
                'code'                 => $code,
                'label'                => $label,
                'age_band_id'          => $ageBandId,
                'sex'                  => $sex,
                'running_order'        => $i + 1,
                'status'               => 'pending',
            ]);
        }
        $this->command->info('Created 4 Semi Contact divisions.');

        // --- Point Sparring (15 divisions) ---
        foreach ($lfp['point_sparring'] as $i => $d) {
            Division::create([
                'competition_event_id' => $pointSparring->id,
                'code'                 => $d['code'],
                'age_band_id'          => $ab[$ageMap[$d['age']]]->id,
                'rank_band_id'         => $rb[$rankMap[$d['rank']]]->id,
                'sex'                  => $sexMap[$d['sex']],
                'running_order'        => $i + 1,
                'status'               => 'pending',
            ]);
        }
        $this->command->info('Created ' . count($lfp['point_sparring']) . ' Point Sparring divisions.');

        // --- Continuous Sparring (15 divisions — same structure as Point Sparring) ---
        foreach ($lfp['continuous_sparring'] as $i => $d) {
            Division::create([
                'competition_event_id' => $continuousSparring->id,
                'code'                 => $d['code'],
                'age_band_id'          => $ab[$ageMap[$d['age']]]->id,
                'rank_band_id'         => $rb[$rankMap[$d['rank']]]->id,
                'sex'                  => $sexMap[$d['sex']],
                'running_order'        => $i + 1,
                'status'               => 'pending',
            ]);
        }
        $this->command->info('Created ' . count($lfp['continuous_sparring']) . ' Continuous Sparring divisions.');

        // --- Sumo (16 divisions — weight class + sex) ---
        foreach ($lfp['sumo'] as $i => $d) {
            Division::create([
                'competition_event_id' => $sumo->id,
                'code'                 => $d['code'],
                'weight_class_id'      => $wc[$d['division']]->id,
                'sex'                  => $sexMap[$d['sex']],
                'running_order'        => $i + 1,
                'status'               => 'pending',
            ]);
        }
        $this->command->info('Created ' . count($lfp['sumo']) . ' Sumo divisions.');

        $total = Division::whereHas('competitionEvent', fn ($q) => $q->where('competition_id', $competition->id))->count();
        $this->command->info("Total: {$total} divisions across 7 competition events.");
        $this->command->info("Run: php artisan db:seed --class=LFPCompetitionSeeder");
        $this->command->info("Then set competition status to 'open' when ready for enrolments.");
    }
}
