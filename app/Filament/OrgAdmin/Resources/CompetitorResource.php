<?php

namespace App\Filament\OrgAdmin\Resources;

use App\Filament\OrgAdmin\Actions\HistoryTableAction;
use App\Filament\OrgAdmin\Resources\CompetitorResource\Pages;
use App\Filament\OrgAdmin\Resources\MemberResource;
use App\Models\CompetitorProfile;
use App\Models\OrganisationMembership;
use App\Models\User;
use App\Notifications\AccountCreatedNotification;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class CompetitorResource extends Resource
{
    protected static ?string $model = CompetitorProfile::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Competitors';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Profiles';
    protected static ?string $recordTitleAttribute = 'surname';

    public static function canAccess(): bool
    {
        $tenant = app('tenant');
        if (! $tenant) return true;
        return auth()->user()?->isOrgAdmin($tenant) ?? false;
    }

    public static function canCreate(): bool
    {
        $tenant = app('tenant');
        if (! $tenant) return true;
        return auth()->user()?->isOrgAdmin($tenant) ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $tenant = app('tenant');
        if (! $tenant) return true;
        return auth()->user()?->isOrgAdmin($tenant) ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $tenant = app('tenant');
        if (! $tenant) return true;
        return auth()->user()?->isOrgAdmin($tenant) ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('organisation_id', app('tenant')?->id);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Ownership')
                ->columns(2)
                ->schema([
                    Select::make('owner_user_id')
                        ->label('Account')
                        ->relationship('owner', 'email')
                        ->searchable()
                        ->required()
                        ->preload(),

                    Radio::make('profile_type')
                        ->label('Profile type')
                        ->options(['self' => 'Self', 'child' => 'Child'])
                        ->required()
                        ->inline()
                        ->hiddenOn('edit'),

                    Placeholder::make('profile_type_display')
                        ->label('Profile type')
                        ->content(fn (CompetitorProfile $record) => ucfirst($record->profile_type))
                        ->visibleOn('edit'),

                ]),

            Section::make('Personal Details')
                ->columns(2)
                ->schema([
                    TextInput::make('first_name')
                        ->required()
                        ->maxLength(100),

                    TextInput::make('surname')
                        ->required()
                        ->maxLength(100),

                    DatePicker::make('date_of_birth')
                        ->required()
                        ->maxDate(now()->subYears(1)),

                    Radio::make('gender')
                        ->options(['M' => 'Male', 'F' => 'Female'])
                        ->required()
                        ->inline(),

                    TextInput::make('phone')
                        ->tel()
                        ->maxLength(30),
                ]),

            Section::make('Profile Photo')
                ->schema([
                    FileUpload::make('profile_photo')
                        ->label('Photo')
                        ->image()
                        ->imagePreviewHeight('200')
                        ->disk('public')
                        ->directory('profile-photos')
                        ->visibility('public')
                        ->maxSize(2048),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('first_name')
                    ->label('Name')
                    ->getStateUsing(fn (CompetitorProfile $record) => $record->first_name . ' ' . $record->surname)
                    ->searchable(query: fn ($query, $search) => $query->where('first_name', 'like', "%{$search}%")->orWhere('surname', 'like', "%{$search}%"))
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('first_name', $direction)->orderBy('surname', $direction)),

                TextColumn::make('profile_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state) => $state === 'child' ? 'warning' : 'info')
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->visibleFrom('sm'),

                TextColumn::make('date_of_birth')
                    ->label('DOB')
                    ->date('d M Y')
                    ->sortable()
                    ->visibleFrom('sm'),

                TextColumn::make('age')
                    ->label('Age')
                    ->sortable(query: fn ($query, string $direction) =>
                        $query->orderBy('date_of_birth', $direction === 'asc' ? 'desc' : 'asc')
                    ),

                TextColumn::make('gender')
                    ->badge()
                    ->color(fn (string $state) => $state === 'M' ? 'info' : 'danger')
                    ->formatStateUsing(fn (string $state) => $state === 'M' ? 'Male' : 'Female')
                    ->visibleFrom('sm'),

                TextColumn::make('owner.email')
                    ->label('Managed by')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->url(fn (CompetitorProfile $record) => $record->owner_user_id
                        ? (($mid = OrganisationMembership::where('user_id', $record->owner_user_id)
                            ->where('organisation_id', app('tenant')?->id)
                            ->value('id'))
                            ? MemberResource::getUrl('edit', ['record' => $mid])
                            : null)
                        : null
                    )
                    ->color('primary')
                    ->visibleFrom('sm'),

                TextColumn::make('owner.status')
                    ->label('Account')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active'   => 'success',
                        'pending'  => 'warning',
                        'inactive' => 'danger',
                        default    => 'gray',
                    })
                    ->visibleFrom('sm'),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->visibleFrom('sm'),
            ])
            ->modifyQueryUsing(fn ($query) => $query->orderBy('first_name')->orderBy('surname'))
            ->filters([
                SelectFilter::make('gender')
                    ->options(['M' => 'Male', 'F' => 'Female']),

                SelectFilter::make('profile_type')
                    ->label('Type')
                    ->options(['self' => 'Self', 'child' => 'Child']),

                TernaryFilter::make('is_active')
                    ->label('Active'),

                SelectFilter::make('profile_complete')
                    ->label('Profile')
                    ->options([
                        '1' => 'Complete',
                        '0' => 'Incomplete',
                    ]),
            ])
            ->actions([
                EditAction::make(),
                ActionGroup::make([
                    Action::make('deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (CompetitorProfile $record) => $record->is_active)
                        ->action(function (CompetitorProfile $record) {
                            $record->update(['is_active' => false]);
                            Notification::make()->title('Profile deactivated.')->success()->send();
                        }),

                    Action::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-arrow-path')
                        ->color('gray')
                        ->visible(fn (CompetitorProfile $record) => ! $record->is_active)
                        ->action(function (CompetitorProfile $record) {
                            $record->update(['is_active' => true]);
                            Notification::make()->title('Profile activated.')->success()->send();
                        }),

                    Action::make('promoteToOwnAccount')
                        ->label('Promote to own account')
                        ->icon('heroicon-o-arrow-up-circle')
                        ->color('success')
                        ->visible(fn (CompetitorProfile $record) => $record->profile_type === 'child')
                        ->modalHeading('Promote to own account')
                        ->modalDescription('This will create a new login account for this competitor. They will receive an email with a link to set their password.')
                        ->modalSubmitActionLabel('Create account')
                        ->form([
                            TextInput::make('email')
                                ->label('Email address for new account')
                                ->email()
                                ->required()
                                ->maxLength(255)
                                ->unique('users', 'email'),
                        ])
                        ->action(function (CompetitorProfile $record, array $data) {
                            $newUser = User::create([
                                'email'    => $data['email'],
                                'password' => Hash::make(Str::random(32)),
                                'status'   => 'active',
                            ]);
                            $newUser->forceFill(['email_verified_at' => now()])->save();
                            $newUser->assignRole('user');

                            $record->update([
                                'profile_type'  => 'self',
                                'user_id'        => $newUser->id,
                                'owner_user_id'  => $newUser->id,
                            ]);

                            $token = Password::broker()->createToken($newUser);
                            $newUser->notify(new AccountCreatedNotification($token, app('tenant')));

                            Notification::make()->title('Account created and setup email sent.')->success()->send();
                        }),

                    DeleteAction::make()
                        ->visible(fn (CompetitorProfile $record) => $record->enrolments()->doesntExist())
                        ->before(function (CompetitorProfile $record) {
                            if ($record->enrolments()->exists()) {
                                Notification::make()
                                    ->title('Cannot delete a profile with enrolment history. Deactivate it instead.')
                                    ->danger()
                                    ->send();
                                $this->halt();
                            }
                        }),

                    HistoryTableAction::make(),
                ]),
            ]);
    }

    public static function getRelationManagers(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompetitors::route('/'),
            'edit'  => Pages\EditCompetitor::route('/{record}/edit'),
        ];
    }
}
