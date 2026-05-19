<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->decimal('fee_official_first_event', 6, 2)->nullable()->after('late_surcharge');
            $table->decimal('fee_official_additional_event', 6, 2)->nullable()->after('fee_official_first_event');
        });
    }

    public function down(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropColumn(['fee_official_first_event', 'fee_official_additional_event']);
        });
    }
};
