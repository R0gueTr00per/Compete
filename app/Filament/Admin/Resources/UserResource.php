<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'System';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Users';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(['system_admin', 'admin']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['socialAccounts', 'roles', 'competitorProfile']))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('competitorProfile.surname')
                    ->label('Surname')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'system_admin' => 'danger',
                        'admin'        => 'warning',
                        'contributor'  => 'info',
                        'competitor'   => 'gray',
                        default        => 'gray',
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active'   => 'success',
                        'pending'  => 'warning',
                        'inactive' => 'danger',
                        default    => 'gray',
                    }),

                TextColumn::make('auth_type')
                    ->label('Auth')
                    ->getStateUsing(function (User $record): string {
                        $providers = $record->socialAccounts->pluck('provider')->map('ucfirst')->toArray();
                        if (empty($providers)) {
                            return 'Password';
                        }
                        return implode(', ', $providers);
                    })
                    ->badge()
                    ->color('gray'),

                TextColumn::make('created_at')
                    ->label('Registered')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending'  => 'Pending approval',
                        'active'   => 'Active',
                        'inactive' => 'Inactive',
                    ]),

                SelectFilter::make('role')
                    ->label('Role')
                    ->options([
                        'competitor'   => 'Competitor',
                        'contributor'  => 'Contributor',
                        'admin'        => 'Admin',
                        'system_admin' => 'System Admin',
                    ])
                    ->query(fn ($query, $data) => $data['value']
                        ? $query->role($data['value'])
                        : $query
                    ),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (User $record) => $record->status === 'pending')
                        ->action(function (User $record) {
                            $record->update(['status' => 'active']);
                            if (! $record->hasAnyRole(['admin', 'system_admin', 'contributor'])) {
                                $record->assignRole('competitor');
                            }
                            Notification::make()->title('User approved.')->success()->send();
                        }),

                    Action::make('deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (User $record) => $record->status === 'active')
                        ->action(function (User $record) {
                            $record->update(['status' => 'inactive']);
                            Notification::make()->title('User deactivated.')->success()->send();
                        }),

                    Action::make('activate')
                        ->label('Reactivate')
                        ->icon('heroicon-o-arrow-path')
                        ->color('gray')
                        ->visible(fn (User $record) => $record->status === 'inactive')
                        ->action(function (User $record) {
                            $record->update(['status' => 'active']);
                            Notification::make()->title('User reactivated.')->success()->send();
                        }),

                    Action::make('assignRole')
                        ->label('Change role')
                        ->icon('heroicon-o-shield-check')
                        ->color('warning')
                        ->form([
                            Select::make('role')
                                ->label('Role')
                                ->options([
                                    'competitor'   => 'Competitor',
                                    'contributor'  => 'Contributor',
                                    'admin'        => 'Admin',
                                    'system_admin' => 'System Admin',
                                ])
                                ->required(),
                        ])
                        ->fillForm(fn (User $record) => [
                            'role' => $record->roles->first()?->name,
                        ])
                        ->action(function (User $record, array $data) {
                            $record->syncRoles([$data['role']]);
                            Notification::make()->title('Role updated.')->success()->send();
                        })
                        ->visible(fn () => auth()->user()?->hasRole('system_admin')),

                    Action::make('resetPassword')
                        ->label('Reset password')
                        ->icon('heroicon-o-key')
                        ->color('gray')
                        ->form([
                            TextInput::make('password')
                                ->label('New password')
                                ->password()
                                ->required()
                                ->minLength(8)
                                ->confirmed(),

                            TextInput::make('password_confirmation')
                                ->label('Confirm password')
                                ->password()
                                ->required(),
                        ])
                        ->visible(fn (User $record) => auth()->user()?->hasRole('system_admin'))
                        ->action(function (User $record, array $data) {
                            $record->update(['password' => Hash::make($data['password'])]);
                            Notification::make()->title('Password reset.')->success()->send();
                        }),
                ]),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
        ];
    }
}
