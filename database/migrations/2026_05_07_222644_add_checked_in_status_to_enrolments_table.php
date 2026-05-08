<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Widen status from enum to string so 'checked_in' is a valid value.
        Schema::table('enrolments', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('enrolments', function (Blueprint $table) {
            $table->enum('status', ['pending', 'confirmed', 'withdrawn'])->default('pending')->change();
        });
    }
};
