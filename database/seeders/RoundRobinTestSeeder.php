<?php

namespace Database\Seeders;

use App\Models\Competition;
use App\Models\CompetitionEvent;
use App\Models\CompetitorProfile;
use App\Models\Division;
use App\Models\Enrolment;
use App\Models\EnrolmentEvent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoundRobinTestSeeder extends Seeder
{
    public function run(): void
    {
        // Use existing competition or create one
        $competition = Competition::where('status', 'running')->first()
            ?? Competition::orderByDesc('competition_date')->first()
            ?? Competition::create([
                'name'                 => 'Round Robin Test Competition',
                'competition_date'     => now()->toDateString(),
                'start_time'           => '09:00:00',
                'status'               => 'running',
                'fee_first_event'      => 0,
                'fee_additional_event' => 0,
                'late_surcharge'       => 0,
            ]);

        if ($competition->status !== 'running') {
            $competition->update(['status' => 'running']);
        }

        $this->command->info("Using competition: {$competition->name}");

        // Get or create a Semi Contact competition event (round robin, win/loss)
        $compEvent = CompetitionEvent::firstOrCreate(
            ['competition_id' => $competition->id, 'name' => 'Semi Contact'],
            [
                'status'                => 'running',
                'running_order'         => 1,
                'scoring_method'        => 'win_loss',
                'tournament_format'     => 'round_robin',
                'division_filter'       => 'age_sex',
                'requires_partner'      => false,
                'requires_weight_check' => false,
                'judge_count'           => 0,
            ]
        );
        $compEvent->update(['status' => 'running']);

        // Get or create a single Open division
        $division = Division::firstOrCreate(
            ['competition_event_id' => $compEvent->id, 'code' => 'RR-OPEN'],
            [
                'label'         => 'Open (Round Robin Test)',
                'status'        => 'assigned',
                'running_order' => 1,
            ]
        );

        // Create 10 test competitors
        $names = [
            ['first_name' => 'Alex',    'surname' => 'Anderson', 'gender' => 'M', 'dob' => '1998-03-15'],
            ['first_name' => 'Blake',   'surname' => 'Brown',    'gender' => 'M', 'dob' => '2000-07-22'],
            ['first_name' => 'Casey',   'surname' => 'Clark',    'gender' => 'F', 'dob' => '1997-11-08'],
            ['first_name' => 'Devon',   'surname' => 'Davis',    'gender' => 'M', 'dob' => '1995-02-14'],
            ['first_name' => 'Emery',   'surname' => 'Evans',    'gender' => 'F', 'dob' => '2001-09-30'],
            ['first_name' => 'Finley',  'surname' => 'Foster',   'gender' => 'M', 'dob' => '1999-05-17'],
            ['first_name' => 'Gray',    'surname' => 'Green',    'gender' => 'M', 'dob' => '1996-12-03'],
            ['first_name' => 'Harper',  'surname' => 'Hill',     'gender' => 'F', 'dob' => '2002-04-25'],
            ['first_name' => 'Indigo',  'surname' => 'Irving',   'gender' => 'F', 'dob' => '1994-08-19'],
            ['first_name' => 'Jordan',  'surname' => 'James',    'gender' => 'M', 'dob' => '2003-01-07'],
        ];

        $created = 0;
        foreach ($names as $i => $n) {
            $email = strtolower($n['first_name']) . '.rr@example.com';

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name'               => $n['first_name'] . ' ' . $n['surname'],
                    'password'           => Hash::make('password'),
                    'email_verified_at'  => now(),
                    'status'             => 'active',
                ]
            );
            $user->syncRoles(['competitor']);

            CompetitorProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'first_name'       => $n['first_name'],
                    'surname'          => $n['surname'],
                    'date_of_birth'    => $n['dob'],
                    'gender'           => $n['gender'],
                    'phone'            => '04000' . str_pad($i + 10, 5, '0', STR_PAD_LEFT),
                    'profile_complete' => true,
                ]
            );

            // Enrolment (checked in)
            $enrolment = Enrolment::updateOrCreate(
                ['competition_id' => $competition->id, 'competitor_id' => $user->id],
                [
                    'enrolled_at'     => now(),
                    'is_late'         => false,
                    'fee_calculated'  => 0,
                    'status'          => 'checked_in',
                ]
            );

            // Enrolment event in the round robin division
            EnrolmentEvent::updateOrCreate(
                ['enrolment_id' => $enrolment->id, 'competition_event_id' => $compEvent->id],
                [
                    'division_id'      => $division->id,
                    'removed'          => false,
                    'yakusuko_complete' => false,
                ]
            );

            $created++;
        }

        $this->command->info("Created {$created} competitors checked in to division '{$division->code}' ({$compEvent->name}).");
        $this->command->info('Go to Scoring → select the competition → select division RR-OPEN → Begin Scoring → Generate matches.');
    }
}
