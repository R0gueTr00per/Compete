<?php

namespace App\Filament\OrgAdmin\Resources\CompetitionResource\RelationManagers;

use App\Models\Division;
use App\Models\Rank;
use App\Models\RankBand;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RankBandsRelationManager extends RelationManager
{
    protected static string $relationship = 'rankBands';
    protected static ?string $title = 'Rank/Level Bands';

    public function form(Schema $form): Schema
    {
        return $form->schema([
            TextInput::make('label')
                ->required()
                ->maxLength(50)
                ->placeholder('e.g. Kyu grades')
                ->columnSpanFull(),

            TextInput::make('description')
                ->maxLength(255)
                ->nullable()
                ->columnSpanFull(),

            Select::make('from_rank_id')
                ->label('From rank (lowest / least experienced)')
                ->options(fn () => Rank::where('organisation_id', $this->getOwnerRecord()->organisation_id)->orderBy('sort_order')->pluck('name', 'id'))
                ->searchable()
                ->nullable()
                ->placeholder('No lower limit'),

            Select::make('to_rank_id')
                ->label('To rank (highest / most experienced)')
                ->options(fn () => Rank::where('organisation_id', $this->getOwnerRecord()->organisation_id)->orderBy('sort_order')->pluck('name', 'id'))
                ->searchable()
                ->nullable()
                ->placeholder('No upper limit'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('label'),
                TextColumn::make('fromRank.name')->label('From rank')->default('—'),
                TextColumn::make('toRank.name')->label('To rank')->default('—'),
                TextColumn::make('description')->default('—'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([
                CreateAction::make()
                    ->hidden(fn () => ! in_array($this->getOwnerRecord()->status, ['planning', 'advertise']))
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['sort_order'] = (RankBand::where('competition_id', $this->getOwnerRecord()->id)->max('sort_order') ?? 0) + 1;
                        return $data;
                    })
            ])
            ->actions([
                EditAction::make()
                    ->hidden(fn () => ! in_array($this->getOwnerRecord()->status, ['planning', 'advertise'])),

                DeleteAction::make()
                    ->hidden(fn () => ! in_array($this->getOwnerRecord()->status, ['planning', 'advertise']))
                    ->before(fn ($record) => Division::where('rank_band_id', $record->id)->delete())
                    ->modalDescription(function ($record) {
                        $count = Division::where('rank_band_id', $record->id)->count();
                        return $count > 0
                            ? "This will also delete {$count} division(s) that use this rank band."
                            : 'Are you sure you want to delete this rank band?';
                    }),
            ]);
    }
}
