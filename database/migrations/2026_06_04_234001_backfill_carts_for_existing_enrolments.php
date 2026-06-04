<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Group cart-less enrolments by owner_user_id + competition_id and create one cart per group.
        $enrolments = DB::table('enrolments')
            ->whereNull('enrolments.cart_id')
            ->whereNotIn('enrolments.status', ['draft'])
            ->join('competitor_profiles', 'enrolments.competitor_profile_id', '=', 'competitor_profiles.id')
            ->join('competitions', 'enrolments.competition_id', '=', 'competitions.id')
            ->select(
                'enrolments.id as enrolment_id',
                'enrolments.competition_id',
                'enrolments.enrolled_at',
                'enrolments.fee_calculated',
                'competitor_profiles.owner_user_id as user_id',
                'competitions.fee_first_event',
                'competitions.fee_additional_event',
                'competitions.fee_official_first_event',
                'competitions.fee_official_additional_event',
                'competitions.late_surcharge',
            )
            ->orderBy('competitor_profiles.owner_user_id')
            ->orderBy('enrolments.competition_id')
            ->orderBy('enrolments.enrolled_at')
            ->get();

        // Group by user_id + competition_id
        $groups = $enrolments->groupBy(fn ($row) => $row->user_id . '_' . $row->competition_id);

        foreach ($groups as $group) {
            $first      = $group->first();
            $totalAmount = $group->sum('fee_calculated');

            $cartId = DB::table('enrolment_carts')->insertGetId([
                'user_id'                       => $first->user_id,
                'competition_id'                => $first->competition_id,
                'status'                        => 'submitted',
                'total_amount'                  => $totalAmount,
                'fee_first_rate'                => $first->fee_first_event,
                'fee_additional_rate'           => $first->fee_additional_event,
                'fee_official_first_rate'       => $first->fee_official_first_event,
                'fee_official_additional_rate'  => $first->fee_official_additional_event,
                'late_surcharge_rate'           => $first->late_surcharge,
                'submitted_at'                  => $first->enrolled_at,
                'created_at'                    => $first->enrolled_at,
                'updated_at'                    => $first->enrolled_at,
            ]);

            DB::table('enrolments')
                ->whereIn('id', $group->pluck('enrolment_id'))
                ->update(['cart_id' => $cartId]);
        }
    }

    public function down(): void
    {
        // Remove backfilled carts and clear cart_id on enrolments
        $backfilledCartIds = DB::table('enrolment_carts')
            ->whereNull('selected_profile_ids')
            ->pluck('id');

        DB::table('enrolments')->whereIn('cart_id', $backfilledCartIds)->update(['cart_id' => null]);
        DB::table('enrolment_carts')->whereIn('id', $backfilledCartIds)->delete();
    }
};
