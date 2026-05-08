<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('surname', 100);
            $table->string('first_name', 100);
            $table->date('date_of_birth');
            $table->enum('gender', ['M', 'F']);
            $table->smallInteger('height_cm')->unsigned()->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('profile_complete')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_profiles');
    }
};
