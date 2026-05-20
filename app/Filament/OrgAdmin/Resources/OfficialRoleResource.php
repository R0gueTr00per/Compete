<?php

namespace App\Filament\OrgAdmin\Resources;

use App\Filament\OrgAdmin\Resources\OfficialRoleResource\Pages;
use App\Models\OfficialRole;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class OfficialRoleResource extends Resource
{
    protected static ?string $model = OfficialRole::class;
    protected static ?string $navigationIcon  = 'heroicon-o-identification';
    protected static ?string $navigationGroup = 'System';
    protected static ?int    $navigationSort  = 4;
    protected static ?string $navigationLabel = 'Official Roles';

    public static function canAccess(): bool
    {
        $tenant = app('tenant');
        if (! $tenant) return true;
        return ! (auth()->user()?->isOrgOfficial($tenant) ?? false);
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

    public static function form(Form $form): Form
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
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Role')->sortable()->searchable(),
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
