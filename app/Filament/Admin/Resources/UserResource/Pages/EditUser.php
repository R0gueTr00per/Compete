<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use Filament\Actions\DeleteAction;
use App\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Hash;

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

    protected function beforeSave(): void
    {
        $original = $this->record;

        $emailChanging  = ($this->data['email'] ?? $original->email) !== $original->email;
        $statusChanging = ($this->data['status'] ?? $original->status) !== $original->status;

        if ($emailChanging || $statusChanging) {
            $current = $this->data['current_password'] ?? null;

            if (!$current || !Hash::check($current, auth()->user()->password)) {
                Notification::make()
                    ->title('Your password is required to change the email address or status.')
                    ->danger()
                    ->send();

                throw new Halt();
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
