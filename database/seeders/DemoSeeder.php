<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Orchestrates the full demo data pipeline for the most-recently-created organisation
 * whose slug starts with "demo":
 *
 *  1. DemoCompetitionSeeder  — creates/updates the competition, bands, events, and all 226 divisions
 *  2. DemoCompetitorSeeder   — creates 100 demo user + competitor profile records
 *  3. DemoEnrolmentSeeder    — enrols every competitor in every event
 *  4. DemoCheckInSeeder      — confirms weights and checks in every enrolment
 *
 * Each step is idempotent — re-running skips records that already exist.
 *
 * Usage:
 *   php artisan db:seed --class=DemoSeeder
 *
 * Individual steps can also be run standalone, e.g.:
 *   php artisan db:seed --class=DemoCheckInSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DemoCompetitionSeeder::class,
            DemoCompetitorSeeder::class,
            DemoEnrolmentSeeder::class,
            DemoCheckInSeeder::class,
        ]);
    }
}
