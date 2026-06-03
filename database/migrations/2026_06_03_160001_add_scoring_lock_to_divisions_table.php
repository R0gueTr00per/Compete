<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            $table->foreignId('scoring_locked_by')->nullable()->after('awarded_places')->constrained('users')->nullOnDelete();
            $table->timestamp('scoring_locked_at')->nullable()->after('scoring_locked_by');
        });
    }

    public function down(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            $table->dropForeign(['scoring_locked_by']);
            $table->dropColumn(['scoring_locked_by', 'scoring_locked_at']);
        });
    }
};
