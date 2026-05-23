<?php

namespace Database\Seeders;

use App\Models\CompetitorProfile;
use App\Models\Organisation;
use App\Models\OrganisationMembership;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates 100 demo user + competitor profile records for the most-recently-created
 * organisation whose slug starts with "demo".
 *
 * Users are keyed by email demo.0@nodomain.invalid … demo.99@nodomain.invalid
 * so they can be looked up deterministically by other demo seeders.
 *
 * This seeder does NOT enrol competitors — run DemoEnrolmentSeeder for that.
 * Safe to re-run: existing users are left untouched (skipped).
 */
class DemoCompetitorSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organisation::where('slug', 'like', 'demo%')->latest('id')->first();

        if (! $org) {
            $this->command->warn('No demo organisation found (slug starting with "demo"). Skipping.');
            return;
        }

        $this->command->info("Org: {$org->name} (slug: {$org->slug})");

        $profiles  = DemoProfileData::buildProfiles();
        $created   = 0;
        $skipped   = 0;

        foreach ($profiles as $i => $data) {
            $email = "demo.{$i}@nodomain.invalid";

            if (User::where('email', $email)->exists()) {
                $skipped++;
                continue;
            }

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

            CompetitorProfile::create([
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

            $rankDesc = $data['rank_type'] === 'dan'
                ? "{$data['rank_dan']} dan"
                : "{$data['rank_kyu']} kyu";

            $this->command->line(sprintf(
                '  [%3d] %-22s age %2d %s  %-14s  %5.1f kg',
                $i,
                $data['first_name'] . ' ' . $data['surname'],
                $data['age'],
                $data['gender'],
                $rankDesc,
                $data['weight_kg'],
            ));

            $created++;
        }

        $suffix = $skipped > 0 ? ", {$skipped} already existed (skipped)" : '';
        $this->command->info("Done: {$created} competitor(s) created{$suffix}.");
    }
}
