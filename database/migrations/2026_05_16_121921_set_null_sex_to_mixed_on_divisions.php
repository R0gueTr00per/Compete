<?php

use App\Models\Division;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Division::whereNull('sex')->each(function (Division $division) {
            $division->sex = 'mixed';
            $division->save(); // triggers label regeneration via model saving hook
        });
    }

    public function down(): void {}
};
