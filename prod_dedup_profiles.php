<?php
// Dedup competitor profiles created by the profile-duplication bug.
// Keeps the most-recently-updated profile per user, reassigns enrolments, deletes dupes.
// Run: php artisan tinker --execute="require base_path('prod_dedup_profiles.php');"
// Safe to run multiple times (idempotent).

use App\Models\CompetitorProfile;
use App\Models\Enrolment;
use App\Models\OrganisationMembership;

// ── Step 1: Fix profiles with null organisation_id ───────────────────────────
$nullOrgProfiles = CompetitorProfile::whereNull('organisation_id')->get();

if ($nullOrgProfiles->isEmpty()) {
    echo "No profiles with null organisation_id found.\n";
} else {
    echo "Profiles with null organisation_id: " . $nullOrgProfiles->count() . "\n";

    foreach ($nullOrgProfiles as $profile) {
        $memberships = OrganisationMembership::where('user_id', $profile->owner_user_id)->get();

        if ($memberships->isEmpty()) {
            echo "  Profile {$profile->id} (owner {$profile->owner_user_id}): no memberships found — skipped\n";
            continue;
        }

        if ($memberships->count() === 1) {
            $orgId = $memberships->first()->organisation_id;
            $profile->organisation_id = $orgId;
            $profile->save();
            echo "  Profile {$profile->id} (owner {$profile->owner_user_id}): set organisation_id = {$orgId}\n";
        } else {
            // Multiple memberships — use the most recently joined/active one.
            $membership = $memberships->sortByDesc('joined_at')->first();
            $orgId = $membership->organisation_id;
            $profile->organisation_id = $orgId;
            $profile->save();
            echo "  Profile {$profile->id} (owner {$profile->owner_user_id}): multiple memberships, used most recent org {$orgId}\n";
        }
    }
}

echo "\n";

// ── Step 2: Dedup profiles (keep most recently updated per owner_user_id) ────
$dupeUsers = CompetitorProfile::select('owner_user_id')
    ->groupBy('owner_user_id')
    ->havingRaw('COUNT(*) > 1')
    ->pluck('owner_user_id');

if ($dupeUsers->isEmpty()) {
    echo "No duplicate profiles found.\n";
    return;
}

echo "Users with duplicate profiles: " . $dupeUsers->count() . "\n\n";

foreach ($dupeUsers as $userId) {
    $profiles = CompetitorProfile::where('owner_user_id', $userId)
        ->orderByDesc('updated_at')
        ->orderByDesc('profile_complete')
        ->orderByDesc('id')
        ->get();

    // Keep the first (most recently updated / most complete).
    $keep = $profiles->shift();

    echo "User {$userId}: keeping profile {$keep->id} (updated {$keep->updated_at}, org {$keep->organisation_id})\n";

    foreach ($profiles as $dupe) {
        $dupeEnrolments = Enrolment::where('competitor_profile_id', $dupe->id)->get();

        foreach ($dupeEnrolments as $enrolment) {
            $conflict = Enrolment::where('competitor_profile_id', $keep->id)
                ->where('competition_id', $enrolment->competition_id)
                ->exists();

            if ($conflict) {
                $enrolment->delete();
                echo "  -> deleted conflicting enrolment {$enrolment->id} (competition {$enrolment->competition_id}) from profile {$dupe->id}\n";
            } else {
                $enrolment->competitor_profile_id = $keep->id;
                $enrolment->save();
                echo "  -> reassigned enrolment {$enrolment->id} from profile {$dupe->id}\n";
            }
        }

        $dupe->delete();
        echo "  -> deleted profile {$dupe->id}\n";
    }
}

echo "\nDone.\n";
