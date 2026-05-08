<?php

namespace App\Filament\Admin\Resources\EnrolmentResource\Pages;

use App\Filament\Admin\Resources\EnrolmentResource;
use App\Models\Competition;
use Filament\Resources\Pages\ListRecords;

class ListEnrolments extends ListRecords
{
    protected static string $resource = EnrolmentResource::class;

    public function mount(): void
    {
        parent::mount();

        $today = now()->toDateString();

        $competition = Competition::whereIn('status', ['open', 'running'])
            ->where('competition_date', $today)
            ->first()
            ?? Competition::whereIn('status', ['open', 'running', 'closed'])
                ->orderBy('competition_date')
                ->first();

        if ($competition) {
            $this->tableFilters['competition']['value'] = $competition->id;
        }
    }
}
