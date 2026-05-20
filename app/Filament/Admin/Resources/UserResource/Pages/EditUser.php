<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => auth()->user()?->hasRole('system_admin') && auth()->id() !== $this->record->id)
                ->before(function (DeleteAction $action) {
                    if ($this->record->enrolments()->exists()) {
                        Notification::make()
                            ->title('Cannot delete a user with enrolment history. Deactivate them instead.')
                            ->danger()
                            ->send();
                        $action->halt();
                    }
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
