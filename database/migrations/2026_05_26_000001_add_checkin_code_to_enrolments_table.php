<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrolments', function (Blueprint $table) {
            $table->string('checkin_code', 8)->nullable()->unique()->after('status');
        });

        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $len = strlen($alphabet);

        foreach (DB::table('enrolments')->whereNull('checkin_code')->orderBy('id')->cursor() as $row) {
            do {
                $code = '';
                for ($i = 0; $i < 8; $i++) {
                    $code .= $alphabet[random_int(0, $len - 1)];
                }
            } while (DB::table('enrolments')->where('checkin_code', $code)->exists());

            DB::table('enrolments')->where('id', $row->id)->update(['checkin_code' => $code]);
        }
    }

    public function down(): void
    {
        Schema::table('enrolments', function (Blueprint $table) {
            $table->dropUnique(['checkin_code']);
            $table->dropColumn('checkin_code');
        });
    }
};
