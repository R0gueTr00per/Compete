<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            $table->json('category_config')->nullable()->after('scoring_method');
        });
    }

    public function down(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            $table->dropColumn('category_config');
        });
    }
};
