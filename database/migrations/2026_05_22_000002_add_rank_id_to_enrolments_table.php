<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrolments', function (Blueprint $table) {
            $table->foreignId('rank_id')->nullable()->constrained('ranks')->nullOnDelete()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('enrolments', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Rank::class);
            $table->dropColumn('rank_id');
        });
    }
};
