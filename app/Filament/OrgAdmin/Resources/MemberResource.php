<?php

namespace App\Filament\OrgAdmin\Resources;

use App\Filament\OrgAdmin\Resources\MemberResource\Pages;
use App\Models\OrganisationMembership;
use App\Models\User;
use App\Notifications\AccountApprovedNotification;
use App\Notifications\AccountCreatedNotification;
use App\Notifications\OrgAdminInvitationNotification;
use Filament\Forms\Components\Section;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MemberResource extends Resource
{
    protected static ?string $model = OrganisationMembership::class;
    protected static ?string $navigationIcon  = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'System';
    protected static ?int    $navigationSort  = 1;
    protected static ?string $navigationLabel = 'Users';
    protected static ?string $modelLabel      = 'User';
    protected static ?string $pluralModelLabel = 'Users';

    public static function canAccess(): bool
    {
        $tenant = app('tenant');
        if (! $tenant) return true;
        return ! (auth()->user()?->isOrgOfficial($tenant) ?? false);
    }

    public static function canCreate(): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('organisation_id', app('tenant')?->id)
            ->with('user');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make()->schema([
                TextInput::make('user.email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->rules(fn ($record) => [
                        Rule::unique('users', 'email')
                            ->where('organisation_id', app('tenant')?->id)
                            ->ignore($record?->user_id),
                    ]),

                Select::make('role')
                    ->options([
                        'competitor'    => 'Competitor',
                        'official'      => 'Official',
                        'administrator' => 'Administrator',
                    ])
                    ->required(),

                Select::make('status')
                    ->options([
                        'active'    => 'Active',
                        'invited'   => 'Invited',
                        'pending'   => 'Pending approval',
                        'suspended' => 'Suspended',
                    ])
                    ->required(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('role')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'administrator' => 'warning',
                        'official'      => 'info',
                        default         => 'gray',
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active'    => 'success',
                        'invited'   => 'info',
                        'pending'   => 'warning',
                        'suspended' => 'danger',
                        default     => 'gray',
                    }),

                TextColumn::make('joined_at')
                    ->label('Joined')
                    ->date()
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('add_user')
                    ->label('Add User')
                    ->icon('heroicon-o-user-plus')
                    ->form([
                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->required(),

                        Select::make('role')
                            ->label('Role')
                            ->options([
                                'competitor'    => 'Competitor',
                                'official'      => 'Official',
                                'administrator' => 'Administrator',
                            ])
                            ->default('competitor')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $org   = app('tenant');
                        $email = $data['email'];
                        $role  = $data['role'];

                        $existing = OrganisationMembership::whereHas(
                            'user', fn ($q) => $q->where('email', $email)
                        )->where('organisation_id', $org->id)->first();

                        if ($existing) {
                            Notification::make()
                                ->title('Already a member')
                                ->body("{$email} is already a member with the {$existing->role} role. Use the edit action on their row to change their role.")
                                ->warning()
                                ->persistent()
                                ->send();
                            return;
                        }

                        $user = User::firstOrCreate(
                            ['email' => $email, 'organisation_id' => $org->id],
                            ['status' => 'pending']
                        );

                        $membership = OrganisationMembership::create([
                            'organisation_id'    => $org->id,
                            'user_id'            => $user->id,
                            'role'               => $role,
                            'status'             => 'invited',
                            'invited_by_user_id' => auth()->id(),
                            'invited_at'         => now(),
                        ]);

                        $user->notify(new OrgAdminInvitationNotification($membership));
                        Notification::make()->title('Invitation sent')->success()->send();
                    }),
            ])
            ->actions([
                EditAction::make(),
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (OrganisationMembership $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Approve registration')
                    ->modalDescription('This will activate the user\'s account and grant them access to the portal.')
                    ->action(function (OrganisationMembership $record) {
                        $record->user->update(['status' => 'active']);
                        $record->update(['status' => 'active', 'joined_at' => now()]);
                        $record->user->notify(new AccountApprovedNotification($record->organisation));
                        Notification::make()->title('Member approved')->success()->send();
                    }),
                Action::make('decline')
                    ->label('Decline')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (OrganisationMembership $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Decline registration')
                    ->modalDescription('This will remove their pending registration. Their user account will remain but they will not have access to this organisation.')
                    ->action(function (OrganisationMembership $record) {
                        $record->delete();
                        Notification::make()->title('Registration declined')->warning()->send();
                    }),
                ActionGroup::make([
                    Action::make('resend_invite')
                        ->label('Resend Invite')
                        ->icon('heroicon-o-envelope')
                        ->color('gray')
                        ->visible(fn (OrganisationMembership $record) => $record->status === 'invited')
                        ->action(function (OrganisationMembership $record) {
                            $record->update(['invited_at' => now()]);
                            $record->user->notify(new OrgAdminInvitationNotification($record));
                            Notification::make()->title('Invitation resent')->success()->send();
                        }),
                    Action::make('resend_setup_email')
                        ->label('Resend account setup email')
                        ->icon('heroicon-o-envelope')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalDescription('A new account setup link will be emailed to this user.')
                        ->visible(fn (OrganisationMembership $record) => $record->status === 'active')
                        ->action(function (OrganisationMembership $record) {
                            $token = Password::broker()->createToken($record->user);
                            $record->user->notify(new AccountCreatedNotification($token, app('tenant')));
                            Notification::make()->title('Account setup email sent')->success()->send();
                        }),
                    Action::make('send_password_reset')
                        ->label('Send password reset email')
                        ->icon('heroicon-o-key')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalDescription('A password reset link (valid for 60 minutes) will be emailed to this user.')
                        ->visible(fn (OrganisationMembership $record) => $record->status === 'active')
                        ->action(function (OrganisationMembership $record) {
                            Password::sendResetLink(['email' => $record->user->email]);
                            Notification::make()->title('Password reset email sent')->success()->send();
                        }),
                    Action::make('suspend')
                        ->label('Suspend')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->visible(fn (OrganisationMembership $record) => $record->status === 'active')
                        ->requiresConfirmation()
                        ->action(function (OrganisationMembership $record) {
                            if (! static::hasOtherActiveAdmin($record)) {
                                Notification::make()
                                    ->title('Cannot suspend last administrator')
                                    ->body('This organisation must have at least one active administrator.')
                                    ->danger()
                                    ->send();
                                return;
                            }
                            $record->update(['status' => 'suspended']);
                            Notification::make()->title('Member suspended')->warning()->send();
                        }),
                    Action::make('restore')
                        ->label('Restore Access')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (OrganisationMembership $record) => $record->status === 'suspended')
                        ->action(function (OrganisationMembership $record) {
                            $record->update(['status' => 'active']);
                            Notification::make()->title('Access restored')->success()->send();
                        }),
                    Action::make('remove')
                        ->label('Delete User')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Delete user')
                        ->modalDescription('This will permanently delete the user and their profiles from this organisation. This cannot be undone.')
                        ->action(function (OrganisationMembership $record) {
                            if (! static::hasOtherActiveAdmin($record)) {
                                Notification::make()
                                    ->title('Cannot delete last administrator')
                                    ->body('This organisation must have at least one active administrator.')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            $hasEnrolments = \App\Models\CompetitorProfile::where('owner_user_id', $record->user_id)
                                ->where('organisation_id', $record->organisation_id)
                                ->whereHas('enrolments')
                                ->exists();

                            if ($hasEnrolments) {
                                Notification::make()
                                    ->title('Cannot delete user')
                                    ->body('This user has competition enrolment history. Suspend them instead.')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            \App\Models\CompetitorProfile::where('owner_user_id', $record->user_id)
                                ->where('organisation_id', $record->organisation_id)
                                ->delete();

                            $user = $record->user;
                            $record->delete();
                            $user?->delete();

                            Notification::make()->title('User deleted')->success()->send();
                        }),
                ])->tooltip('More actions'),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options([
                        'competitor'    => 'Competitor',
                        'official'      => 'Official',
                        'administrator' => 'Administrator',
                    ]),

                SelectFilter::make('status')
                    ->options([
                        'active'    => 'Active',
                        'invited'   => 'Invited',
                        'pending'   => 'Pending approval',
                        'suspended' => 'Suspended',
                    ]),
            ])
            ->defaultSort('joined_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\OrgAdmin\Resources\MemberResource\RelationManagers\ProfilesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMembers::route('/'),
            'edit'  => Pages\EditMember::route('/{record}/edit'),
        ];
    }

    private static function hasOtherActiveAdmin(OrganisationMembership $record): bool
    {
        if ($record->role !== 'administrator') {
            return true;
        }

        return OrganisationMembership::where('organisation_id', $record->organisation_id)
            ->where('id', '!=', $record->id)
            ->where('role', 'administrator')
            ->where('status', 'active')
            ->exists();
    }
}
