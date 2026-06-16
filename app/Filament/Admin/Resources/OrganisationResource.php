<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\OrganisationResource\Pages;
use App\Models\Organisation;
use App\Models\OrganisationMembership;
use App\Models\User;
use App\Notifications\OrgAdminInvitationNotification;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;


class OrganisationResource extends Resource
{
    protected static ?string $model = Organisation::class;
    protected static ?string $navigationIcon  = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = 'System';
    protected static ?int    $navigationSort  = 1;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('system_admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make()->schema([
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(150),

                TextInput::make('slug')
                    ->label('Sub-domain')
                    ->helperText(fn () => config('app.scheme') . '://{sub-domain}.' . config('app.domain', 'kompetic.com'))
                    ->required()
                    ->minLength(3)
                    ->maxLength(50)
                    ->unique(Organisation::class, 'slug', ignoreRecord: true)
                    ->rules([
                        'alpha_dash',
                        new class implements \Illuminate\Contracts\Validation\ValidationRule {
                            public function validate(string $attribute, mixed $value, \Closure $fail): void {
                                $reserved = ['www', 'mail', 'api', 'admin', 'app', 'manage', 'portal', 'static',
                                             'cdn', 'help', 'support', 'ftp', 'smtp', 'pop', 'imap', 'ns', 'ns1',
                                             'ns2', 'dev', 'staging', 'test', 'beta', 'kompetic', 'preprod'];
                                if (in_array(strtolower($value), $reserved, true)) {
                                    $fail("The sub-domain \"{$value}\" is reserved and cannot be used.");
                                }
                            }
                        },
                    ])
                    ->validationMessages([
                        'min_digits' => 'The sub-domain must be at least 3 characters.',
                        'unique'     => 'This sub-domain is already in use.',
                        'alpha_dash' => 'The sub-domain may only contain letters, numbers, dashes, and underscores.',
                    ])
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, ?string $state, string $operation) {
                        if ($operation === 'create') {
                            $set('slug', strtolower($state ?? ''));
                        }
                    }),

                Select::make('status')
                    ->options(['active' => 'Active', 'inactive' => 'Inactive'])
                    ->default('active')
                    ->required(),

                \Filament\Forms\Components\TextInput::make('platform_fee')
                    ->label('Platform service fee')
                    ->helperText('Fee charged per registration on top of the organiser\'s competition fee. Set to 0 to disable.')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->suffix(fn (?Organisation $record) => $record?->currency ?: 'AUD')
                    ->default(0),

                \Filament\Forms\Components\Toggle::make('competitor_logins_locked')
                    ->label('Lock competitor portal logins')
                    ->helperText('Blocks competitor logins only — Org Admins can still log in. Use when payments are overdue but you don\'t want to disable the whole Org.'),
            ]),

            Section::make('Billing')
                ->schema([
                    \Filament\Forms\Components\TextInput::make('annual_fee')
                        ->label('Annual platform subscription fee')
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->suffix(fn (?Organisation $record) => $record?->currency ?: 'AUD'),

                    \Filament\Forms\Components\DatePicker::make('annual_fee_anniversary_date')
                        ->label('Annual fee anniversary date')
                        ->helperText('The annual fee is billed in this month on the monthly invoice run.'),

                    \Filament\Forms\Components\Toggle::make('gst_registered')
                        ->label('GST registered')
                        ->helperText('Applies GST to this Org\'s invoices from Kompetic and to competitor registration totals.')
                        ->live(),

                    \Filament\Forms\Components\TextInput::make('gst_rate')
                        ->label('GST rate')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->step(0.01)
                        ->suffix('%')
                        ->visible(fn (Get $get) => (bool) $get('gst_registered')),
                ]),

            Section::make('Initial Administrator')
                ->description('An invitation email with a magic link will be sent to this address.')
                ->visibleOn('create')
                ->schema([
                    TextInput::make('initial_admin_email')
                        ->label('Admin email address')
                        ->email()
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->maxLength(255),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label('Sub-domain')
                    ->formatStateUsing(fn (string $state) => $state . '.' . config('app.domain', 'kompetic.com'))
                    ->color('gray')
                    ->searchable()
                    ->visibleFrom('sm'),

                TextColumn::make('memberships_count')
                    ->label('Users')
                    ->counts('memberships')
                    ->sortable()
                    ->visibleFrom('sm'),

                TextColumn::make('competitor_profiles_count')
                    ->label('Profiles')
                    ->counts('competitorProfiles')
                    ->sortable()
                    ->visibleFrom('sm'),

                TextColumn::make('competitions_count')
                    ->label('Competitions')
                    ->counts(['competitions' => fn ($q) => $q->where('is_template', false)])
                    ->sortable()
                    ->visibleFrom('sm'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => $state === 'active' ? 'success' : 'gray'),
            ])
            ->actions([
                EditAction::make(),
                ActionGroup::make([
                    Action::make('invite_admin')
                        ->label('Invite / Re-invite Admin')
                        ->icon('heroicon-o-envelope')
                        ->form([
                            TextInput::make('email')
                                ->label('Admin email address')
                                ->email()
                                ->required(),
                        ])
                        ->action(function (Organisation $record, array $data) {
                            $email = $data['email'];
                            $existing = OrganisationMembership::whereHas(
                                'user', fn ($q) => $q->where('email', $email)
                            )->where('organisation_id', $record->id)->first();

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
                                ['email' => $email, 'organisation_id' => $record->id],
                                ['status' => 'pending']
                            );

                            $membership = OrganisationMembership::create([
                                'organisation_id'    => $record->id,
                                'user_id'            => $user->id,
                                'role'               => 'administrator',
                                'status'             => 'invited',
                                'invited_by_user_id' => auth()->id(),
                                'invited_at'         => now(),
                            ]);

                            $user->notify(new OrgAdminInvitationNotification($membership));
                            Notification::make()->title('Invitation sent')->success()->send();
                        }),
                    Action::make('open_portal')
                        ->label('Open Portal')
                        ->icon('heroicon-o-globe-alt')
                        ->url(fn (Organisation $record) => config('app.scheme') . '://' . $record->slug . '.' . config('app.domain', 'kompetic.com') . '/portal')
                        ->openUrlInNewTab(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOrganisations::route('/'),
            'create' => Pages\CreateOrganisation::route('/create'),
            'view'   => Pages\ViewOrganisation::route('/{record}'),
            'edit'   => Pages\EditOrganisation::route('/{record}/edit'),
        ];
    }
}
