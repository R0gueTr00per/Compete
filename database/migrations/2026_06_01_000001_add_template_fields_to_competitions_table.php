<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->boolean('is_template')->default(false)->index()->after('status');
            $table->boolean('template_active')->default(true)->after('is_template');
        });
    }

    public function down(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropIndex(['is_template']);
            $table->dropColumn(['is_template', 'template_active']);
        });
    }
};
