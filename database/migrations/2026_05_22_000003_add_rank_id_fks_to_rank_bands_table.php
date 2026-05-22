<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rank_bands', function (Blueprint $table) {
            $table->foreignId('from_rank_id')->nullable()->constrained('ranks')->nullOnDelete()->after('id');
            $table->foreignId('to_rank_id')->nullable()->constrained('ranks')->nullOnDelete()->after('from_rank_id');
        });
    }

    public function down(): void
    {
        Schema::table('rank_bands', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Rank::class, 'from_rank_id');
            $table->dropForeignIdFor(\App\Models\Rank::class, 'to_rank_id');
            $table->dropColumn(['from_rank_id', 'to_rank_id']);
        });
    }
};
