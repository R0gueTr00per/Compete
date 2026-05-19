<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\OfficialRoleResource\Pages;
use App\Models\OfficialRole;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OfficialRoleResource extends Resource
{
    protected static ?string $model = OfficialRole::class;
    protected static ?string $navigationIcon  = 'heroicon-o-identification';
    protected static ?string $navigationGroup = 'System';
    protected static ?int    $navigationSort  = 4;
    protected static ?string $navigationLabel = 'Official Roles';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(['system_admin', 'competition_administrator']) ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return ! $record->isUsed();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make()->schema([
                TextInput::make('name')
                    ->label('Role')
                    ->required()
                    ->maxLength(100)
                    ->unique(OfficialRole::class, 'name', ignoreRecord: true),
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
