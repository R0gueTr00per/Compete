<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrolment_carts', function (Blueprint $table) {
            $table->dropForeign(['competition_id']);
            $table->unsignedBigInteger('competition_id')->nullable()->change();
            $table->foreign('competition_id')->references('id')->on('competitions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('enrolment_carts', function (Blueprint $table) {
            $table->dropForeign(['competition_id']);
            $table->unsignedBigInteger('competition_id')->nullable(false)->change();
            $table->foreign('competition_id')->references('id')->on('competitions')->cascadeOnDelete();
        });
    }
};
