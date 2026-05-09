<?php

namespace Database\Seeders;

use App\Models\Competition;
use App\Models\EnrolmentEvent;
use App\Models\User;
use App\Services\EnrolmentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class TestEnrolmentSeeder extends Seeder
{
    // Specific rank/weight overrides for known test accounts.
    // Any competitor not listed here gets a rank cycled from $defaults below.
    private array $overrides = [
        'alice@example.com'      => ['rank_type' => 'kyu', 'rank_kyu' => 7, 'weight_kg' => 55],
        'bob@example.com'        => ['rank_type' => 'dan', 'rank_dan' => 1, 'weight_kg' => 75],
        'cara@example.com'       => ['rank_type' => 'kyu', 'rank_kyu' => 3, 'weight_kg' => 52],
        'dan@example.com'        => ['rank_type' => 'kyu', 'rank_kyu' => 5, 'weight_kg' => 68],
        'competitor@example.com' => ['rank_type' => 'kyu', 'rank_kyu' => 7, 'weight_kg' => 70],
    ];

    // Cycled through for any competitor not in $overrides — varied so divisions get spread.
    private array $defaults = [
        ['rank_type' => 'kyu', 'rank_kyu' => 8,  'weight_kg' => 38],  // 10-6 Kyu, lighter
        ['rank_type' => 'kyu', 'rank_kyu' => 7,  'weight_kg' => 55],  // 10-6 Kyu
        ['rank_type' => 'kyu', 'rank_kyu' => 6,  'weight_kg' => 48],  // 10-6 Kyu
        ['rank_type' => 'kyu', 'rank_kyu' => 4,  'weight_kg' => 62],  // 5-1 Kyu
        ['rank_type' => 'kyu', 'rank_kyu' => 3,  'weight_kg' => 58],  // 5-1 Kyu
        ['rank_type' => 'kyu', 'rank_kyu' => 1,  'weight_kg' => 72],  // 5-1 Kyu
        ['rank_type' => 'dan', 'rank_dan' => 1,  'weight_kg' => 80],  // Black Belt
        ['rank_type' => 'dan', 'rank_dan' => 2,  'weight_kg' => 66],  // Black Belt
    ];

    public function run(): void
    {
        Notification::fake();

        $competition = Competition::orderByDesc('competition_date')->first();

        if (! $competition) {
            $this->command->error('No competition found. Run LFPCompetitionSeeder first.');
            return;
        }

        $this->command->info("Enrolling into: {$competition->name}");

        // Clear existing enrolments for this competition
        $enrolmentIds = DB::table('enrolments')
            ->where('competition_id', $competition->id)
            ->pluck('id');

        $enrolmentEventIds = DB::table('enrolment_events')
            ->whereIn('enrolment_id', $enrolmentIds)
            ->pluck('id');

        DB::statement('PRAGMA foreign_keys = OFF');
        DB::table('round_robin_matches')
            ->whereIn('division_id', DB::table('divisions')
                ->whereIn('competition_event_id', $competition->competitionEvents()->pluck('id'))
                ->pluck('id'))
            ->delete();
        DB::table('results')->whereIn('enrolment_event_id', $enrolmentEventIds)->delete();
        DB::table('enrolment_events')->whereIn('id', $enrolmentEventIds)->delete();
        DB::table('enrolments')->where('competition_id', $competition->id)->delete();
        DB::statement('PRAGMA foreign_keys = ON');

        $this->command->info('Cleared existing enrolments.');

        // All competitors with complete profiles
        $competitors = User::role('competitor')
            ->whereHas('competitorProfile', fn ($q) => $q->where('profile_complete', true))
            ->with('competitorProfile')
            ->get();

        if ($competitors->isEmpty()) {
            $this->command->warn('No competitors with complete profiles found. Run DevSeeder or SampleDataSeeder first.');
            return;
        }

        $this->command->info("Found {$competitors->count()} competitor(s) with complete profiles.");

        $eventIds = $competition->competitionEvents()->pluck('id')->values()->toArray();
        $service  = app(EnrolmentService::class);
        $enrolments = [];
        $defaultIndex = 0;

        foreach ($competitors as $user) {
            $rankWeight = $this->overrides[$user->email]
                ?? $this->defaults[$defaultIndex++ % count($this->defaults)];

            $entryDetails = array_filter([
                'rank_type' => $rankWeight['rank_type'],
                'rank_kyu'  => $rankWeight['rank_kyu'] ?? null,
                'rank_dan'  => $rankWeight['rank_dan'] ?? null,
                'weight_kg' => $rankWeight['weight_kg'],
                'dojo_type' => 'lfp',
            ], fn ($v) => $v !== null);

            $enrolment = $service->enrol($user, $competition, $eventIds, [], $entryDetails);
            $enrolments[] = ['enrolment' => $enrolment, 'user' => $user];

            $profile  = $user->competitorProfile;
            $fullName = $profile ? "{$profile->first_name} {$profile->surname}" : $user->name;
            $count    = $enrolment->fresh()->activeEvents()->count();
            $rankDesc = isset($rankWeight['rank_dan'])
                ? "{$rankWeight['rank_dan']}st dan"
                : "{$rankWeight['rank_kyu']}th kyu";
            $this->command->info("  {$fullName} — {$rankDesc}, {$rankWeight['weight_kg']} kg → {$count} events");
        }

        // Pair Yakusuko partners: pair competitors in the same YA division
        $yakusuko = $competition->competitionEvents()->where('event_code', 'YA')->first();
        if ($yakusuko) {
            $this->pairYakusuko($enrolments, $yakusuko->id);
        }

        $total = DB::table('enrolments')->where('competition_id', $competition->id)->count();
        $this->command->info("Done. {$total} enrolments created.");
    }

    private function pairYakusuko(array $enrolments, int $yakusukoEventId): void
    {
        // Group enrolment events by division, then pair within each group
        $eeByDivision = [];
        foreach ($enrolments as $item) {
            $ee = EnrolmentEvent::where('enrolment_id', $item['enrolment']->id)
                ->where('competition_event_id', $yakusukoEventId)
                ->where('removed', false)
                ->whereNull('partner_enrolment_event_id')
                ->first();

            if ($ee) {
                $divId = $ee->division_id ?? 'none';
                $eeByDivision[$divId][] = $ee;
            }
        }

        $svc   = app(\App\Services\EnrolmentService::class);
        $pairs = 0;

        foreach ($eeByDivision as $divId => $ees) {
            // Pair them up in order: [0,1], [2,3], etc. — leftover stays unpaired
            $chunks = array_chunk($ees, 2);
            foreach ($chunks as $chunk) {
                if (count($chunk) === 2) {
                    $svc->resolveYakusukoPartner($chunk[0], $chunk[1]);
                    $pairs++;
                }
            }
        }

        $this->command->info("  Yakusuko: {$pairs} pair(s) linked.");
    }
}
