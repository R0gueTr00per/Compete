<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrolments', function (Blueprint $table) {
            $table->enum('rank_type', ['kyu', 'dan', 'experience'])->nullable()->after('status');
            $table->tinyInteger('rank_kyu')->unsigned()->nullable()->after('rank_type');
            $table->tinyInteger('rank_dan')->unsigned()->nullable()->after('rank_kyu');
            $table->tinyInteger('experience_years')->unsigned()->nullable()->after('rank_dan');
            $table->tinyInteger('experience_months')->unsigned()->nullable()->after('experience_years');
            $table->decimal('weight_kg', 5, 2)->nullable()->after('experience_months');
            $table->enum('dojo_type', ['lfp', 'guest'])->nullable()->after('weight_kg');
            $table->string('dojo_name', 100)->nullable()->after('dojo_type');
            $table->string('guest_style', 100)->nullable()->after('dojo_name');
        });
    }

    public function down(): void
    {
        Schema::table('enrolments', function (Blueprint $table) {
            $table->dropColumn([
                'rank_type', 'rank_kyu', 'rank_dan',
                'experience_years', 'experience_months',
                'weight_kg', 'dojo_type', 'dojo_name', 'guest_style',
            ]);
        });
    }
};
