<?php

namespace App\Filament\Admin\Resources\CompetitorResource\Pages;

use App\Filament\Admin\Resources\CompetitorResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditCompetitor extends EditRecord
{
    protected static string $resource = CompetitorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewEnrolments')
                ->label('View enrolments')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('gray')
                ->url(fn () => route('filament.admin.resources.enrolments.index', [
                    'tableFilters[competition][value]' => '',
                ]))
                ->openUrlInNewTab(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
