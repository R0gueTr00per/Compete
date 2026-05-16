<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\EditAction;
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
        return auth()->user()?->hasRole(['system_admin', 'competition_administrator']) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('system_admin') ?? false;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['email', 'competitorProfile.first_name', 'competitorProfile.surname'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return $record->getFilamentName();
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return ['Email' => $record->email];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Account')
                ->columns(2)
                ->schema([
                    TextInput::make('email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),

                    Select::make('status')
                        ->options([
                            'active'   => 'Active',
                            'pending'  => 'Pending approval',
                            'inactive' => 'Inactive',
                        ])
                        ->required()
                        ->default('active'),
                ]),

            Section::make('Password')
                ->hiddenOn('edit')
                ->schema([
                    TextInput::make('password')
                        ->password()
                        ->required()
                        ->minLength(8)
                        ->confirmed()
                        ->dehydrated(fn ($state) => filled($state))
                        ->dehydrateStateUsing(fn ($state) => Hash::make($state)),

                    TextInput::make('password_confirmation')
                        ->password()
                        ->required()
                        ->label('Confirm password')
                        ->dehydrated(false),
                ]),

            Section::make('Role')
                ->hiddenOn('edit')
                ->schema([
                    Radio::make('role')
                        ->options([
                            'user'                      => 'User',
                            'competition_official'      => 'Competition Official',
                            'competition_administrator' => 'Competition Administrator',
                            'system_admin'              => 'System Administrator',
                        ])
                        ->descriptions([
                            'user'                      => 'Can enrol in competitions and view their own results.',
                            'competition_official'      => 'Can manage scheduling, check-in, and scoring.',
                            'competition_administrator' => 'Full access to competitions, events, and divisions.',
                            'system_admin'              => 'Full access including user management and system settings.',
                        ])
                        ->required()
                        ->default('user'),
                ]),

            Section::make('Profile')
                ->columns(2)
                ->schema([
                    TextInput::make('profile_first_name')
                        ->label('First name')
                        ->required()
                        ->maxLength(100),

                    TextInput::make('profile_surname')
                        ->label('Surname')
                        ->required()
                        ->maxLength(100),

                    DatePicker::make('profile_date_of_birth')
                        ->label('Date of birth')
                        ->required(),

                    Radio::make('profile_gender')
                        ->label('Gender')
                        ->options(['M' => 'Male', 'F' => 'Female'])
                        ->required()
                        ->inline(),

                    TextInput::make('profile_phone')
                        ->label('Phone')
                        ->tel()
                        ->maxLength(30),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['socialAccounts', 'roles', 'competitorProfile']))
            ->columns([
                TextColumn::make('full_name')
                    ->label('Name')
                    ->getStateUsing(fn (User $record) => trim($record->competitorProfile?->first_name . ' ' . $record->competitorProfile?->surname) ?: null)
                    ->placeholder('—')
                    ->description(fn (User $record) => $record->email)
                    ->searchable(query: fn ($query, $search) => $query->where(fn ($q) => $q
                        ->whereHas('competitorProfile', fn ($q2) => $q2
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('surname', 'like', "%{$search}%")
                        )
                        ->orWhere('email', 'like', "%{$search}%")
                    )),

                TextColumn::make('email')
                    ->sortable()
                    ->visibleFrom('sm'),

                TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'system_admin'              => 'System Administrator',
                        'competition_administrator' => 'Competition Administrator',
                        'competition_official'      => 'Competition Official',
                        'user'                      => 'User',
                        default                     => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'system_admin'              => 'danger',
                        'competition_administrator' => 'warning',
                        'competition_official'      => 'info',
                        'user'                      => 'gray',
                        default                     => 'gray',
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active'   => 'success',
                        'pending'  => 'warning',
                        'inactive' => 'danger',
                        default    => 'gray',
                    }),

                TextColumn::make('profile_status')
                    ->label('Profile')
                    ->getStateUsing(fn (User $record) => $record->competitorProfile?->profile_complete ? 'Complete' : ($record->competitorProfile ? 'Incomplete' : 'None'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Complete'   => 'success',
                        'Incomplete' => 'warning',
                        'None'       => 'gray',
                    })
                    ->visibleFrom('sm'),

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
                    ->color('gray')
                    ->visibleFrom('sm'),

                TextColumn::make('last_login_at')
                    ->label('Last login')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->placeholder('Never')
                    ->visibleFrom('sm'),

                TextColumn::make('created_at')
                    ->label('Registered')
                    ->date('d M Y')
                    ->sortable()
                    ->visibleFrom('sm'),
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
                        'user'                      => 'User',
                        'competition_official'      => 'Competition Official',
                        'competition_administrator' => 'Competition Administrator',
                        'system_admin'              => 'System Administrator',
                    ])
                    ->query(fn ($query, $data) => $data['value']
                        ? $query->role($data['value'])
                        : $query
                    ),
            ])
            ->actions([
                ActionGroup::make([
                    EditAction::make(),

                    Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (User $record) => $record->status === 'pending')
                        ->action(function (User $record) {
                            $record->update(['status' => 'active']);
                            if (! $record->hasAnyRole(['competition_administrator', 'system_admin', 'competition_official'])) {
                                $record->assignRole('user');
                            }
                            Notification::make()->title('User approved.')->success()->send();
                        }),

                    Action::make('deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (User $record) =>
                            $record->status === 'active'
                            && auth()->user()?->hasRole('system_admin')
                            && auth()->id() !== $record->id
                        )
                        ->action(function (User $record) {
                            if ($record->hasRole('system_admin')) {
                                $remaining = User::role('system_admin')
                                    ->where('status', 'active')
                                    ->count();

                                if ($remaining <= 1) {
                                    Notification::make()
                                        ->title('Cannot deactivate the last system admin.')
                                        ->danger()
                                        ->send();
                                    return;
                                }
                            }

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
                            Radio::make('role')
                                ->label('Role')
                                ->options([
                                    'user'                      => 'User',
                                    'competition_official'      => 'Competition Official',
                                    'competition_administrator' => 'Competition Administrator',
                                    'system_admin'              => 'System Administrator',
                                ])
                                ->descriptions([
                                    'user'                      => 'Can enrol in competitions and view their own results.',
                                    'competition_official'      => 'Can manage scheduling, check-in, and scoring.',
                                    'competition_administrator' => 'Full access to competitions, events, and divisions.',
                                    'system_admin'              => 'Full access including user management and system settings.',
                                ])
                                ->required(),
                        ])
                        ->fillForm(fn (User $record) => [
                            'role' => $record->roles->first()?->name,
                        ])
                        ->action(function (User $record, array $data) {
                            $isLeavingSysAdmin = $record->hasRole('system_admin')
                                && $data['role'] !== 'system_admin';

                            if ($isLeavingSysAdmin) {
                                $remaining = User::role('system_admin')
                                    ->where('status', 'active')
                                    ->count();

                                if ($remaining <= 1) {
                                    Notification::make()
                                        ->title('Cannot remove the last system admin.')
                                        ->danger()
                                        ->send();
                                    return;
                                }
                            }

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

    public static function getRelationManagers(): array
    {
        return [
            UserResource\RelationManagers\EnrolmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
