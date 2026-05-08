<?php

namespace App\Filament\Admin\Resources\EventTypeResource\Pages;

use App\Filament\Admin\Resources\EventTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditEventType extends EditRecord
{
    protected static string $resource = EventTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (DeleteAction $action) {
                    if ($this->record->competitionEvents()->exists()) {
                        Notification::make()
                            ->title('Cannot delete this event type — it has been used in one or more competitions.')
                            ->danger()
                            ->send();

                        $action->cancel();
                    }
                }),
        ];
    }
}
