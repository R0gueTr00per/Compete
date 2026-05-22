<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $rankMap = DB::table('ranks')->pluck('id', 'name');

        $ordinal = function (int $n): string {
            $suffix = match (true) {
                $n % 100 === 11, $n % 100 === 12, $n % 100 === 13 => 'th',
                $n % 10 === 1 => 'st',
                $n % 10 === 2 => 'nd',
                $n % 10 === 3 => 'rd',
                default => 'th',
            };
            return $n . $suffix;
        };

        DB::table('enrolments')
            ->where('rank_type', 'kyu')
            ->whereNotNull('rank_kyu')
            ->whereNull('rank_id')
            ->get(['id', 'rank_kyu'])
            ->each(function ($row) use ($rankMap, $ordinal) {
                $rankId = $rankMap[$ordinal($row->rank_kyu) . ' Kyu'] ?? null;
                if ($rankId) {
                    DB::table('enrolments')->where('id', $row->id)->update(['rank_id' => $rankId]);
                }
            });

        DB::table('enrolments')
            ->where('rank_type', 'dan')
            ->whereNotNull('rank_dan')
            ->whereNull('rank_id')
            ->get(['id', 'rank_dan'])
            ->each(function ($row) use ($rankMap, $ordinal) {
                $rankId = $rankMap[$ordinal($row->rank_dan) . ' Dan'] ?? null;
                if ($rankId) {
                    DB::table('enrolments')->where('id', $row->id)->update(['rank_id' => $rankId]);
                }
            });

        Schema::table('enrolments', function (Blueprint $table) {
            $table->dropColumn([
                'rank_type',
                'rank_kyu',
                'rank_dan',
                'experience_years',
                'experience_months',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('enrolments', function (Blueprint $table) {
            $table->enum('rank_type', ['kyu', 'dan', 'experience'])->nullable()->after('rank_id');
            $table->tinyInteger('rank_kyu')->unsigned()->nullable()->after('rank_type');
            $table->tinyInteger('rank_dan')->unsigned()->nullable()->after('rank_kyu');
            $table->tinyInteger('experience_years')->unsigned()->nullable()->after('rank_dan');
            $table->tinyInteger('experience_months')->unsigned()->nullable()->after('experience_years');
        });
    }
};
