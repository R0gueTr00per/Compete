<?php

namespace Database\Seeders;

use App\Models\CompetitorProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RoleSeeder::class, SampleDataSeeder::class]);

        $sysAdmin = User::updateOrCreate(
            ['email' => 'sysadmin@example.com'],
            ['password' => Hash::make('password'), 'email_verified_at' => now(), 'status' => 'active']
        );
        $sysAdmin->syncRoles(['system_admin']);

        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            ['password' => Hash::make('password'), 'email_verified_at' => now(), 'status' => 'active']
        );
        $admin->syncRoles(['competition_administrator']);

        $competitor = User::updateOrCreate(
            ['email' => 'competitor@example.com'],
            ['password' => Hash::make('password'), 'email_verified_at' => now(), 'status' => 'active']
        );
        $competitor->syncRoles(['user']);

        CompetitorProfile::updateOrCreate(
            ['user_id' => $competitor->id],
            [
                'surname'          => 'Competitor',
                'first_name'       => 'Test',
                'date_of_birth'    => '1990-06-15',
                'gender'           => 'M',
                'phone'            => '0400000000',
                'profile_complete' => true,
            ]
        );

        $this->call(LfpRound1Seeder::class);
    }
}
