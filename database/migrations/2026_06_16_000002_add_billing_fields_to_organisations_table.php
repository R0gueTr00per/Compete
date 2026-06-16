<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->decimal('annual_fee', 8, 2)->nullable()->after('platform_fee');
            $table->date('annual_fee_anniversary_date')->nullable()->after('annual_fee');
            $table->boolean('gst_registered')->default(false)->after('annual_fee_anniversary_date');
            $table->decimal('gst_rate', 5, 2)->nullable()->after('gst_registered');
            $table->boolean('competitor_logins_locked')->default(false)->after('gst_rate');
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn(['annual_fee', 'annual_fee_anniversary_date', 'gst_registered', 'gst_rate', 'competitor_logins_locked']);
        });
    }
};
