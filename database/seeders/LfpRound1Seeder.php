<?php

namespace Database\Seeders;

use App\Models\AgeBand;
use App\Models\Competition;
use App\Models\CompetitionEvent;
use App\Models\CompetitorProfile;
use App\Models\Division;
use App\Models\RankBand;
use App\Models\User;
use App\Models\WeightClass;
use App\Services\EnrolmentService;
use Illuminate\Database\Seeder;

class LfpRound1Seeder extends Seeder
{
    public function run(): void
    {
        $competition = Competition::updateOrCreate(
            ['name' => 'LFP Round 1 2026'],
            [
                'competition_date'     => '2026-09-19',
                'start_time'           => '09:00:00',
                'checkin_time'         => '08:00:00',
                'enrolment_due_date'   => '2026-09-05',
                'location_name'        => 'LFP Training Centre',
                'location_address'     => 'Dragon St, Melbourne VIC 3000',
                'fee_first_event'      => 38.00,
                'fee_additional_event' => 12.00,
                'late_surcharge'       => 15.00,
                'status'               => 'open',
            ]
        );

        // --- Age bands ---
        $ab = [];
        foreach ([
            ['label' => '8 & Under',  'min_age' => null, 'max_age' => 8,  'sort_order' => 1],
            ['label' => '9-11 Years', 'min_age' => 9,    'max_age' => 11, 'sort_order' => 2],
            ['label' => '12-14 Years','min_age' => 12,   'max_age' => 14, 'sort_order' => 3],
            ['label' => '15+ Years',  'min_age' => 15,   'max_age' => 39, 'sort_order' => 4],
            ['label' => '40+ Years',  'min_age' => 40,   'max_age' => null,'sort_order' => 5],
        ] as $data) {
            $ab[$data['label']] = AgeBand::updateOrCreate(
                ['competition_id' => $competition->id, 'label' => $data['label']],
                $data + ['competition_id' => $competition->id]
            );
        }

        // --- Rank bands ---
        $rb = [];
        foreach ([
            ['label' => 'Open',        'description' => 'All ranks',                  'rank_min' => null, 'rank_max' => null, 'sort_order' => 1],
            ['label' => '10-6 Kyu',    'description' => '10th to 6th kyu (beginner)', 'rank_min' => -10,  'rank_max' => -6,  'sort_order' => 2],
            ['label' => '5 Kyu-Black', 'description' => '5th kyu through black belt', 'rank_min' => -5,   'rank_max' => null,'sort_order' => 3],
            ['label' => '5-1 Kyu',     'description' => '5th to 1st kyu (intermediate)', 'rank_min' => -5,'rank_max' => -1,  'sort_order' => 4],
            ['label' => 'Black Belt',  'description' => '1st dan and above',          'rank_min' => 1,    'rank_max' => null,'sort_order' => 5],
        ] as $data) {
            $rb[$data['label']] = RankBand::updateOrCreate(
                ['competition_id' => $competition->id, 'label' => $data['label']],
                $data + ['competition_id' => $competition->id]
            );
        }

        // --- Weight classes (8 classes for Sumo) ---
        $wc = [];
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
            $wc[$data['label']] = WeightClass::updateOrCreate(
                ['competition_id' => $competition->id, 'label' => $data['label']],
                $data + ['competition_id' => $competition->id]
            );
        }

        // --- Competition events ---
        $events = [];
        foreach ([
            ['name' => 'Kata',                'event_code' => 'KA', 'order' => 1, 'location' => 'Mat 1', 'scoring_method' => 'judges_total', 'tournament_format' => 'once_off',           'division_filter' => 'age_rank',     'judge_count' => 3, 'target_score' => null, 'requires_partner' => false, 'requires_weight_check' => false],
            ['name' => 'Tile Breaking',       'event_code' => 'TB', 'order' => 2, 'location' => 'Mat 1', 'scoring_method' => 'judges_total', 'tournament_format' => 'once_off',           'division_filter' => 'age_rank',     'judge_count' => 3, 'target_score' => null, 'requires_partner' => false, 'requires_weight_check' => false],
            ['name' => 'Yakusuko',            'event_code' => 'YA', 'order' => 3, 'location' => 'Mat 2', 'scoring_method' => 'judges_total', 'tournament_format' => 'once_off',           'division_filter' => 'age_only',     'judge_count' => 3, 'target_score' => null, 'requires_partner' => true,  'requires_weight_check' => false],
            ['name' => 'Semi Contact',        'event_code' => 'SC', 'order' => 4, 'location' => 'Mat 2', 'scoring_method' => 'win_loss',     'tournament_format' => 'single_elimination', 'division_filter' => 'age_sex',      'judge_count' => 0, 'target_score' => null, 'requires_partner' => false, 'requires_weight_check' => true],
            ['name' => 'Point Sparring',      'event_code' => 'PS', 'order' => 5, 'location' => 'Mat 3', 'scoring_method' => 'first_to_n',   'tournament_format' => 'single_elimination', 'division_filter' => 'age_rank_sex', 'judge_count' => 0, 'target_score' => 5,    'requires_partner' => false, 'requires_weight_check' => false],
            ['name' => 'Continuous Sparring', 'event_code' => 'CS', 'order' => 6, 'location' => 'Mat 3', 'scoring_method' => 'win_loss',     'tournament_format' => 'single_elimination', 'division_filter' => 'age_rank_sex', 'judge_count' => 0, 'target_score' => null, 'requires_partner' => false, 'requires_weight_check' => false],
            ['name' => 'Sumo',                'event_code' => 'SW', 'order' => 7, 'location' => 'Mat 4', 'scoring_method' => 'win_loss',     'tournament_format' => 'single_elimination', 'division_filter' => 'weight_sex',   'judge_count' => 0, 'target_score' => null, 'requires_partner' => false, 'requires_weight_check' => true],
        ] as $cfg) {
            $events[$cfg['name']] = CompetitionEvent::updateOrCreate(
                ['competition_id' => $competition->id, 'name' => $cfg['name']],
                [
                    'event_code'            => $cfg['event_code'],
                    'running_order'         => $cfg['order'],
                    'location_label'        => $cfg['location'],
                    'scoring_method'        => $cfg['scoring_method'],
                    'tournament_format'     => $cfg['tournament_format'],
                    'division_filter'       => $cfg['division_filter'],
                    'judge_count'           => $cfg['judge_count'],
                    'target_score'          => $cfg['target_score'],
                    'requires_partner'      => $cfg['requires_partner'],
                    'requires_weight_check' => $cfg['requires_weight_check'],
                    'status'                => 'scheduled',
                ]
            );
        }

        // --- Divisions ---
        // Kata (10) — age_rank, Mixed
        $kataDivs = [
            ['KA01', $ab['8 & Under'],  $rb['Open'],        null, null,   '8 & Under / Open'],
            ['KA02', $ab['9-11 Years'], $rb['10-6 Kyu'],    null, null,   '9-11 Years / 10-6 Kyu'],
            ['KA03', $ab['9-11 Years'], $rb['5 Kyu-Black'], null, null,   '9-11 Years / 5 Kyu-Black'],
            ['KA04', $ab['12-14 Years'],$rb['10-6 Kyu'],    null, null,   '12-14 Years / 10-6 Kyu'],
            ['KA05', $ab['12-14 Years'],$rb['5-1 Kyu'],     null, null,   '12-14 Years / 5-1 Kyu'],
            ['KA06', $ab['12-14 Years'],$rb['Black Belt'],  null, null,   '12-14 Years / Black Belt'],
            ['KA07', $ab['15+ Years'],  $rb['10-6 Kyu'],    null, null,   '15+ Years / 10-6 Kyu'],
            ['KA08', $ab['15+ Years'],  $rb['5-1 Kyu'],     null, null,   '15+ Years / 5-1 Kyu'],
            ['KA09', $ab['15+ Years'],  $rb['Black Belt'],  null, null,   '15+ Years / Black Belt'],
            ['KA10', $ab['40+ Years'],  $rb['5 Kyu-Black'], null, null,   '40+ Years / 5 Kyu-Black'],
        ];
        $this->createDivisions($events['Kata'], $kataDivs);

        // Tile Breaking (8) — age_rank, Mixed
        $tbDivs = [
            ['TB01', $ab['8 & Under'],  $rb['Open'],        null, null,   '8 & Under / Open'],
            ['TB02', $ab['9-11 Years'], $rb['10-6 Kyu'],    null, null,   '9-11 Years / 10-6 Kyu'],
            ['TB03', $ab['9-11 Years'], $rb['5 Kyu-Black'], null, null,   '9-11 Years / 5 Kyu-Black'],
            ['TB04', $ab['12-14 Years'],$rb['10-6 Kyu'],    null, null,   '12-14 Years / 10-6 Kyu'],
            ['TB05', $ab['12-14 Years'],$rb['5 Kyu-Black'], null, null,   '12-14 Years / 5 Kyu-Black'],
            ['TB06', $ab['15+ Years'],  $rb['10-6 Kyu'],    null, null,   '15+ Years / 10-6 Kyu'],
            ['TB07', $ab['15+ Years'],  $rb['5 Kyu-Black'], null, null,   '15+ Years / 5 Kyu-Black'],
            ['TB08', $ab['40+ Years'],  $rb['5 Kyu-Black'], null, null,   '40+ Years / 5 Kyu-Black'],
        ];
        $this->createDivisions($events['Tile Breaking'], $tbDivs);

        // Yakusuko (5) — age_only, Mixed
        $yaDivs = [
            ['YA01', $ab['8 & Under'],  null, null, null, '8 & Under'],
            ['YA02', $ab['9-11 Years'], null, null, null, '9-11 Years'],
            ['YA03', $ab['12-14 Years'],null, null, null, '12-14 Years'],
            ['YA04', $ab['15+ Years'],  null, null, null, '15+ Years'],
            ['YA05', $ab['40+ Years'],  null, null, null, '40+ Years'],
        ];
        $this->createDivisions($events['Yakusuko'], $yaDivs);

        // Semi Contact (10) — age_sex, sex split
        $scDivs = [
            ['SC01', $ab['8 & Under'],  null, null, 'M', '8 & Under / Male'],
            ['SC02', $ab['8 & Under'],  null, null, 'F', '8 & Under / Female'],
            ['SC03', $ab['9-11 Years'], null, null, 'M', '9-11 Years / Male'],
            ['SC04', $ab['9-11 Years'], null, null, 'F', '9-11 Years / Female'],
            ['SC05', $ab['12-14 Years'],null, null, 'M', '12-14 Years / Male'],
            ['SC06', $ab['12-14 Years'],null, null, 'F', '12-14 Years / Female'],
            ['SC07', $ab['15+ Years'],  null, null, 'M', '15+ Years / Male'],
            ['SC08', $ab['15+ Years'],  null, null, 'F', '15+ Years / Female'],
            ['SC09', $ab['40+ Years'],  null, null, 'M', '40+ Years / Male'],
            ['SC10', $ab['40+ Years'],  null, null, 'F', '40+ Years / Female'],
        ];
        $this->createDivisions($events['Semi Contact'], $scDivs);

        // Point Sparring (14) — Mixed for 8-14, sex-split for 15+/40+
        $psDivs = [
            ['PS01', $ab['8 & Under'],  $rb['Open'],       null, null,   '8 & Under / Open'],
            ['PS02', $ab['9-11 Years'], $rb['10-6 Kyu'],   null, null,   '9-11 Years / 10-6 Kyu'],
            ['PS03', $ab['9-11 Years'], $rb['5 Kyu-Black'],null, null,   '9-11 Years / 5 Kyu-Black'],
            ['PS04', $ab['12-14 Years'],$rb['10-6 Kyu'],   null, null,   '12-14 Years / 10-6 Kyu'],
            ['PS05', $ab['12-14 Years'],$rb['5-1 Kyu'],    null, null,   '12-14 Years / 5-1 Kyu'],
            ['PS06', $ab['12-14 Years'],$rb['Black Belt'], null, null,   '12-14 Years / Black Belt'],
            ['PS07', $ab['15+ Years'],  $rb['10-6 Kyu'],   null, 'M',    '15+ Years / 10-6 Kyu / Male'],
            ['PS08', $ab['15+ Years'],  $rb['10-6 Kyu'],   null, 'F',    '15+ Years / 10-6 Kyu / Female'],
            ['PS09', $ab['15+ Years'],  $rb['5-1 Kyu'],    null, 'M',    '15+ Years / 5-1 Kyu / Male'],
            ['PS10', $ab['15+ Years'],  $rb['5-1 Kyu'],    null, 'F',    '15+ Years / 5-1 Kyu / Female'],
            ['PS11', $ab['15+ Years'],  $rb['Black Belt'],  null, 'M',   '15+ Years / Black Belt / Male'],
            ['PS12', $ab['15+ Years'],  $rb['Black Belt'],  null, 'F',   '15+ Years / Black Belt / Female'],
            ['PS13', $ab['40+ Years'],  $rb['5 Kyu-Black'], null, 'M',   '40+ Years / 5 Kyu-Black / Male'],
            ['PS14', $ab['40+ Years'],  $rb['5 Kyu-Black'], null, 'F',   '40+ Years / 5 Kyu-Black / Female'],
        ];
        $this->createDivisions($events['Point Sparring'], $psDivs);

        // Continuous Sparring (9) — Mixed for 12-14, sex-split for 15+
        $csDivs = [
            ['CS01', $ab['12-14 Years'],$rb['10-6 Kyu'],  null, null,    '12-14 Years / 10-6 Kyu'],
            ['CS02', $ab['12-14 Years'],$rb['5-1 Kyu'],   null, null,    '12-14 Years / 5-1 Kyu'],
            ['CS03', $ab['12-14 Years'],$rb['Black Belt'], null, null,   '12-14 Years / Black Belt'],
            ['CS04', $ab['15+ Years'],  $rb['10-6 Kyu'],  null, 'M',     '15+ Years / 10-6 Kyu / Male'],
            ['CS05', $ab['15+ Years'],  $rb['10-6 Kyu'],  null, 'F',     '15+ Years / 10-6 Kyu / Female'],
            ['CS06', $ab['15+ Years'],  $rb['5-1 Kyu'],   null, 'M',     '15+ Years / 5-1 Kyu / Male'],
            ['CS07', $ab['15+ Years'],  $rb['5-1 Kyu'],   null, 'F',     '15+ Years / 5-1 Kyu / Female'],
            ['CS08', $ab['15+ Years'],  $rb['Black Belt'], null, 'M',    '15+ Years / Black Belt / Male'],
            ['CS09', $ab['15+ Years'],  $rb['Black Belt'], null, 'F',    '15+ Years / Black Belt / Female'],
        ];
        $this->createDivisions($events['Continuous Sparring'], $csDivs);

        // Sumo (16) — weight_sex
        $suDivs = [];
        $suNum  = 1;
        foreach (array_values($wc) as $weightClass) {
            foreach (['M' => 'Male', 'F' => 'Female'] as $sexCode => $sexLabel) {
                $code = 'SU' . str_pad($suNum++, 2, '0', STR_PAD_LEFT);
                $suDivs[] = [$code, null, null, $weightClass, $sexCode, $weightClass->label . ' / ' . $sexLabel];
            }
        }
        $this->createDivisions($events['Sumo'], $suDivs);

        $this->command?->info('LFP Round 1 2026: 7 events, 72 divisions created.');

        // --- Enrol test competitors ---
        $this->enrolTestCompetitors($competition, $events);
    }

    /**
     * @param  array<array{string, ?AgeBand, ?RankBand, ?WeightClass, ?string, string}>  $rows
     *   Each row: [code_prefix, ageBand|null, rankBand|null, weightClass|null, sex|null, label]
     */
    private function createDivisions(CompetitionEvent $event, array $rows): void
    {
        $order = 1;
        foreach ($rows as [$code, $ageBand, $rankBand, $weightClass, $sex, $label]) {
            Division::updateOrCreate(
                [
                    'competition_event_id' => $event->id,
                    'age_band_id'          => $ageBand?->id,
                    'rank_band_id'         => $rankBand?->id,
                    'weight_class_id'      => $weightClass?->id,
                    'sex'                  => $sex,
                ],
                [
                    'code'          => $code,
                    'label'         => $label,
                    'location_label' => $event->location_label,
                    'running_order' => $order++,
                    'status'        => 'pending',
                ]
            );
        }
    }

    private function enrolTestCompetitors(Competition $competition, array $events): void
    {
        $service = app(EnrolmentService::class);

        $enrolments = [
            'alice@example.com'      => ['events' => ['Kata', 'Point Sparring', 'Yakusuko'],  'rank_type' => 'kyu', 'rank_kyu' => 5,  'weight_kg' => 52.0, 'dojo_type' => 'lfp', 'dojo_name' => 'LFP Melbourne'],
            'bob@example.com'        => ['events' => ['Kata', 'Sumo', 'Continuous Sparring'], 'rank_type' => 'kyu', 'rank_kyu' => 3,  'weight_kg' => 78.0, 'dojo_type' => 'lfp', 'dojo_name' => 'LFP Melbourne'],
            'cara@example.com'       => ['events' => ['Kata', 'Semi Contact', 'Point Sparring'], 'rank_type' => 'dan', 'rank_dan' => 1, 'weight_kg' => 58.0, 'dojo_type' => 'lfp', 'dojo_name' => 'LFP Melbourne'],
            'dan@example.com'        => ['events' => ['Kata', 'Point Sparring', 'Yakusuko'],  'rank_type' => 'kyu', 'rank_kyu' => 8,  'weight_kg' => 65.0, 'dojo_type' => 'lfp', 'dojo_name' => 'LFP Melbourne'],
            'competitor@example.com' => ['events' => ['Kata', 'Sumo', 'Point Sparring'],      'rank_type' => 'kyu', 'rank_kyu' => 7,  'weight_kg' => 70.0, 'dojo_type' => 'lfp', 'dojo_name' => 'LFP Melbourne'],
        ];

        foreach ($enrolments as $email => $cfg) {
            $user = User::where('email', $email)->first();
            if (! $user) {
                continue;
            }

            $eventIds = collect($cfg['events'])
                ->map(fn ($name) => $events[$name]?->id ?? null)
                ->filter()
                ->values()
                ->toArray();

            if (empty($eventIds)) {
                continue;
            }

            $entryDetails = [
                'rank_type'  => $cfg['rank_type'],
                'rank_kyu'   => $cfg['rank_kyu'] ?? null,
                'rank_dan'   => $cfg['rank_dan'] ?? null,
                'weight_kg'  => $cfg['weight_kg'],
                'dojo_type'  => $cfg['dojo_type'],
                'dojo_name'  => $cfg['dojo_name'] ?? null,
                'guest_style' => $cfg['guest_style'] ?? null,
            ];

            try {
                $service->enrol($user, $competition, $eventIds, [], $entryDetails);
            } catch (\Exception) {
                // Already enrolled — skip
            }
        }

        $this->command?->info('Test competitors enrolled in LFP Round 1 2026.');
    }
}
