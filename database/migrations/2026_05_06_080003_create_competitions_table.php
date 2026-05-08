<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('competition_date');
            $table->time('start_time');
            $table->time('checkin_time')->nullable();
            $table->string('location_name')->nullable();
            $table->text('location_address')->nullable();
            $table->date('enrolment_due_date')->nullable();
            $table->decimal('fee_first_event', 6, 2)->default(38.00);
            $table->decimal('fee_additional_event', 6, 2)->default(12.00);
            $table->decimal('late_surcharge', 6, 2)->default(15.00);
            $table->enum('status', ['draft', 'open', 'closed', 'running', 'complete'])->default('draft');
            $table->foreignId('copied_from_id')->nullable()->constrained('competitions')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitions');
    }
};
