<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrolment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrolment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_event_id')->constrained();
            $table->foreignId('division_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('partner_enrolment_event_id')->nullable()->constrained('enrolment_events')->nullOnDelete();
            $table->boolean('yakusuko_complete')->default(false);
            $table->boolean('checked_in')->default(false);
            $table->timestamp('checked_in_at')->nullable();
            $table->decimal('weight_confirmed_kg', 5, 2)->nullable();
            $table->boolean('removed')->default(false);
            $table->timestamp('removed_at')->nullable();
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('removal_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrolment_events');
    }
};
