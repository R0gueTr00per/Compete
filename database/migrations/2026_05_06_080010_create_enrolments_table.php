<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrolments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competitor_id')->constrained('users');
            $table->timestamp('enrolled_at');
            $table->boolean('is_late')->default(false);
            $table->decimal('fee_calculated', 6, 2);
            $table->enum('status', ['pending', 'confirmed', 'withdrawn'])->default('pending');
            $table->timestamps();

            $table->unique(['competition_id', 'competitor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrolments');
    }
};
