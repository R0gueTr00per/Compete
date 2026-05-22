<?php

namespace App\Filament\Admin\Resources;

use App\Models\Rank;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class RankResource extends Resource
{
    protected static ?string $model = Rank::class;

    public static function canAccess(): bool { return false; }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([]);
    }

    public static function getPages(): array
    {
        return [];
    }
}
