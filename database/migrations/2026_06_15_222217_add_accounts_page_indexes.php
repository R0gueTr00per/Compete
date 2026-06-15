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
        // enrolment_carts: (user_id, status) — accounts page whereHas subquery and eager load
        // both filter WHERE user_id = ? AND status NOT IN ('draft'); FK index on user_id alone is insufficient
        $this->addIndexIfMissing('enrolment_carts', ['user_id', 'status'], 'enrolment_carts_user_id_status_index');

        // enrolment_carts: (competition_id, status) — tenant-scoped cart lookup
        $this->addIndexIfMissing('enrolment_carts', ['competition_id', 'status'], 'enrolment_carts_competition_id_status_index');

        // enrolments: (cart_id, status) — eager load filters WHERE cart_id IN (...) AND status NOT IN ('draft')
        $this->addIndexIfMissing('enrolments', ['cart_id', 'status'], 'enrolments_cart_id_status_index');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('enrolment_carts', 'enrolment_carts_user_id_status_index');
        $this->dropIndexIfExists('enrolment_carts', 'enrolment_carts_competition_id_status_index');
        $this->dropIndexIfExists('enrolments', 'enrolments_cart_id_status_index');
    }

    private function addIndexIfMissing(string $table, array $columns, string $name): void
    {
        $existing = collect(Schema::getIndexes($table))->pluck('name');
        if (! $existing->contains($name)) {
            Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
        }
    }

    private function dropIndexIfExists(string $table, string $name): void
    {
        $existing = collect(Schema::getIndexes($table))->pluck('name');
        if ($existing->contains($name)) {
            Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
        }
    }
};
