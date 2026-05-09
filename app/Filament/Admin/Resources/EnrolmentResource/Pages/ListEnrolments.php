<?php

namespace App\Filament\Admin\Resources\EnrolmentResource\Pages;

use App\Filament\Admin\Resources\EnrolmentResource;
use App\Models\Competition;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\Url;

class ListEnrolments extends ListRecords
{
    protected static string $resource = EnrolmentResource::class;

    #[Url]
    public ?int $competition_id = null;

    public function mount(): void
    {
        parent::mount();

        if (! $this->competition_id) {
            $today = now()->toDateString();

            $competition = Competition::whereIn('status', ['open', 'running'])
                ->where('competition_date', $today)
                ->first()
                ?? Competition::whereIn('status', ['open', 'running', 'closed', 'complete'])
                    ->orderByDesc('competition_date')
                    ->first();

            if ($competition) {
                $this->competition_id = $competition->id;
            }
        }
    }

    public function updatedCompetitionId(): void
    {
        $this->resetTable();
    }

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getTableQuery()
            ->when(
                $this->competition_id,
                fn ($q) => $q->where('competition_id', $this->competition_id)
            );
    }
}
