<?php

namespace App\Filament\Admin\Resources\CompetitionResource\Pages;

use App\Filament\Admin\Resources\CompetitionResource;
use App\Models\Competition;
use App\Services\DivisionAssignmentService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCompetition extends CreateRecord
{
    protected static string $resource = CompetitionResource::class;

    private bool $shouldCopyStructure = true;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->shouldCopyStructure = (bool) ($data['copy_previous_structure'] ?? true);
        unset($data['copy_previous_structure']);
        return $data;
    }

    protected function beforeCreate(): void
    {
        $locations = collect(array_values($this->data['locations'] ?? []))
            ->map(fn ($v) => strtolower(trim((string) (is_array($v) ? ($v['location'] ?? array_values($v)[0] ?? '') : $v))))
            ->filter();

        if ($locations->count() !== $locations->unique()->count()) {
            Notification::make()
                ->danger()
                ->title('Duplicate location')
                ->body('Each location must have a unique name.')
                ->send();
            $this->halt();
        }
    }

    protected function afterCreate(): void
    {
        if (! $this->shouldCopyStructure) {
            return;
        }

        $latest = Competition::where('id', '!=', $this->record->id)
            ->orderByDesc('competition_date')
            ->first();

        if (! $latest) {
            return;
        }

        $service = app(DivisionAssignmentService::class);
        $service->copyDivisionsFromCompetition($latest, $this->record);

        Notification::make()
            ->title('Structure copied from "' . $latest->name . '"')
            ->body('Age bands, rank bands, weight classes, events, and all divisions have been copied. Review and adjust the Events and Age/Rank/Weight tabs before opening enrolments.')
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
