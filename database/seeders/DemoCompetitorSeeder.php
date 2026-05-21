<?php

namespace Database\Seeders;

use App\Models\Competition;
use App\Models\CompetitorProfile;
use App\Models\Dojo;
use App\Models\Organisation;
use App\Models\OrganisationMembership;
use App\Models\User;
use App\Services\EnrolmentService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

class DemoCompetitorSeeder extends Seeder
{
    public function __construct(private EnrolmentService $enrolmentService) {}

    public function run(): void
    {
        Notification::fake();

        $org = Organisation::where('name', 'like', '%Demo%')->firstOrFail();

        $competition = Competition::where('organisation_id', $org->id)
            ->orderByDesc('competition_date')
            ->firstOrFail();

        $this->command->info("Organisation : {$org->name}");
        $this->command->info("Competition  : {$competition->name} (status: {$competition->status})");

        $eventIds = $competition->competitionEvents()
            ->where('requires_partner', false)
            ->pluck('id')
            ->toArray();

        if (empty($eventIds)) {
            $this->command->warn('No non-partner events found — aborting.');
            return;
        }

        $eventNames = $competition->competitionEvents()
            ->whereIn('id', $eventIds)
            ->pluck('name')
            ->join(', ');
        $this->command->info("Events       : {$eventNames}");

        $divisionCount = $competition->allDivisions()->count();
        if ($divisionCount === 0) {
            $this->command->warn('No divisions defined for this competition — build divisions first, then re-run.');
            return;
        }
        $this->command->info("Divisions    : {$divisionCount}");

        $dojoNames = Dojo::where('organisation_id', $org->id)
            ->where('is_active', true)
            ->pluck('name')
            ->toArray();

        if (empty($dojoNames)) {
            $dojoNames = ['Demo Dojo'];
        }

        $profiles = $this->buildProfiles();
        $created  = 0;
        $skipped  = 0;

        foreach ($profiles as $i => $data) {
            $email = "demo.{$i}@nodomain.invalid";

            $user = User::where('email', $email)->first();

            if ($user) {
                // User exists — find their profile and skip if already enrolled
                $profile = CompetitorProfile::where('owner_user_id', $user->id)->first();
                if (! $profile) {
                    $this->command->warn("  [{$i}] User exists but no profile — skipping.");
                    $skipped++;
                    continue;
                }
                $alreadyEnrolled = $profile->enrolments()
                    ->where('competition_id', $competition->id)
                    ->exists();
                if ($alreadyEnrolled) {
                    $skipped++;
                    continue;
                }
                // Profile exists but no enrolment — fall through to enrol
            } else {
                $user = User::create([
                    'organisation_id'   => $org->id,
                    'email'             => $email,
                    'status'            => 'active',
                    'password'          => Hash::make('Demo1234!'),
                    'email_verified_at' => now(),
                ]);

                $user->assignRole('user');

                OrganisationMembership::create([
                    'user_id'         => $user->id,
                    'organisation_id' => $org->id,
                    'role'            => 'competitor',
                    'status'          => 'active',
                    'joined_at'       => now(),
                ]);

                $profile = CompetitorProfile::create([
                    'organisation_id'  => $org->id,
                    'owner_user_id'    => $user->id,
                    'user_id'          => $user->id,
                    'profile_type'     => 'self',
                    'first_name'       => $data['first_name'],
                    'surname'          => $data['surname'],
                    'date_of_birth'    => $data['dob'],
                    'gender'           => $data['gender'],
                    'profile_complete' => true,
                    'is_active'        => true,
                ]);
            }

            $entryDetails = [
                'rank_type'         => $data['rank_type'],
                'rank_kyu'          => $data['rank_kyu'],
                'rank_dan'          => $data['rank_dan'],
                'experience_years'  => $data['experience_years'],
                'experience_months' => $data['experience_months'],
                'weight_kg'         => $data['weight_kg'],
                'dojo_type'         => 'lfp',
                'dojo_name'         => $dojoNames[$i % count($dojoNames)],
            ];

            try {
                $enrolment = $this->enrolmentService->enrol(
                    $profile,
                    $competition,
                    $eventIds,
                    [],
                    $entryDetails,
                    false,
                );

                $assigned = $enrolment->activeEvents()->whereNotNull('division_id')->count();
                $total    = $enrolment->activeEvents()->count();

                $rankDesc = match ($data['rank_type']) {
                    'dan'        => "{$data['rank_dan']} dan",
                    'kyu'        => "{$data['rank_kyu']} kyu",
                    'experience' => ($data['experience_years'] ?? 0) . 'y ' . ($data['experience_months'] ?? 0) . 'm exp',
                    default      => '—',
                };

                $this->command->line(sprintf(
                    '  [%2d] %-20s age %2d %s  %-12s  %5.1f kg  %d/%d events',
                    $i + 1,
                    $data['first_name'] . ' ' . $data['surname'],
                    $data['age'],
                    $data['gender'],
                    $rankDesc,
                    $data['weight_kg'],
                    $assigned,
                    $total,
                ));

                $created++;
            } catch (\Throwable $e) {
                $this->command->error("  FAILED {$data['first_name']} {$data['surname']}: " . $e->getMessage());
            }
        }

        $suffix = $skipped > 0 ? ", {$skipped} already existed (skipped)" : '';
        $this->command->info("Done: {$created} competitors created{$suffix}.");
    }

    private function buildProfiles(): array
    {
        $maleNames = [
            'James', 'Oliver', 'William', 'Ethan', 'Noah', 'Lucas', 'Mason', 'Logan', 'Aiden', 'Henry',
            'Jack', 'Daniel', 'Owen', 'Samuel', 'Ryan', 'Dylan', 'Tyler', 'Caleb', 'Nathan', 'Alex',
            'Marcus', 'Sean', 'Patrick', 'Jordan', 'Liam',
        ];
        $femaleNames = [
            'Emma', 'Olivia', 'Sophie', 'Isla', 'Charlotte', 'Amelia', 'Ava', 'Mia', 'Grace', 'Lily',
            'Zoe', 'Chloe', 'Emily', 'Hannah', 'Ella', 'Scarlett', 'Madison', 'Aria', 'Leah', 'Nora',
            'Ruby', 'Sienna', 'Abby', 'Jasmine', 'Claire',
        ];
        $surnames = [
            'Smith', 'Jones', 'Williams', 'Brown', 'Wilson', 'Taylor', 'Johnson', 'White', 'Martin',
            'Anderson', 'Thompson', 'Garcia', 'Robinson', 'Clark', 'Rodriguez', 'Lewis', 'Lee', 'Walker',
            'Hall', 'Allen', 'Young', 'King', 'Wright', 'Scott', 'Torres', 'Nguyen', 'Hill', 'Flores',
            'Green', 'Adams', 'Nelson', 'Baker', 'Rivera', 'Campbell', 'Mitchell', 'Carter', 'Roberts',
            'Turner', 'Phillips', 'Evans', 'Parker', 'Edwards', 'Collins', 'Stewart', 'Morris', 'Sanchez',
            'Rogers', 'Reed', 'Cook', 'Morgan', 'Cooper',
        ];

        // [age, gender, rank_type, rank_value, weight_kg]
        // rank_value: int for kyu/dan; ['years' => N, 'months' => N] for experience
        $specs = [
            // Young children 8–12 (beginners)
            [ 9, 'F', 'experience', ['years' => 1, 'months' => 0], 32.0],
            [10, 'M', 'kyu',        9,                              38.0],
            [ 8, 'F', 'experience', ['years' => 0, 'months' => 8], 28.0],
            [11, 'M', 'kyu',        8,                              42.0],
            [10, 'F', 'kyu',        9,                              35.0],
            [12, 'M', 'kyu',        7,                              48.0],
            [ 9, 'M', 'experience', ['years' => 1, 'months' => 6], 36.0],
            [12, 'F', 'kyu',        8,                              44.0],

            // Teens 13–15
            [13, 'M', 'kyu',  7, 55.0],
            [14, 'F', 'kyu',  8, 50.0],
            [15, 'M', 'kyu',  6, 62.0],
            [13, 'F', 'kyu',  7, 48.0],
            [14, 'M', 'kyu',  5, 58.0],
            [15, 'F', 'kyu',  6, 55.0],
            [13, 'M', 'kyu',  8, 52.0],

            // Older teens 16–17
            [16, 'F', 'kyu',  5, 60.0],
            [17, 'M', 'kyu',  4, 70.0],
            [16, 'M', 'kyu',  3, 68.0],
            [17, 'F', 'kyu',  4, 58.0],
            [16, 'F', 'kyu',  3, 63.0],

            // Young adults 18–25
            [18, 'M', 'kyu',  2, 75.0],
            [20, 'F', 'kyu',  3, 62.0],
            [22, 'M', 'kyu',  1, 82.0],
            [19, 'F', 'kyu',  2, 65.0],
            [24, 'M', 'dan',  1, 88.0],
            [21, 'F', 'kyu',  1, 68.0],
            [25, 'M', 'dan',  1, 85.0],
            [23, 'F', 'dan',  1, 70.0],
            [18, 'M', 'kyu',  3, 73.0],
            [20, 'F', 'kyu',  4, 60.0],

            // Adults 26–35
            [28, 'M', 'dan',  2,  92.0],
            [30, 'F', 'dan',  1,  72.0],
            [26, 'M', 'kyu',  1,  87.0],
            [33, 'F', 'dan',  2,  75.0],
            [29, 'M', 'dan',  1,  90.0],
            [32, 'F', 'kyu',  1,  68.0],
            [35, 'M', 'dan',  3,  95.0],
            [27, 'F', 'dan',  1,  65.0],

            // Mature adults 36–50
            [38, 'M', 'dan',  2,  98.0],
            [42, 'F', 'dan',  2,  78.0],
            [45, 'M', 'dan',  3, 102.0],
            [40, 'F', 'dan',  1,  73.0],
            [48, 'M', 'dan',  2,  95.0],
            [36, 'F', 'kyu',  1,  70.0],
            [50, 'M', 'dan',  4, 100.0],

            // Seniors 51–65
            [55, 'M', 'dan',  3,  90.0],
            [58, 'F', 'dan',  2,  76.0],
            [52, 'M', 'dan',  4,  92.0],
            [62, 'F', 'dan',  3,  72.0],
            [60, 'M', 'dan',  5,  88.0],
        ];

        $mIdx = $fIdx = $sIdx = 0;
        $profiles = [];

        foreach ($specs as $i => [$age, $gender, $rankType, $rankVal, $weight]) {
            $firstName = $gender === 'M'
                ? $maleNames[$mIdx++ % count($maleNames)]
                : $femaleNames[$fIdx++ % count($femaleNames)];

            $surname = $surnames[$sIdx++ % count($surnames)];

            // Deterministic DOB: put birthday at least one month before today so age() is accurate
            $dob = Carbon::now()->subYears($age)->subMonths(($i % 11) + 1);

            $profiles[] = [
                'first_name'        => $firstName,
                'surname'           => $surname,
                'gender'            => $gender,
                'dob'               => $dob->format('Y-m-d'),
                'age'               => $age,
                'weight_kg'         => $weight,
                'rank_type'         => $rankType,
                'rank_kyu'          => $rankType === 'kyu' ? $rankVal : null,
                'rank_dan'          => $rankType === 'dan' ? $rankVal : null,
                'experience_years'  => $rankType === 'experience' ? ($rankVal['years'] ?? 0) : null,
                'experience_months' => $rankType === 'experience' ? ($rankVal['months'] ?? 0) : null,
            ];
        }

        return $profiles;
    }
}
