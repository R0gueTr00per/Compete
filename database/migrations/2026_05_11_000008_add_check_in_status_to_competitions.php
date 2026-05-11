<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->enum('status', ['draft', 'open', 'closed', 'check_in', 'running', 'complete'])
                ->default('draft')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->enum('status', ['draft', 'open', 'closed', 'running', 'complete'])
                ->default('draft')
                ->change();
        });
    }
};
