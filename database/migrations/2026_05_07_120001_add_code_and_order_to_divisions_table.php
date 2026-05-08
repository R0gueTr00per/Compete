<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->after('id');
            $table->smallInteger('running_order')->nullable()->after('code');
            $table->string('location_label', 50)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            $table->dropColumn(['code', 'running_order', 'location_label']);
        });
    }
};
