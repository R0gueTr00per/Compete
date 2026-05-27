<?php

namespace App\Filament\Admin\Resources\OrganisationResource\Pages;

use App\Filament\Admin\Resources\OrganisationResource;
use App\Models\OrganisationMembership;
use App\Models\User;
use App\Notifications\OrgAdminInvitationNotification;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use App\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class EditOrganisation extends EditRecord implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = OrganisationResource::class;
    protected static string $view = 'filament.admin.resources.organisation.pages.edit-organisation';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                OrganisationMembership::query()
                    ->where('organisation_id', $this->record->id)
                    ->where('role', 'administrator')
                    ->with('user')
            )
            ->heading('Administrators')
            ->columns([
                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active'  => 'success',
                        'invited' => 'info',
                        default   => 'gray',
                    }),

                TextColumn::make('invited_at')
                    ->label('Invited')
                    ->date()
                    ->sortable(),

                TextColumn::make('joined_at')
                    ->label('Joined')
                    ->date()
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('invite_admin')
                    ->label('Invite Administrator')
                    ->icon('heroicon-o-envelope')
                    ->form([
                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $org   = $this->record;
                        $email = $data['email'];

                        $existing = OrganisationMembership::whereHas(
                            'user', fn ($q) => $q->where('email', $email)
                        )->where('organisation_id', $org->id)->first();

                        if ($existing) {
                            if ($existing->role === 'administrator') {
                                $existing->update(['status' => 'invited', 'invited_at' => now()]);
                                $existing->user->notify(new OrgAdminInvitationNotification($existing));
                                Notification::make()->title('Invitation resent')->success()->send();
                                return;
                            }
                            $existing->update(['role' => 'administrator', 'status' => 'invited', 'invited_at' => now()]);
                            $existing->user->notify(new OrgAdminInvitationNotification($existing));
                            Notification::make()->title('Role updated and invitation sent')->success()->send();
                            return;
                        }

                        $user = User::firstOrCreate(
                            ['email' => $email, 'organisation_id' => $org->id],
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
                        Notification::make()->title('Invitation sent')->success()->send();
                    }),
            ])
            ->actions([
                Action::make('resend')
                    ->label('Resend Invite')
                    ->icon('heroicon-o-envelope')
                    ->color('gray')
                    ->visible(fn (OrganisationMembership $record) => $record->status === 'invited')
                    ->action(function (OrganisationMembership $record) {
                        $record->update(['invited_at' => now()]);
                        $record->user->notify(new OrgAdminInvitationNotification($record));
                        Notification::make()->title('Invitation resent')->success()->send();
                    }),

                Action::make('revoke')
                    ->label('Revoke')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (OrganisationMembership $record) {
                        $otherActive = OrganisationMembership::where('organisation_id', $record->organisation_id)
                            ->where('id', '!=', $record->id)
                            ->where('role', 'administrator')
                            ->where('status', 'active')
                            ->exists();

                        if (! $otherActive && $record->status === 'active') {
                            Notification::make()->title('Cannot remove the last active administrator')->danger()->send();
                            return;
                        }

                        $record->delete();
                        Notification::make()->title('Administrator removed')->success()->send();
                    }),
            ]);
    }
}
