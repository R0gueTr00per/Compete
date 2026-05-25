<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->unique()->constrained()->cascadeOnDelete();
            $table->longText('content');
            $table->json('data_snapshot')->nullable();
            $table->string('model_used')->default('claude-sonnet-4-6');
            $table->timestamp('generated_at');
            $table->timestamps();
        });

        Schema::table('organisations', function (Blueprint $table) {
            $table->text('ai_context')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_insights');
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn('ai_context');
        });
    }
};
