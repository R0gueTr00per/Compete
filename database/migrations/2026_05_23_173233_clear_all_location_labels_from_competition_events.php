<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('competition_events')->update(['location_label' => null]);
    }

    public function down(): void {}
};
