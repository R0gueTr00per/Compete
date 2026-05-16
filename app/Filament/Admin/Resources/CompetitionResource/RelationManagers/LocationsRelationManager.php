<?php

namespace App\Filament\Admin\Resources\CompetitionResource\RelationManagers;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class LocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'competitionLocations';
    protected static ?string $title = 'Locations';

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Location name')
                ->placeholder('e.g. Mat 1')
                ->required()
                ->maxLength(50)
                ->unique(
                    table: 'competition_locations',
                    column: 'name',
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule, RelationManager $livewire) =>
                        $rule->where('competition_id', $livewire->ownerRecord->id),
                ),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')->label('Location'),
            ])
            ->paginated(false)
            ->headerActions([
                CreateAction::make()
                    ->hidden(fn () => $this->getOwnerRecord()->status !== 'draft'),
            ])
            ->actions([
                EditAction::make()
                    ->hidden(fn () => $this->getOwnerRecord()->status !== 'draft'),
                DeleteAction::make()
                    ->hidden(fn () => $this->getOwnerRecord()->status !== 'draft'),
            ]);
    }
}
