<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Illuminate\Validation\Rules\Password;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'System';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'System Administrators';
    protected static ?string $modelLabel      = 'System Administrator';
    protected static ?string $pluralModelLabel = 'System Administrators';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('system_admin') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('system_admin') ?? false;
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
                        ->unique(
                            ignoreRecord: true,
                            modifyRuleUsing: fn (\Illuminate\Validation\Rules\Unique $rule) => $rule->whereNull('organisation_id'),
                        )
                        ->validationMessages(['unique' => 'An account already exists for this email address.'])
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Select::make('status')
                        ->options(['active' => 'Active', 'inactive' => 'Inactive'])
                        ->required()
                        ->default('active')
                        ->hiddenOn('create'),
                ]),

            Section::make('Identity Verification')
                ->description('Enter your own password to confirm changes to the email address or status.')
                ->hiddenOn('create')
                ->schema([
                    TextInput::make('current_password')
                        ->label('Your password')
                        ->password()
                        ->dehydrated(false)
                        ->columnSpanFull(),
                ]),

            Section::make('Change Password')
                ->hiddenOn('create')
                ->schema([
                    Toggle::make('change_password')
                        ->label('Set a new password')
                        ->live()
                        ->dehydrated(false),

                    TextInput::make('password')
                        ->password()
                        ->rule(Password::defaults())
                        ->maxLength(255)
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null)
                        ->dehydrated(fn ($state) => filled($state))
                        ->required(fn (Get $get) => (bool) $get('change_password'))
                        ->visible(fn (Get $get) => (bool) $get('change_password'))
                        ->columnSpanFull(),

                    TextInput::make('password_confirmation')
                        ->password()
                        ->label('Confirm new password')
                        ->same('password')
                        ->requiredWith('password')
                        ->dehydrated(false)
                        ->visible(fn (Get $get) => (bool) $get('change_password'))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->role('system_admin')->with(['socialAccounts']))
            ->columns([
                TextColumn::make('full_name')
                    ->label('Name / Email')
                    ->getStateUsing(fn (User $record) => $record->getFilamentName())
                    ->description(fn (User $record) => $record->getFilamentName() !== $record->email ? $record->email : null)
                    ->searchable(query: fn ($query, $search) => $query->where('email', 'like', "%{$search}%")),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => $state === 'active' ? 'success' : 'danger')
                    ->formatStateUsing(fn (string $state) => $state === 'active' ? 'Active' : 'Inactive'),

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
            ->defaultSort('email')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending'  => 'Pending approval',
                        'active'   => 'Active',
                        'inactive' => 'Inactive',
                    ]),
            ])
            ->actions([
                ActionGroup::make([
                    EditAction::make(),

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
                ]),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
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
