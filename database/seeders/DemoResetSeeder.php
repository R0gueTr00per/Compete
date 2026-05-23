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
 * Wipes all demo data for the most-recently-created organisation whose slug starts with "demo":
 *
 *  - All competitions (and their events, divisions, age/rank/weight bands, enrolments, enrolment events)
 *  - All org users except system_admin role holders and org administrators (membership role = "administrator")
 *  - Org administrators' enrolments and competitor profiles are removed, but the user account is kept
 *
 * Deletion order respects FK constraints.
 *
 * Usage:
 *   php artisan db:seed --class=DemoResetSeeder
 *
 * To reset and re-seed in one step:
 *   php artisan db:seed --class=DemoResetSeeder && php artisan db:seed --class=DemoSeeder
 */
class DemoResetSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organisation::where('slug', 'like', 'demo%')->latest('id')->first();

        if (! $org) {
            $this->command->warn('No demo organisation found (slug starting with "demo"). Skipping.');
            return;
        }

        $this->command->info("Org: {$org->name} (slug: {$org->slug})");

        // ── Competitions ──────────────────────────────────────────────────────────
        $competitions = Competition::where('organisation_id', $org->id)->get();

        foreach ($competitions as $competition) {
            $enrolmentCount = $competition->enrolments()->count();

            foreach ($competition->enrolments as $enrolment) {
                $enrolment->enrolmentEvents()->delete();
            }
            $competition->enrolments()->delete();

            foreach ($competition->competitionEvents as $event) {
                $event->divisions()->delete();
            }
            $competition->competitionEvents()->delete();

            $competition->ageBands()->delete();
            $competition->rankBands()->delete();
            $competition->weightClasses()->delete();

            $competition->delete();

            $this->command->info(
                "  Deleted competition \"{$competition->name}\" ({$enrolmentCount} enrolments)."
            );
        }

        if ($competitions->isEmpty()) {
            $this->command->line('  No competitions found.');
        }

        // ── Users ─────────────────────────────────────────────────────────────────
        // Collect all user IDs that belong to this org (via membership or organisation_id).
        $orgUserIds = OrganisationMembership::where('organisation_id', $org->id)
            ->pluck('user_id')
            ->merge(User::where('organisation_id', $org->id)->pluck('id'))
            ->unique();

        $orgAdminIds = OrganisationMembership::where('organisation_id', $org->id)
            ->where('role', 'administrator')
            ->pluck('user_id');

        $deleted  = 0;
        $kept     = 0;

        foreach ($orgUserIds as $userId) {
            $user = User::find($userId);
            if (! $user) {
                continue;
            }

            // Never touch system admins.
            if ($user->hasRole('system_admin')) {
                $kept++;
                continue;
            }

            // Keep org admins but strip their enrolments and competitor profiles.
            if ($orgAdminIds->contains($userId)) {
                $profileIds = CompetitorProfile::where('owner_user_id', $userId)->pluck('id');
                if ($profileIds->isNotEmpty()) {
                    foreach (Enrolment::whereIn('competitor_profile_id', $profileIds)->get() as $enrolment) {
                        $enrolment->enrolmentEvents()->delete();
                        $enrolment->delete();
                    }
                    CompetitorProfile::whereIn('id', $profileIds)->delete();
                }
                $kept++;
                $this->command->line("  Kept org admin user ID {$userId} — enrolments/profiles cleared.");
                continue;
            }

            // Delete everyone else.
            CompetitorProfile::where('owner_user_id', $userId)->delete();
            OrganisationMembership::where('user_id', $userId)->delete();
            $user->delete();
            $deleted++;
        }

        $this->command->info("  Deleted {$deleted} user(s), kept {$kept} (sysadmin/orgadmin).");
        $this->command->info('Reset complete.');
    }
}
