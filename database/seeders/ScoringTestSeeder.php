<?php

namespace Database\Seeders;

use App\Models\Competition;
use App\Models\CompetitionEvent;
use App\Models\CompetitorProfile;
use App\Models\Division;
use App\Models\Enrolment;
use App\Models\EnrolmentEvent;
use App\Models\EventType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ScoringTestSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure round-robin flag is set on win_loss event types
        EventType::where('scoring_method', 'win_loss')->update(['is_round_robin' => true]);

        // Use the most recent competition, or the running one
        $competition = Competition::orderByDesc('competition_date')->first();
        if (! $competition) {
            $this->command->error('No competition found. Run LfpRound1Seeder first.');
            return;
        }

        // Force competition to running so scoring works
        $competition->update(['status' => 'running']);
        $this->command->info("Using competition: {$competition->name}");

        // Fake competitors — 20 people across mixed genders/ages
        $people = [
            ['first_name' => 'Aiden',   'surname' => 'Abbott',   'gender' => 'M', 'dob' => '1995-02-10', 'email' => 'aiden.st@example.com'],
            ['first_name' => 'Brianna', 'surname' => 'Baker',    'gender' => 'F', 'dob' => '1998-07-14', 'email' => 'brianna.st@example.com'],
            ['first_name' => 'Caleb',   'surname' => 'Castro',   'gender' => 'M', 'dob' => '1992-11-30', 'email' => 'caleb.st@example.com'],
            ['first_name' => 'Diana',   'surname' => 'Dean',     'gender' => 'F', 'dob' => '2001-04-22', 'email' => 'diana.st@example.com'],
            ['first_name' => 'Ethan',   'surname' => 'Ellis',    'gender' => 'M', 'dob' => '1997-09-05', 'email' => 'ethan.st@example.com'],
            ['first_name' => 'Fiona',   'surname' => 'Flynn',    'gender' => 'F', 'dob' => '1994-12-18', 'email' => 'fiona.st@example.com'],
            ['first_name' => 'George',  'surname' => 'Grant',    'gender' => 'M', 'dob' => '2000-06-03', 'email' => 'george.st@example.com'],
            ['first_name' => 'Hannah',  'surname' => 'Hayes',    'gender' => 'F', 'dob' => '1999-01-27', 'email' => 'hannah.st@example.com'],
            ['first_name' => 'Ivan',    'surname' => 'Ingram',   'gender' => 'M', 'dob' => '1996-08-15', 'email' => 'ivan.st@example.com'],
            ['first_name' => 'Julia',   'surname' => 'Jenkins',  'gender' => 'F', 'dob' => '2002-03-09', 'email' => 'julia.st@example.com'],
            ['first_name' => 'Kyle',    'surname' => 'Knight',   'gender' => 'M', 'dob' => '1993-05-21', 'email' => 'kyle.st@example.com'],
            ['first_name' => 'Laura',   'surname' => 'Lane',     'gender' => 'F', 'dob' => '2003-10-11', 'email' => 'laura.st@example.com'],
            ['first_name' => 'Marcus',  'surname' => 'Mason',    'gender' => 'M', 'dob' => '1991-07-07', 'email' => 'marcus.st@example.com'],
            ['first_name' => 'Nina',    'surname' => 'Nash',     'gender' => 'F', 'dob' => '2000-02-28', 'email' => 'nina.st@example.com'],
            ['first_name' => 'Oscar',   'surname' => 'Owen',     'gender' => 'M', 'dob' => '1998-12-01', 'email' => 'oscar.st@example.com'],
        ];

        $users = collect();
        foreach ($people as $p) {
            $user = User::updateOrCreate(
                ['email' => $p['email']],
                [
                    'name'              => $p['first_name'] . ' ' . $p['surname'],
                    'password'          => Hash::make('password'),
                    'email_verified_at' => now(),
                    'status'            => 'active',
                ]
            );
            $user->syncRoles(['user']);
            CompetitorProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'first_name'       => $p['first_name'],
                    'surname'          => $p['surname'],
                    'date_of_birth'    => $p['dob'],
                    'gender'           => $p['gender'],
                    'phone'            => '04001' . str_pad($user->id, 5, '0', STR_PAD_LEFT),
                    'profile_complete' => true,
                ]
            );
            $users->push($user);
        }
        $this->command->info("Created/updated {$users->count()} test competitors.");

        // Divisions to populate — pick a judge-scored and a round-robin event
        $targets = [
            // [event_type_name, division_code, competitor_indices, status]
            ['Kata',               'KA07', [0, 1, 2, 3, 4, 5]],       // 6 competitors for judge scoring / sudden death
            ['Tile Breaking',      'TB06', [6, 7, 8, 9, 10, 11]],     // 6 more for another judge event
            ['Semi Contact',       'SC07', [0, 2, 4, 6, 8, 10, 12, 14]], // 8 for round robin
            ['Continuous Sparring','CS07', [1, 3, 5, 7, 9, 11, 13]],  // 7 for round robin
        ];

        // CS07 might not exist in every seeder run, so handle gracefully
        $divisionCodes = ['KA07', 'TB06', 'SC07'];
        foreach ($targets as [$eventName, $divCode, $indices]) {
            $et = EventType::where('name', $eventName)->first();
            if (! $et) continue;

            $compEvent = CompetitionEvent::where('competition_id', $competition->id)
                ->where('event_type_id', $et->id)
                ->first();
            if (! $compEvent) {
                $this->command->warn("No competition event found for {$eventName} — skipping.");
                continue;
            }
            $compEvent->update(['status' => 'running']);

            $division = Division::where('competition_event_id', $compEvent->id)
                ->where('code', $divCode)
                ->first();
            if (! $division) {
                // Try the first available division for this event
                $division = Division::where('competition_event_id', $compEvent->id)->first();
            }
            if (! $division) {
                $this->command->warn("No division found for {$eventName} / {$divCode} — skipping.");
                continue;
            }
            $division->update(['status' => 'assigned']);

            $enrolled = 0;
            foreach ($indices as $idx) {
                $user = $users->get($idx);
                if (! $user) continue;

                $enrolment = Enrolment::updateOrCreate(
                    ['competition_id' => $competition->id, 'competitor_id' => $user->id],
                    [
                        'enrolled_at'    => now(),
                        'is_late'        => false,
                        'fee_calculated' => 0,
                        'status'         => 'checked_in',
                    ]
                );

                EnrolmentEvent::updateOrCreate(
                    ['enrolment_id' => $enrolment->id, 'competition_event_id' => $compEvent->id],
                    ['division_id' => $division->id, 'removed' => false, 'yakusuko_complete' => false]
                );
                $enrolled++;
            }

            $this->command->info("  {$eventName} / {$division->code}: {$enrolled} competitors checked in.");
        }

        $this->command->info('Done. Go to Scoring, select this competition, and test each division.');
    }
}
