<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add payment tracking to carts
        Schema::table('enrolment_carts', function (Blueprint $table) {
            $table->string('payment_status', 20)->default('outstanding')->after('payment_method');
            $table->decimal('payment_amount', 8, 2)->nullable()->after('payment_status');
            $table->timestamp('payment_received_at')->nullable()->after('payment_amount');
        });

        // Backfill: if any non-withdrawn active enrolment in a cart was paid, mark the cart paid
        DB::statement("
            UPDATE enrolment_carts
            SET
                payment_status      = 'received',
                payment_amount      = (
                    SELECT SUM(COALESCE(e.payment_amount, e.fee_calculated))
                    FROM enrolments e
                    WHERE e.cart_id = enrolment_carts.id
                      AND e.payment_status = 'received'
                      AND e.deleted_at IS NULL
                ),
                payment_received_at = (
                    SELECT MAX(e.payment_received_at)
                    FROM enrolments e
                    WHERE e.cart_id = enrolment_carts.id
                      AND e.payment_status = 'received'
                      AND e.deleted_at IS NULL
                )
            WHERE id IN (
                SELECT DISTINCT cart_id
                FROM enrolments
                WHERE payment_status = 'received'
                  AND deleted_at IS NULL
            )
        ");

        // Remove per-enrolment payment tracking
        Schema::table('enrolments', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'payment_amount', 'payment_received_at']);
        });
    }

    public function down(): void
    {
        Schema::table('enrolments', function (Blueprint $table) {
            $table->string('payment_status', 20)->default('outstanding')->after('fee_calculated');
            $table->decimal('payment_amount', 8, 2)->nullable()->after('payment_status');
            $table->timestamp('payment_received_at')->nullable()->after('payment_amount');
        });

        Schema::table('enrolment_carts', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'payment_amount', 'payment_received_at']);
        });
    }
};
