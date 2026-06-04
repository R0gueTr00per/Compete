<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Recalculate total_amount for every cart as:
        //   sum(fee_calculated for active enrolments) + count(active enrolments) * platform_fee_rate
        // This is the authoritative stored total — never recalculated at display time.
        $carts = DB::table('enrolment_carts')->get(['id', 'platform_fee_rate']);

        foreach ($carts as $cart) {
            $platformRate = (float) ($cart->platform_fee_rate ?? 0);

            $enrolments = DB::table('enrolments')
                ->where('cart_id', $cart->id)
                ->whereNotIn('status', ['draft', 'withdrawn'])
                ->get(['fee_calculated']);

            $total = $enrolments->sum('fee_calculated') + $enrolments->count() * $platformRate;

            DB::table('enrolment_carts')
                ->where('id', $cart->id)
                ->update(['total_amount' => $total]);
        }
    }

    public function down(): void {}
};
