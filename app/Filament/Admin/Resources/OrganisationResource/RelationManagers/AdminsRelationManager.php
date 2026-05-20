<?php

namespace App\Filament\Admin\Resources\OrganisationResource\RelationManagers;

use App\Models\OrganisationMembership;
use App\Models\User;
use App\Notifications\OrgAdminInvitationNotification;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AdminsRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';
    protected static ?string $title = 'Organisation Administrators';
    protected static ?string $icon = 'heroicon-o-shield-check';

    public static function canViewAny(): bool { return true; }

    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool { return true; }

    public function isReadOnly(): bool { return false; }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('role', 'administrator')->with('user'))
            ->columns([
                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active'    => 'success',
                        'invited'   => 'info',
                        'suspended' => 'danger',
                        default     => 'gray',
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
                        $org   = $this->getOwnerRecord();
                        $email = $data['email'];

                        $existing = OrganisationMembership::whereHas(
                            'user', fn ($q) => $q->where('email', $email)
                        )->where('organisation_id', $org->id)->first();

                        if ($existing) {
                            if ($existing->role === 'administrator') {
                                // Resend invite
                                $existing->update(['status' => 'invited', 'invited_at' => now()]);
                                $existing->user->notify(new OrgAdminInvitationNotification($existing));
                                Notification::make()->title('Invitation resent')->success()->send();
                                return;
                            }
                            // Promote existing member
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
                    ->label('Revoke Access')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (OrganisationMembership $record) {
                        $otherAdmin = OrganisationMembership::where('organisation_id', $record->organisation_id)
                            ->where('id', '!=', $record->id)
                            ->where('role', 'administrator')
                            ->where('status', 'active')
                            ->exists();

                        if (! $otherAdmin && $record->status === 'active') {
                            Notification::make()
                                ->title('Cannot remove last administrator')
                                ->danger()
                                ->send();
                            return;
                        }

                        $record->delete();
                        Notification::make()->title('Administrator removed')->success()->send();
                    }),
            ])
            ->defaultSort('joined_at', 'desc');
    }
}
