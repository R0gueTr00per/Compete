<?php

namespace App\Filament\Admin\Resources\OrganisationResource\Pages;

use App\Filament\Admin\Resources\OrganisationResource;
use App\Models\OrganisationMembership;
use App\Models\User;
use App\Notifications\OrgAdminInvitationNotification;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateOrganisation extends CreateRecord
{
    protected static string $resource = OrganisationResource::class;

    private ?string $initialAdminEmail = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->initialAdminEmail = $data['initial_admin_email'] ?? null;
        unset($data['initial_admin_email']);
        $data['created_by_user_id'] = auth()->id();
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        if (! $this->initialAdminEmail) {
            return;
        }

        $org = $this->record;

        $user = User::firstOrCreate(
            ['email' => $this->initialAdminEmail, 'organisation_id' => $org->id],
            ['status' => 'pending']
        );

        $membership = OrganisationMembership::create([
            'organisation_id'    => $org->id,
            'user_id'            => $user->id,
            'role'               => 'administrator',
            'status'             => 'invited',
            'invited_by_user_id' => auth()->id(),
            'invited_at'         => now(),
        ]);

        $user->notify(new OrgAdminInvitationNotification($membership));

        Notification::make()
            ->title('Invitation sent')
            ->body("An invitation has been sent to {$this->initialAdminEmail}.")
            ->success()
            ->send();
    }
}
