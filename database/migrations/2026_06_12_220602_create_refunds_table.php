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
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignId('cart_id')->constrained('enrolment_carts')->cascadeOnDelete();
            $table->foreignId('enrolment_id')->nullable()->constrained('enrolments')->nullOnDelete();
            $table->foreignId('enrolment_event_id')->nullable()->constrained('enrolment_events')->nullOnDelete();
            $table->enum('type', ['event_cancelled', 'withdrawal_return', 'manual']);
            $table->decimal('amount', 10, 2);
            $table->text('reason');
            $table->string('payment_method')->default('cash');
            $table->enum('status', ['pending', 'issued', 'voided'])->default('pending');
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
