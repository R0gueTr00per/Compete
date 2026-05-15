<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dojos', function (Blueprint $table) {
            $table->foreignId('instructor_id')->nullable()->constrained('users')->nullOnDelete()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('dojos', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\User::class, 'instructor_id');
            $table->dropColumn('instructor_id');
        });
    }
};
