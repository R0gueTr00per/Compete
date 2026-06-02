<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrolments', function (Blueprint $table) {
            $table->foreignId('cart_id')->nullable()->after('id')
                ->constrained('enrolment_carts')->nullOnDelete();
            $table->timestamp('withdrawn_at')->nullable()->after('status');
            $table->string('withdrawal_reason')->nullable()->after('withdrawn_at');
            $table->boolean('refund_requested')->default(false)->after('withdrawal_reason');
        });
    }

    public function down(): void
    {
        Schema::table('enrolments', function (Blueprint $table) {
            $table->dropForeign(['cart_id']);
            $table->dropColumn(['cart_id', 'withdrawn_at', 'withdrawal_reason', 'refund_requested']);
        });
    }
};
