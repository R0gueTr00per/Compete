<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('enrolment_carts', 'platform_fee_rate')) {
            Schema::table('enrolment_carts', function (Blueprint $table) {
                $table->decimal('platform_fee_rate', 10, 2)->nullable()->after('late_surcharge_rate');
            });
        }

        // Backfill existing carts using the current org platform fee
        $carts = DB::table('enrolment_carts')
            ->whereNotNull('competition_id')
            ->get(['id', 'competition_id']);

        foreach ($carts as $cart) {
            $platformFee = DB::table('competitions')
                ->join('organisations', 'competitions.organisation_id', '=', 'organisations.id')
                ->where('competitions.id', $cart->competition_id)
                ->value('organisations.platform_fee');

            DB::table('enrolment_carts')
                ->where('id', $cart->id)
                ->update(['platform_fee_rate' => $platformFee]);
        }
    }

    public function down(): void
    {
        Schema::table('enrolment_carts', function (Blueprint $table) {
            $table->dropColumn('platform_fee_rate');
        });
    }
};
