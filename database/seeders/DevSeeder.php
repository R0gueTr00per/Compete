<?php

namespace Database\Seeders;

use App\Models\Competition;
use App\Models\CompetitorProfile;
use App\Models\Dojo;
use App\Models\Organisation;
use App\Models\OrganisationMembership;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RoleSeeder::class, SampleDataSeeder::class]);

        // System admin — global, not tied to any org
        $sysAdmin = User::updateOrCreate(
            ['email' => 'sysadmin@example.com'],
            ['password' => Hash::make('password'), 'email_verified_at' => now(), 'status' => 'active', 'organisation_id' => null]
        );
        $sysAdmin->syncRoles(['system_admin']);

        // Create the LFP organisation and assign the old admin as its org admin
        $lfp = Organisation::updateOrCreate(
            ['slug' => 'lfp'],
            ['name' => 'Loong Fu Pai Martial Arts', 'status' => 'active', 'created_by_user_id' => $sysAdmin->id]
        );

        // Org admin for LFP (what the old competition_administrator was)
        $orgAdmin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            ['password' => Hash::make('password'), 'email_verified_at' => now(), 'status' => 'active']
        );
        $orgAdmin->syncRoles([]); // No Spatie roles — access via membership

        OrganisationMembership::updateOrCreate(
            ['organisation_id' => $lfp->id, 'user_id' => $orgAdmin->id],
            ['role' => 'administrator', 'status' => 'active', 'joined_at' => now()]
        );

        // Competitor user for LFP
        $competitor = User::updateOrCreate(
            ['email' => 'competitor@example.com'],
            ['password' => Hash::make('password'), 'email_verified_at' => now(), 'status' => 'active']
        );
        $competitor->syncRoles([]);

        OrganisationMembership::updateOrCreate(
            ['organisation_id' => $lfp->id, 'user_id' => $competitor->id],
            ['role' => 'competitor', 'status' => 'active', 'joined_at' => now()]
        );

        CompetitorProfile::updateOrCreate(
            ['owner_user_id' => $competitor->id, 'profile_type' => 'self'],
            [
                'organisation_id'  => $lfp->id,
                'is_active'        => true,
                'surname'          => 'Competitor',
                'first_name'       => 'Test',
                'date_of_birth'    => '1990-06-15',
                'gender'           => 'M',
                'phone'            => '0400000000',
                'profile_complete' => true,
            ]
        );

        // Backfill existing competitions, competitor profiles, and dojos to LFP
        Competition::whereNull('organisation_id')->update(['organisation_id' => $lfp->id]);
        CompetitorProfile::whereNull('organisation_id')->update(['organisation_id' => $lfp->id]);
        Dojo::whereNull('organisation_id')->update(['organisation_id' => $lfp->id]);

        $this->call(LfpRound1Seeder::class);
    }
}
