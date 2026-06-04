<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('enrolment_carts', function (Blueprint $table) {
            $table->decimal('total_amount', 10, 2)->nullable()->after('status');
            $table->decimal('fee_first_rate', 10, 2)->nullable()->after('total_amount');
            $table->decimal('fee_additional_rate', 10, 2)->nullable()->after('fee_first_rate');
            $table->decimal('fee_official_first_rate', 10, 2)->nullable()->after('fee_additional_rate');
            $table->decimal('fee_official_additional_rate', 10, 2)->nullable()->after('fee_official_first_rate');
            $table->decimal('late_surcharge_rate', 10, 2)->nullable()->after('fee_official_additional_rate');
            $table->timestamp('submitted_at')->nullable()->after('late_surcharge_rate');
        });
    }

    public function down(): void
    {
        Schema::table('enrolment_carts', function (Blueprint $table) {
            $table->dropColumn(['total_amount', 'fee_first_rate', 'fee_additional_rate', 'fee_official_first_rate', 'fee_official_additional_rate', 'late_surcharge_rate', 'submitted_at']);
        });
    }
};
