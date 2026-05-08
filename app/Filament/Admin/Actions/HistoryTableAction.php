<?php
namespace App\Filament\Admin\Actions;

use Filament\Tables\Actions\Action;
use Spatie\Activitylog\Models\Activity;

class HistoryTableAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'history';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this
            ->label('History')
            ->icon('heroicon-o-clock')
            ->color('gray')
            ->modalHeading('Change History')
            ->modalContent(fn ($record) => view(
                'filament.admin.partials.history-modal',
                ['activities' => Activity::forSubject($record)->with('causer')->latest()->get()]
            ))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }
}
