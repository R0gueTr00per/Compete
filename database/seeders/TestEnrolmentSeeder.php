<?php

namespace Database\Seeders;

use App\Models\Competition;
use App\Models\CompetitorProfile;
use App\Models\EnrolmentEvent;
use App\Models\Rank;
use App\Services\EnrolmentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class TestEnrolmentSeeder extends Seeder
{
    // Rank / weight / dojo keyed by profile full_name — fallback cycles through $defaults.
    private array $byName = [
        'Alice Chen'       => ['rank' => '5th Kyu',  'weight_kg' => 58.0, 'dojo_name' => 'Scoresby'],
        'Bob Smith'        => ['rank' => '2nd Kyu',  'weight_kg' => 75.0, 'dojo_name' => 'Honbu'],
        'Cara Jones'       => ['rank' => '8th Kyu',  'weight_kg' => 50.0, 'dojo_name' => 'Wangaratta'],
        'Dan Wu'           => ['rank' => '1st Dan',  'weight_kg' => 68.0, 'dojo_name' => 'Scoresby'],
        'Test Competitor'  => ['rank' => '6th Kyu',  'weight_kg' => 82.0, 'dojo_name' => 'Honbu'],
        'Brad Gouldson'    => ['rank' => '3rd Dan',  'weight_kg' => 88.0, 'dojo_name' => 'Honbu'],
        'Lily Gouldson'    => ['rank' => '9th Kyu',  'weight_kg' => 47.0, 'dojo_name' => 'Honbu'],
        'Jasmine Gouldson' => ['rank' => '3rd Kyu',  'weight_kg' => 55.0, 'dojo_name' => 'Honbu'],
        'Ronald Donald'    => ['rank' => '4th Dan',  'weight_kg' => 92.0, 'dojo_name' => 'Wangaratta'],
    ];

    private array $defaults = [
        ['rank' => '8th Kyu',  'weight_kg' => 42.0, 'dojo_name' => 'Honbu'],
        ['rank' => '6th Kyu',  'weight_kg' => 55.0, 'dojo_name' => 'Scoresby'],
        ['rank' => '3rd Kyu',  'weight_kg' => 63.0, 'dojo_name' => 'Honbu'],
        ['rank' => '2nd Dan',  'weight_kg' => 78.0, 'dojo_name' => 'Wangaratta'],
    ];

    public function run(): void
    {
        Notification::fake();

        $competition = Competition::orderByDesc('competition_date')->first();

        if (! $competition) {
            $this->command->error('No competition found.');
            return;
        }

        $this->command->info("Competition: {$competition->name} (status: {$competition->status})");

        // Clear existing enrolments for this competition
        DB::statement('PRAGMA foreign_keys = OFF');
        $enrolmentIds = $competition->enrolments()->pluck('id');
        $eeIds = DB::table('enrolment_events')->whereIn('enrolment_id', $enrolmentIds)->pluck('id');
        DB::table('round_robin_matches')
            ->whereIn('division_id', $competition->competitionEvents()
                ->join('divisions', 'competition_events.id', '=', 'divisions.competition_event_id')
                ->pluck('divisions.id'))
            ->delete();
        DB::table('results')->whereIn('enrolment_event_id', $eeIds)->delete();
        DB::table('enrolment_events')->whereIn('id', $eeIds)->delete();
        DB::table('enrolments')->whereIn('id', $enrolmentIds)->delete();
        DB::statement('PRAGMA foreign_keys = ON');
        $this->command->info('Cleared existing enrolments.');

        $profiles  = CompetitorProfile::where('profile_complete', true)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $eventIds = $competition->competitionEvents()
            ->orderBy('running_order')
            ->pluck('id')
            ->values()
            ->toArray();

        $yaEventId = $competition->competitionEvents()
            ->where('event_code', 'YA')
            ->value('id');

        $rankMap      = Rank::pluck('id', 'name');
        $service      = app(EnrolmentService::class);
        $enrolments   = [];
        $defaultIndex = 0;

        foreach ($profiles as $profile) {
            $data = $this->byName[$profile->full_name]
                ?? $this->defaults[$defaultIndex++ % count($this->defaults)];

            $entryDetails = [
                'rank_id'   => $rankMap[$data['rank']] ?? null,
                'weight_kg' => $data['weight_kg'],
                'dojo_type' => 'lfp',
                'dojo_name' => $data['dojo_name'],
            ];

            try {
                $enrolment = $service->enrol($profile, $competition, $eventIds, [], $entryDetails);
                $enrolments[$profile->id] = $enrolment;

                $assigned = $enrolment->activeEvents()->whereNotNull('division_id')->count();
                $total    = $enrolment->activeEvents()->count();
                $this->command->info("  {$profile->full_name} (age {$profile->age}, {$profile->gender}) — {$data['rank']}, {$data['weight_kg']}kg, {$data['dojo_name']} → {$assigned}/{$total} divisions assigned");
            } catch (\Throwable $e) {
                $this->command->error("  FAILED {$profile->full_name}: " . $e->getMessage());
            }
        }

        // Pair Yakusuko partners within each division
        if ($yaEventId) {
            $this->pairYakusuko($enrolments, $yaEventId);
        }

        $total = $competition->enrolments()->count();
        $totalEe = DB::table('enrolment_events')
            ->whereIn('enrolment_id', $competition->enrolments()->pluck('id'))
            ->where('removed', false)
            ->count();
        $this->command->info("Done — {$total} enrolments, {$totalEe} event entries.");
    }

    private function pairYakusuko(array $enrolments, int $yaEventId): void
    {
        $eeByDivision = [];

        foreach ($enrolments as $profileId => $enrolment) {
            $ee = EnrolmentEvent::where('enrolment_id', $enrolment->id)
                ->where('competition_event_id', $yaEventId)
                ->where('removed', false)
                ->whereNull('partner_enrolment_event_id')
                ->first();

            if ($ee) {
                $eeByDivision[$ee->division_id ?? 'none'][] = $ee;
            }
        }

        $svc   = app(EnrolmentService::class);
        $pairs = 0;

        foreach ($eeByDivision as $divId => $ees) {
            foreach (array_chunk($ees, 2) as $chunk) {
                if (count($chunk) === 2) {
                    $svc->resolveYakusukoPartner($chunk[0], $chunk[1]);
                    $pairs++;
                }
            }
        }

        $this->command->info("  Yakusuko: {$pairs} pair(s) linked.");
    }
}
