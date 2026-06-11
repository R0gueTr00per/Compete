<?php

namespace App\Filament\OrgAdmin\Resources\CompetitionResource\RelationManagers;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CompetitionMessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'portalMessages';
    protected static ?string $title = 'Portal Messages';

    public function form(Form $form): Form
    {
        return $form->schema([
            Textarea::make('message')
                ->label('Message')
                ->required()
                ->maxLength(500)
                ->rows(3)
                ->placeholder('e.g. Good luck in all your events! If you have any issues please speak to one of the officials.')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('message')
            ->description('Messages displayed to competitors on their portal dashboard for this competition.')
            ->columns([
                TextColumn::make('message')
                    ->limit(100)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label('Posted')
                    ->since()
                    ->sortable(),
            ])
            ->reorderable('sort_order')
            ->paginated(false)
            ->headerActions([
                CreateAction::make()->label('Add message'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
