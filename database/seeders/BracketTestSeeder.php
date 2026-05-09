<?php

namespace Database\Seeders;

use App\Models\Competition;
use App\Services\BracketService;
use App\Models\CompetitionEvent;
use App\Models\CompetitorProfile;
use App\Models\Division;
use App\Models\Enrolment;
use App\Models\EnrolmentEvent;
use App\Models\Result;
use App\Models\RoundRobinMatch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BracketTestSeeder extends Seeder
{
    // Produces two divisions: 8-person (clean bracket) and 13-person (byes at bracket size 16)
    private array $competitors = [
        // --- 8-person division ---
        ['first_name' => 'Aiden',   'surname' => 'Blake',    'gender' => 'M', 'dob' => '2001-03-12'],
        ['first_name' => 'Brooke',  'surname' => 'Carter',   'gender' => 'F', 'dob' => '2000-07-24'],
        ['first_name' => 'Connor',  'surname' => 'Dixon',    'gender' => 'M', 'dob' => '1999-11-05'],
        ['first_name' => 'Dana',    'surname' => 'Ellis',    'gender' => 'F', 'dob' => '2002-01-30'],
        ['first_name' => 'Ethan',   'surname' => 'Flynn',    'gender' => 'M', 'dob' => '1998-09-14'],
        ['first_name' => 'Freya',   'surname' => 'Grant',    'gender' => 'F', 'dob' => '2001-06-08'],
        ['first_name' => 'George',  'surname' => 'Hayes',    'gender' => 'M', 'dob' => '2000-04-19'],
        ['first_name' => 'Hana',    'surname' => 'Irving',   'gender' => 'F', 'dob' => '1999-12-27'],
        // --- 13-person division (bracket size 16, 3 byes) ---
        ['first_name' => 'Ivan',    'surname' => 'Jensen',   'gender' => 'M', 'dob' => '2003-02-11'],
        ['first_name' => 'Jade',    'surname' => 'King',     'gender' => 'F', 'dob' => '1997-08-03'],
        ['first_name' => 'Kyle',    'surname' => 'Lane',     'gender' => 'M', 'dob' => '2002-05-22'],
        ['first_name' => 'Luna',    'surname' => 'Moore',    'gender' => 'F', 'dob' => '2000-10-16'],
        ['first_name' => 'Mason',   'surname' => 'Nash',     'gender' => 'M', 'dob' => '1998-03-07'],
        ['first_name' => 'Nora',    'surname' => 'Owen',     'gender' => 'F', 'dob' => '2001-09-29'],
        ['first_name' => 'Oscar',   'surname' => 'Park',     'gender' => 'M', 'dob' => '1999-07-13'],
        ['first_name' => 'Piper',   'surname' => 'Quinn',    'gender' => 'F', 'dob' => '2003-04-02'],
        ['first_name' => 'Remy',    'surname' => 'Reed',     'gender' => 'M', 'dob' => '2000-11-18'],
        ['first_name' => 'Sara',    'surname' => 'Stone',    'gender' => 'F', 'dob' => '1998-06-25'],
        ['first_name' => 'Tyler',   'surname' => 'Turner',   'gender' => 'M', 'dob' => '2002-08-09'],
        ['first_name' => 'Uma',     'surname' => 'Vance',    'gender' => 'F', 'dob' => '2001-02-14'],
        ['first_name' => 'Victor',  'surname' => 'Ward',     'gender' => 'M', 'dob' => '1997-12-01'],
    ];

    public function run(): void
    {
        $competition = Competition::orderByDesc('competition_date')->first();

        if (! $competition) {
            $this->command->error('No competition found. Run LFPCompetitionSeeder first.');
            return;
        }

        $this->command->info("Using competition: {$competition->name}");

        // Set the competition to running so the scoring page finds it
        $competition->update(['status' => 'running']);

        // Find the Point Sparring event or create a standalone bracket test event
        $event = $competition->competitionEvents()
            ->where('tournament_format', 'double_elimination')
            ->orWhere('tournament_format', 'single_elimination')
            ->orderByRaw("CASE tournament_format WHEN 'double_elimination' THEN 0 ELSE 1 END")
            ->first();

        if (! $event) {
            $event = CompetitionEvent::create([
                'competition_id'        => $competition->id,
                'name'                  => 'Point Sparring',
                'event_code'            => 'PS',
                'tournament_format'     => 'double_elimination',
                'scoring_method'        => 'win_loss',
                'division_filter'       => 'age_rank_sex',
                'requires_partner'      => false,
                'requires_weight_check' => false,
                'judge_count'           => 0,
                'status'                => 'running',
                'running_order'         => 99,
            ]);
        } else {
            $event->update(['status' => 'running']);
        }

        $this->command->info("Using event: {$event->name} ({$event->tournament_format})");

        // Clear any existing bracket test data for these two divisions
        foreach (['BT-08', 'BT-13'] as $code) {
            $div = Division::where('competition_event_id', $event->id)->where('code', $code)->first();
            if ($div) {
                RoundRobinMatch::where('division_id', $div->id)->delete();
                $eeIds = EnrolmentEvent::where('division_id', $div->id)->pluck('id');
                Result::whereIn('enrolment_event_id', $eeIds)->delete();
                EnrolmentEvent::where('division_id', $div->id)->delete();
                $div->delete();
            }
        }

        // Also remove test enrolments from previous runs
        $testEmails = collect($this->competitors)
            ->map(fn ($c) => strtolower($c['first_name']) . '.bt@example.com')
            ->all();
        $testUserIds = User::whereIn('email', $testEmails)->pluck('id');
        Enrolment::where('competition_id', $competition->id)
            ->whereIn('competitor_id', $testUserIds)
            ->delete();

        // Create the two test divisions
        $div8 = Division::create([
            'competition_event_id' => $event->id,
            'code'                 => 'BT-08',
            'label'                => 'Bracket Test — 8 Competitors',
            'status'               => 'assigned',
            'running_order'        => 900,
        ]);

        $div13 = Division::create([
            'competition_event_id' => $event->id,
            'code'                 => 'BT-13',
            'label'                => 'Bracket Test — 13 Competitors (with byes)',
            'status'               => 'assigned',
            'running_order'        => 901,
        ]);

        // Create competitors and enrol them
        $slot = 0;
        foreach ($this->competitors as $i => $data) {
            $email = strtolower($data['first_name']) . '.bt@example.com';

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name'              => $data['first_name'] . ' ' . $data['surname'],
                    'password'          => Hash::make('password'),
                    'email_verified_at' => now(),
                    'status'            => 'active',
                ]
            );
            $user->syncRoles(['competitor']);

            CompetitorProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'first_name'       => $data['first_name'],
                    'surname'          => $data['surname'],
                    'date_of_birth'    => $data['dob'],
                    'gender'           => $data['gender'],
                    'phone'            => '04100' . str_pad($i + 1, 5, '0', STR_PAD_LEFT),
                    'profile_complete' => true,
                ]
            );

            $enrolment = Enrolment::create([
                'competition_id'  => $competition->id,
                'competitor_id'   => $user->id,
                'enrolled_at'     => now(),
                'is_late'         => false,
                'fee_calculated'  => 0,
                'status'          => 'checked_in',
            ]);

            // First 8 go into div8, all 21 go into div13
            $divisions = $i < 8 ? [$div8, $div13] : [$div13];

            foreach ($divisions as $div) {
                EnrolmentEvent::create([
                    'enrolment_id'        => $enrolment->id,
                    'competition_event_id' => $event->id,
                    'division_id'         => $div->id,
                    'removed'             => false,
                    'yakusuko_complete'   => false,
                ]);
            }

            $slot++;
        }

        $this->command->info('Created ' . count($this->competitors) . ' test competitors.');

        // Generate round-1 brackets for both divisions
        $svc = app(BracketService::class);

        foreach ([[$div8, 'BT-08'], [$div13, 'BT-13']] as [$div, $code]) {
            $div->load('competitionEvent');
            $ees = EnrolmentEvent::where('division_id', $div->id)
                ->where('removed', false)
                ->with('enrolment.competitor.competitorProfile')
                ->get()
                ->sortBy(fn ($ee) => ($ee->enrolment->competitor?->competitorProfile?->first_name ?? '')
                    . ' ' . ($ee->enrolment->competitor?->competitorProfile?->surname ?? ''))
                ->values();

            $svc->generate($div, $ees);
            $matchCount = RoundRobinMatch::where('division_id', $div->id)->count();
            $this->command->info("  {$code}: {$ees->count()} competitors, {$matchCount} matches generated.");
        }

        $this->command->info('Go to Scoring → select the competition → select BT-08 or BT-13 → Begin Scoring → bracket is ready.');
    }
}
