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
        Schema::table('competitor_profiles', function (Blueprint $table) {
            $table->index(['owner_user_id', 'profile_type', 'first_name', 'surname'], 'cp_owner_type_name_sort');
        });
    }

    public function down(): void
    {
        Schema::table('competitor_profiles', function (Blueprint $table) {
            $table->dropIndex('cp_owner_type_name_sort');
        });
    }
};
