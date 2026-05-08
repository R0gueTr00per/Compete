<?php

namespace Database\Seeders;

use App\Models\CompetitorProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        // --- Competitors ---
        $competitors = [
            [
                'email' => 'alice@example.com', 'name' => 'Alice Chen',
                'profile' => ['surname' => 'Chen', 'first_name' => 'Alice', 'date_of_birth' => '1995-03-12',
                    'gender' => 'F', 'phone' => '0411000001'],
            ],
            [
                'email' => 'bob@example.com', 'name' => 'Bob Smith',
                'profile' => ['surname' => 'Smith', 'first_name' => 'Bob', 'date_of_birth' => '1988-07-22',
                    'gender' => 'M', 'phone' => '0411000002'],
            ],
            [
                'email' => 'cara@example.com', 'name' => 'Cara Jones',
                'profile' => ['surname' => 'Jones', 'first_name' => 'Cara', 'date_of_birth' => '2008-11-05',
                    'gender' => 'F', 'phone' => '0411000003'],
            ],
            [
                'email' => 'dan@example.com', 'name' => 'Dan Wu',
                'profile' => ['surname' => 'Wu', 'first_name' => 'Dan', 'date_of_birth' => '1999-01-30',
                    'gender' => 'M', 'phone' => '0411000004'],
            ],
        ];

        foreach ($competitors as $c) {
            $user = User::updateOrCreate(
                ['email' => $c['email']],
                ['name' => $c['name'], 'password' => Hash::make('password'), 'email_verified_at' => now()]
            );
            $user->assignRole('competitor');
            CompetitorProfile::updateOrCreate(
                ['user_id' => $user->id],
                array_merge($c['profile'], ['profile_complete' => true])
            );
        }

        $this->command->info('Sample data seeded: 4 competitor accounts created.');
    }
}
