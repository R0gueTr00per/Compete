<?php

use App\Models\MatchPenalty;
use App\Models\Result;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->boolean('forfeited')->default(false)->after('disqualified');
        });

        // Backfill: results that were disqualified solely via a forfeit penalty
        // (no accompanying 'dq' penalty) should be converted to forfeited=true, disqualified=false.
        Result::where('disqualified', true)->each(function (Result $result) {
            $hasForfeit = MatchPenalty::where('result_id', $result->id)
                ->where('type', 'forfeit')
                ->exists();
            $hasDq = MatchPenalty::where('result_id', $result->id)
                ->whereIn('type', ['dq'])
                ->exists();

            if ($hasForfeit && ! $hasDq) {
                $result->forceFill(['disqualified' => false, 'forfeited' => true])->save();
            }
        });
    }

    public function down(): void
    {
        // Convert forfeited back to disqualified before dropping the column
        Result::where('forfeited', true)->update(['disqualified' => true, 'forfeited' => false]);

        Schema::table('results', function (Blueprint $table) {
            $table->dropColumn('forfeited');
        });
    }
};
