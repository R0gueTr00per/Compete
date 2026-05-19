<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_officials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('official_role_id')->constrained('official_roles')->restrictOnDelete();
            $table->foreignId('competition_location_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['competition_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_officials');
    }
};
