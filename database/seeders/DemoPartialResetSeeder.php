<?php

namespace Database\Seeders;

use App\Models\Competition;
use App\Models\CompetitorProfile;
use App\Models\Enrolment;
use App\Models\Organisation;
use App\Models\OrganisationMembership;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Partial reset for the demo organisation: removes enrolments, divisions, competitor
 * profiles, and non-admin users — but leaves the competition, event types, and bands
 * intact so manually-adjusted event types are preserved.
 *
 * Usage:
 *   php artisan db:seed --class=DemoPartialResetSeeder
 *
 * To reset and re-seed in one step:
 *   php artisan db:seed --class=DemoPartialResetSeeder && php artisan db:seed --class=DemoSeeder
 */
class DemoPartialResetSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organisation::where('slug', 'like', 'demo%')->latest('id')->first();

        if (! $org) {
            $this->command->warn('No demo organisation found (slug starting with "demo"). Skipping.');
            return;
        }

        $this->command->info("Org: {$org->name} (slug: {$org->slug})");

        // ── Enrolments ────────────────────────────────────────────────────────────
        $competitions = Competition::where('organisation_id', $org->id)->get();

        foreach ($competitions as $competition) {
            foreach ($competition->enrolments as $enrolment) {
                $enrolment->enrolmentEvents()->delete();
            }
            $enrolmentCount = $competition->enrolments()->count();
            $competition->enrolments()->delete();

            $divisionCount = 0;
            foreach ($competition->competitionEvents as $event) {
                $divisionCount += $event->divisions()->count();
                $event->divisions()->delete();
            }

            $this->command->info(
                "  Competition \"{$competition->name}\": deleted {$enrolmentCount} enrolment(s), {$divisionCount} division(s)."
            );
        }

        if ($competitions->isEmpty()) {
            $this->command->line('  No competitions found.');
        }

        // ── Users ─────────────────────────────────────────────────────────────────
        $orgUserIds = OrganisationMembership::where('organisation_id', $org->id)
            ->pluck('user_id')
            ->merge(User::where('organisation_id', $org->id)->pluck('id'))
            ->unique();

        $orgAdminIds = OrganisationMembership::where('organisation_id', $org->id)
            ->where('role', 'administrator')
            ->pluck('user_id');

        $deleted = 0;
        $kept    = 0;

        foreach ($orgUserIds as $userId) {
            $user = User::find($userId);
            if (! $user) {
                continue;
            }

            if ($user->hasRole('system_admin')) {
                $kept++;
                continue;
            }

            if ($orgAdminIds->contains($userId)) {
                $kept++;
                continue;
            }

            CompetitorProfile::where('owner_user_id', $userId)->delete();
            OrganisationMembership::where('user_id', $userId)->delete();
            $user->delete();
            $deleted++;
        }

        $this->command->info("  Deleted {$deleted} user(s), kept {$kept} (sysadmin/orgadmin).");
        $this->command->info('Partial reset complete. Run DemoSeeder to repopulate.');
    }
}
