<?php

namespace Database\Seeders;

use App\Models\CompetitorProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $email    = env('SEED_ADMIN_EMAIL');
        $name     = env('SEED_ADMIN_NAME', 'System Admin');
        $password = env('SEED_ADMIN_PASSWORD');

        if (! $email || ! $password) {
            $this->command->error('Set SEED_ADMIN_EMAIL and SEED_ADMIN_PASSWORD in .env before running this seeder.');
            return;
        }

        $admin = User::updateOrCreate(
            ['email' => $email],
            [
                'name'               => $name,
                'password'           => Hash::make($password),
                'email_verified_at'  => now(),
                'status'             => 'active',
            ]
        );

        $admin->syncRoles(['system_admin']);

        $this->command->info("System admin created: {$email}");
    }
}
