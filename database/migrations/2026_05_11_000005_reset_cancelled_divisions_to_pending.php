<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('divisions')
            ->where('status', 'cancelled')
            ->update(['status' => 'pending', 'location_label' => null]);
    }

    public function down(): void
    {
        // Not reversible — cancelled status is being removed
    }
};
