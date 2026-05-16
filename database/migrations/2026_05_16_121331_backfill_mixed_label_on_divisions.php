<?php

use App\Models\AgeBand;
use App\Models\Division;
use App\Models\RankBand;
use App\Models\WeightClass;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Division::where('sex', 'mixed')->each(function (Division $division) {
            $parts = array_filter([
                $division->age_band_id     ? AgeBand::find($division->age_band_id)?->label          : null,
                $division->rank_band_id    ? RankBand::find($division->rank_band_id)?->label         : null,
                $division->weight_class_id ? WeightClass::find($division->weight_class_id)?->full_label : null,
                'Mixed',
            ]);
            if (! empty($parts)) {
                $division->updateQuietly(['label' => implode(' / ', $parts)]);
            }
        });
    }

    public function down(): void {}
};
