<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisation_annual_fee_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->date('due_date');
            $table->decimal('amount', 8, 2);
            $table->dateTime('dismissed_at')->nullable();
            $table->timestamps();

            $table->unique(['organisation_id', 'due_date'], 'oafr_org_due_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisation_annual_fee_reminders');
    }
};
