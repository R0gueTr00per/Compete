<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('official_roles', function (Blueprint $table) {
            $table->boolean('can_access_accounts')->default(false)->after('can_access_scoring');
            $table->boolean('can_access_results')->default(false)->after('can_access_accounts');
        });
    }

    public function down(): void
    {
        Schema::table('official_roles', function (Blueprint $table) {
            $table->dropColumn(['can_access_accounts', 'can_access_results']);
        });
    }
};
