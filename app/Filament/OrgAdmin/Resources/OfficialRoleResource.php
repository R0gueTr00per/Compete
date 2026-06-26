<?php

namespace App\Filament\OrgAdmin\Resources;

use App\Filament\OrgAdmin\Resources\OfficialRoleResource\Pages;
use App\Models\OfficialRole;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class OfficialRoleResource extends Resource
{
    protected static ?string $model = OfficialRole::class;
    protected static string | \BackedEnum | null $navigationIcon  = 'heroicon-o-identification';
    protected static string | \UnitEnum | null $navigationGroup = 'System';
    protected static ?int    $navigationSort  = 4;
    protected static ?string $navigationLabel = 'Official Roles';

    public static function canAccess(): bool
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

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return ! $record->isUsed();
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organisation_id'] = app('tenant')?->id;
        return $data;
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Section::make()->schema([
                TextInput::make('name')
                    ->label('Role')
                    ->required()
                    ->maxLength(100)
                    ->rules(fn ($record) => [
                        Rule::unique('official_roles', 'name')
                            ->where('organisation_id', app('tenant')?->id)
                            ->ignore($record?->id),
                    ]),
            ]),
            Section::make('Portal Access')
                ->description('Areas of the org admin portal this role can access.')
                ->schema([
                    Toggle::make('can_access_enrolments')->label('Registrations'),
                    Toggle::make('can_access_checkin')->label('Check-in'),
                    Toggle::make('can_access_create_enrolment')->label('Create Enrolment'),
                    Toggle::make('can_access_scoring')->label('Scoring'),
                    Toggle::make('can_access_accounts')->label('Accounts'),
                    Toggle::make('can_access_results')->label('Results'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Role')->sortable()->searchable(),
                TextColumn::make('can_access_enrolments')
                    ->label('Registrations')
                    ->html()
                    ->formatStateUsing(fn ($state) => $state ? '<span class="text-success-500 text-base font-bold">✓</span>' : '')
                    ->alignment(\Filament\Support\Enums\Alignment::Center)
                    ->visibleFrom('md'),
                TextColumn::make('can_access_checkin')
                    ->label('Check-in')
                    ->html()
                    ->formatStateUsing(fn ($state) => $state ? '<span class="text-success-500 text-base font-bold">✓</span>' : '')
                    ->alignment(\Filament\Support\Enums\Alignment::Center)
                    ->visibleFrom('md'),
                TextColumn::make('can_access_create_enrolment')
                    ->label('Create Enrolment')
                    ->html()
                    ->formatStateUsing(fn ($state) => $state ? '<span class="text-success-500 text-base font-bold">✓</span>' : '')
                    ->alignment(\Filament\Support\Enums\Alignment::Center)
                    ->visibleFrom('md'),
                TextColumn::make('can_access_scoring')
                    ->label('Scoring')
                    ->html()
                    ->formatStateUsing(fn ($state) => $state ? '<span class="text-success-500 text-base font-bold">✓</span>' : '')
                    ->alignment(\Filament\Support\Enums\Alignment::Center)
                    ->visibleFrom('md'),
                TextColumn::make('can_access_accounts')
                    ->label('Accounts')
                    ->html()
                    ->formatStateUsing(fn ($state) => $state ? '<span class="text-success-500 text-base font-bold">✓</span>' : '')
                    ->alignment(\Filament\Support\Enums\Alignment::Center)
                    ->visibleFrom('md'),
                TextColumn::make('can_access_results')
                    ->label('Results')
                    ->html()
                    ->formatStateUsing(fn ($state) => $state ? '<span class="text-success-500 text-base font-bold">✓</span>' : '')
                    ->alignment(\Filament\Support\Enums\Alignment::Center)
                    ->visibleFrom('md'),
            ])
            ->defaultSort('name')
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->disabled(fn (OfficialRole $record) => $record->isUsed())
                    ->tooltip(fn (OfficialRole $record) => $record->isUsed()
                        ? 'Cannot delete — role is assigned to one or more officials.'
                        : null
                    ),
            ])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOfficialRoles::route('/'),
        ];
    }
}
