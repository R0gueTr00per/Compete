<?php

namespace App\Filament\OrgAdmin\Resources\MemberResource\Pages;

use App\Filament\OrgAdmin\Resources\MemberResource;
use App\Models\OrganisationMembership;
use App\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditMember extends EditRecord
{
    protected static string $resource = MemberResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['user']['email'] = $this->record->user?->email;
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var OrganisationMembership $record */
        $record = $this->record;

        if (isset($data['user']['email'])) {
            $record->user->update(['email' => $data['user']['email']]);
        }
        unset($data['user']);

        $isCurrentlyActiveAdmin = $record->role === 'administrator' && $record->status === 'active';
        $willBeActiveAdmin      = ($data['role'] ?? $record->role) === 'administrator'
                                  && ($data['status'] ?? $record->status) === 'active';

        if ($isCurrentlyActiveAdmin && ! $willBeActiveAdmin) {
            $hasOtherActiveAdmin = OrganisationMembership::where('organisation_id', $record->organisation_id)
                ->where('id', '!=', $record->id)
                ->where('role', 'administrator')
                ->where('status', 'active')
                ->exists();

            if (! $hasOtherActiveAdmin) {
                Notification::make()
                    ->title('Cannot remove last administrator')
                    ->body('This organisation must have at least one active administrator.')
                    ->danger()
                    ->persistent()
                    ->send();

                $this->halt();
            }
        }

        return $data;
    }
}
