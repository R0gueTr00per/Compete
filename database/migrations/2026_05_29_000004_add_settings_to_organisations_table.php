<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->boolean('auto_email_insights')->default(true)->after('ai_context');
            $table->boolean('insights_auto_refresh')->default(true)->after('auto_email_insights');
            $table->unsignedSmallInteger('dashboard_closed_days')->default(7)->after('insights_auto_refresh');
            $table->string('timezone', 100)->nullable()->after('dashboard_closed_days');
            $table->string('date_format', 20)->nullable()->after('timezone');
            $table->string('currency', 10)->nullable()->after('date_format');
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn([
                'auto_email_insights',
                'insights_auto_refresh',
                'dashboard_closed_days',
                'timezone',
                'date_format',
                'currency',
            ]);
        });
    }
};
