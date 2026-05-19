<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrolments', function (Blueprint $table) {
            $table->boolean('is_official_discount')->default(false)->after('is_late');
        });
    }

    public function down(): void
    {
        Schema::table('enrolments', function (Blueprint $table) {
            $table->dropColumn('is_official_discount');
        });
    }
};
