<?php

namespace App\Filament\OrgAdmin\Resources;

use App\Filament\OrgAdmin\Resources\RankResource\Pages;
use App\Models\Rank;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RankResource extends Resource
{
    protected static ?string $model = Rank::class;
    protected static ?string $navigationIcon  = 'heroicon-o-star';
    protected static ?string $navigationGroup = 'System';
    protected static ?int    $navigationSort  = 10;
    protected static ?string $navigationLabel = 'Ranks / Levels';

    public static function canAccess(): bool
    {
        return ! auth()->user()?->hasRole('system_admin');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('organisation_id', app('tenant')?->id);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->required()
                ->maxLength(100),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('Order ranks from lowest to highest (e.g. 9th Kyu → 1st Kyu, then 1st Dan → 10th Dan). Drag rows to reorder.')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->width('50px'),

                TextColumn::make('name')
                    ->searchable(),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRanks::route('/'),
            'create' => Pages\CreateRank::route('/create'),
            'edit'   => Pages\EditRank::route('/{record}/edit'),
        ];
    }
}
