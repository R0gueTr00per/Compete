<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrolment_carts', function (Blueprint $table) {
            $table->foreignId('payment_accepted_by_user_id')
                ->nullable()
                ->after('payment_received_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('enrolment_carts', function (Blueprint $table) {
            $table->dropForeign(['payment_accepted_by_user_id']);
            $table->dropColumn('payment_accepted_by_user_id');
        });
    }
};
