<?php

namespace App\Filament\Admin\Resources\CompetitionResource\Pages;

use App\Filament\Admin\Resources\CompetitionResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class ManageCompetitionConfig extends Page
{
    use InteractsWithRecord;

    protected static string $resource = CompetitionResource::class;
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string $view = 'filament.admin.pages.competition-config';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public static function getNavigationLabel(): string
    {
        return 'Configuration';
    }

    public function getTitle(): string
    {
        return $this->getRecord()->name . ' — Configuration';
    }

    public function getBreadcrumb(): string
    {
        return 'Configuration';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('events')
                ->label('Events')
                ->icon('heroicon-o-rectangle-stack')
                ->color('info')
                ->url(fn () => CompetitionResource::getUrl('events', ['record' => $this->getRecord()])),

            Action::make('back')
                ->label('Back to competition')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => CompetitionResource::getUrl('edit', ['record' => $this->getRecord()])),
        ];
    }

    protected function getViewData(): array
    {
        return [
            'ownerRecord' => $this->getRecord(),
            'pageClass'   => static::class,
        ];
    }
}
