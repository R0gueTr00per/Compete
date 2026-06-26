<?php

namespace App\Filament\OrgAdmin\Resources;

use App\Filament\OrgAdmin\Resources\OrganisationNewsResource\Pages;
use App\Models\OrganisationNews;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrganisationNewsResource extends Resource
{
    protected static ?string $model = OrganisationNews::class;
    protected static string | \BackedEnum | null $navigationIcon  = 'heroicon-o-newspaper';
    protected static string | \UnitEnum | null $navigationGroup = 'System';
    protected static ?int    $navigationSort  = 0;
    protected static ?string $navigationLabel = 'News';

    public static function canAccess(): bool
    {
        if (auth()->user()?->hasRole('system_admin')) return false;
        $tenant = app('tenant');
        if (! $tenant) return true;
        return auth()->user()?->isOrgAdmin($tenant) ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('organisation_id', app('tenant')?->id);
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            TextInput::make('title')
                ->maxLength(200)
                ->columnSpanFull(),

            RichEditor::make('content')
                ->required()
                ->toolbarButtons(['bold', 'italic', 'bulletList', 'orderedList', 'link'])
                ->columnSpanFull(),

            DatePicker::make('display_from')
                ->label('Show from')
                ->helperText('Leave blank to show immediately'),

            DatePicker::make('display_until')
                ->label('Show until')
                ->helperText('Leave blank to show indefinitely'),

            Toggle::make('is_visible')
                ->label('Visible')
                ->default(true)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('News items displayed at the top of the competitor dashboard.')
            ->columns([
                IconColumn::make('is_visible')
                    ->label('Visible')
                    ->boolean()
                    ->width('80px'),

                TextColumn::make('title')
                    ->placeholder('(no title)')
                    ->searchable(),

                TextColumn::make('display_from')
                    ->label('From')
                    ->date('d M Y')
                    ->placeholder('—'),

                TextColumn::make('display_until')
                    ->label('Until')
                    ->date('d M Y')
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->since()
                    ->sortable(),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOrganisationNews::route('/'),
            'create' => Pages\CreateOrganisationNews::route('/create'),
            'edit'   => Pages\EditOrganisationNews::route('/{record}/edit'),
        ];
    }
}
