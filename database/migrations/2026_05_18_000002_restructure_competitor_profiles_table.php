<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitor_profiles', function (Blueprint $table) {
            // Drop the unique constraint on user_id (allows 1:many profiles per user)
            $table->dropUnique(['user_id']);

            // Make user_id nullable — NULL for child profiles that have no own login
            $table->foreignId('user_id')->nullable()->change();

            // The user who manages/owns this profile (parent or self)
            $table->foreignId('owner_user_id')
                ->nullable() // nullable for the backfill migration; constrained after data is set
                ->after('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Distinguish self-profile from child profiles
            $table->enum('profile_type', ['self', 'child'])->default('self')->after('owner_user_id');

            // Soft-disable without losing history
            $table->boolean('is_active')->default(true)->after('profile_complete');
        });

        Schema::table('competitor_profiles', function (Blueprint $table) {
            $table->index('owner_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('competitor_profiles', function (Blueprint $table) {
            $table->dropForeign(['owner_user_id']);
            $table->dropIndex(['owner_user_id']);
            $table->dropColumn(['owner_user_id', 'profile_type', 'is_active']);
            $table->foreignId('user_id')->nullable(false)->unique()->change();
        });
    }
};
