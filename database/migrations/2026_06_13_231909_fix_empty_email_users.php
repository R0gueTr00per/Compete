<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where(function ($q) {
                $q->whereNull('email')->orWhere('email', '');
            })
            ->orderBy('id')
            ->get(['id'])
            ->each(function ($user) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['email' => "unknown-{$user->id}@placeholder.invalid"]);
            });
    }

    public function down(): void
    {
        //
    }
};
