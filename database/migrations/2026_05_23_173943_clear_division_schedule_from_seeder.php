<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('divisions')->update([
            'location_label' => null,
            'running_order'  => null,
        ]);
    }

    public function down(): void {}
};
