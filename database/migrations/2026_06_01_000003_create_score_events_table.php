<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('score_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 7, 3);
            $table->timestamps();

            $table->index(['result_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_events');
    }
};
