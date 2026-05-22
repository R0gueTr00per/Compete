<?php

namespace Database\Seeders;

use App\Models\Competition;
use App\Models\CompetitorProfile;
use App\Models\Dojo;
use App\Models\Organisation;
use App\Models\Rank;
use App\Models\User;
use App\Services\EnrolmentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;

/**
 * Enrols all 100 demo competitors (demo.0…demo.99@nodomain.invalid) in every event
 * of the "Demo Tournament" for the most-recently-created demo organisation.
 *
 * Rank and weight are sourced from DemoProfileData so they match the profiles created
 * by DemoCompetitorSeeder. Already-enrolled competitors are silently skipped.
 * Safe to re-run.
 */
class DemoEnrolmentSeeder extends Seeder
{
    public function __construct(private readonly EnrolmentService $enrolmentService) {}

    public function run(): void
    {
        Notification::fake();

        $org = Organisation::where('slug', 'like', 'demo%')->latest('id')->first();

        if (! $org) {
            $this->command->warn('No demo organisation found (slug starting with "demo"). Skipping.');
            return;
        }

        $competition = Competition::where('organisation_id', $org->id)
            ->where('name', 'Demo Tournament')
            ->first();

        if (! $competition) {
            $this->command->warn('Demo Tournament not found — run DemoCompetitionSeeder first.');
            return;
        }

        $allEventIds = $competition->competitionEvents()->pluck('id')->toArray();

        if (empty($allEventIds)) {
            $this->command->warn('No events found for Demo Tournament — run DemoCompetitionSeeder first.');
            return;
        }

        $divisionCount = $competition->allDivisions()->count();
        if ($divisionCount === 0) {
            $this->command->warn('No divisions found — run DemoCompetitionSeeder first.');
            return;
        }

        $dojoNames = Dojo::where('organisation_id', $org->id)
            ->where('is_active', true)
            ->pluck('name')
            ->toArray();

        if (empty($dojoNames)) {
            $dojoNames = ['Demo Dojo'];
        }

        $rankMap   = Rank::pluck('id', 'name');
        $ordinal   = fn (int $n): string => $n . match (true) {
            $n % 100 >= 11 && $n % 100 <= 13 => 'th',
            $n % 10 === 1 => 'st',
            $n % 10 === 2 => 'nd',
            $n % 10 === 3 => 'rd',
            default => 'th',
        };
        $resolveRankId = function (array $data) use ($rankMap, $ordinal): ?int {
            if ($data['rank_type'] === 'kyu' && $data['rank_kyu']) {
                return $rankMap[$ordinal($data['rank_kyu']) . ' Kyu'] ?? null;
            }
            if ($data['rank_type'] === 'dan' && $data['rank_dan']) {
                return $rankMap[$ordinal($data['rank_dan']) . ' Dan'] ?? null;
            }
            return null;
        };

        $profiles  = DemoProfileData::buildProfiles();
        $enrolled  = 0;
        $skipped   = 0;

        foreach ($profiles as $i => $data) {
            $email = "demo.{$i}@nodomain.invalid";
            $user  = User::where('email', $email)->first();

            if (! $user) {
                $this->command->warn("  [{$i}] User {$email} not found — run DemoCompetitorSeeder first.");
                continue;
            }

            $profile = CompetitorProfile::where('owner_user_id', $user->id)->first();

            if (! $profile) {
                $this->command->warn("  [{$i}] No profile for {$email} — skipping.");
                continue;
            }

            $alreadyEnrolled = $profile->enrolments()
                ->where('competition_id', $competition->id)
                ->exists();

            if ($alreadyEnrolled) {
                $skipped++;
                continue;
            }

            $entryDetails = [
                'rank_id'   => $resolveRankId($data),
                'weight_kg' => $data['weight_kg'],
                'dojo_type' => 'lfp',
                'dojo_name' => $dojoNames[$i % count($dojoNames)],
            ];

            try {
                $enrolment = $this->enrolmentService->enrol(
                    $profile,
                    $competition,
                    $allEventIds,
                    [],
                    $entryDetails,
                    notify: false,
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
                    '  [%3d] %-22s  %-12s  %5.1f kg  %d/%d divisions assigned',
                    $i,
                    $data['first_name'] . ' ' . $data['surname'],
                    $rankDesc,
                    $data['weight_kg'],
                    $assigned,
                    $total,
                ));

                $enrolled++;
            } catch (\Throwable $e) {
                $this->command->error("  [{$i}] FAILED {$data['first_name']} {$data['surname']}: " . $e->getMessage());
            }
        }

        $suffix = $skipped > 0 ? ", {$skipped} already enrolled (skipped)" : '';
        $this->command->info("Done: {$enrolled} competitor(s) enrolled{$suffix}.");
    }
}
