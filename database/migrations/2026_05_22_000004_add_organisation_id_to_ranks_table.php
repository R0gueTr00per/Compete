<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ranks', function (Blueprint $table) {
            $table->foreignId('organisation_id')->nullable()->after('id')->constrained('organisations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ranks', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Organisation::class);
            $table->dropColumn('organisation_id');
        });
    }
};
