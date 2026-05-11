<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitor_profiles', function (Blueprint $table) {
            $table->string('surname', 100)->nullable()->change();
            $table->string('first_name', 100)->nullable()->change();
            $table->date('date_of_birth')->nullable()->change();
            $table->enum('gender', ['M', 'F'])->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('competitor_profiles', function (Blueprint $table) {
            $table->string('surname', 100)->nullable(false)->change();
            $table->string('first_name', 100)->nullable(false)->change();
            $table->date('date_of_birth')->nullable(false)->change();
            $table->enum('gender', ['M', 'F'])->nullable(false)->change();
        });
    }
};
