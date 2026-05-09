<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrolments', function (Blueprint $table) {
            $table->string('payment_status', 20)->default('outstanding')->after('fee_calculated');
            $table->decimal('payment_amount', 8, 2)->nullable()->after('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('enrolments', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'payment_amount']);
        });
    }
};
