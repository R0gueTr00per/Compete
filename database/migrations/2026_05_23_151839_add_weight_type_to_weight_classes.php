<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('weight_classes', function (Blueprint $table) {
            $table->enum('weight_type', ['under', 'over'])->default('under')->after('max_kg');
        });
    }

    public function down(): void
    {
        Schema::table('weight_classes', function (Blueprint $table) {
            $table->dropColumn('weight_type');
        });
    }
};
