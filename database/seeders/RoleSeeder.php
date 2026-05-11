<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'system_admin',             'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'competition_administrator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'competition_official',      'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'user',                      'guard_name' => 'web']);
    }
}
