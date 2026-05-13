<?php

namespace App\Filament\Admin\Resources\CompetitionResource\RelationManagers;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CompetitionEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'competitionEvents';
    protected static ?string $title = 'Event Types';

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make()->columns(2)->schema([
                TextInput::make('name')
                    ->label('Event type name')
                    ->required()
                    ->maxLength(100)
                    ->columnSpanFull(),

                Select::make('division_filter')
                    ->label('Division filter')
                    ->options([
                        'age_rank_sex'  => 'Age + rank + sex',
                        'age_sex'       => 'Age + sex',
                        'age_rank'      => 'Age + rank',
                        'age_only'      => 'Age only',
                        'weight_sex'    => 'Weight + sex',
                        'age_weight'    => 'Age + weight',
                        'age_weight_sex' => 'Age + weight + sex',
                    ])
                    ->required()
                    ->columnSpanFull(),

                Select::make('tournament_format')
                    ->label('Tournament format')
                    ->options([
                        'once_off'           => 'Single performance (all perform, ranked by score)',
                        'round_robin'        => 'Round robin',
                        'single_elimination' => 'Single elimination bracket',
                        'double_elimination' => 'Double elimination bracket',
                        'repechage'          => 'Single elimination with repechage',
                        'se_3rd_place'       => 'Single elimination with 3rd place playoff',
                    ])
                    ->default('once_off')
                    ->required()
                    ->columnSpanFull(),

                Select::make('scoring_method')
                    ->label('Scoring method')
                    ->options([
                        'judges_total'   => 'Judges scores total',
                        'judges_average' => 'Judges scores averaged',
                        'first_to_n'     => 'First to N points',
                        'win_loss'       => 'Win / Loss',
                    ])
                    ->required()
                    ->live(),

                TextInput::make('judge_count')
                    ->label('Number of judges')
                    ->numeric()
                    ->default(0)
                    ->nullable()
                    ->hidden(fn (Get $get) => ! in_array($get('scoring_method'), ['judges_total', 'judges_average'])),

                TextInput::make('default_score')
                    ->label('Default judge score')
                    ->numeric()
                    ->step(0.1)
                    ->default(7.0)
                    ->nullable()
                    ->helperText('Pre-fills judge score inputs on the scoring screen.')
                    ->hidden(fn (Get $get) => ! in_array($get('scoring_method'), ['judges_total', 'judges_average'])),

                TextInput::make('target_score')
                    ->label('Target score (first-to-N)')
                    ->numeric()
                    ->nullable()
                    ->hidden(fn (Get $get) => $get('scoring_method') !== 'first_to_n'),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Event type')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('tournament_format')
                    ->label('Format')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'round_robin'        => 'Round robin',
                        'single_elimination' => 'Single elim',
                        'double_elimination' => 'Double elim',
                        'repechage'          => 'Repechage',
                        'se_3rd_place'       => 'SE + 3rd place',
                        default              => 'Single perf',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'round_robin'        => 'info',
                        'single_elimination' => 'warning',
                        'double_elimination' => 'danger',
                        'repechage'          => 'primary',
                        'se_3rd_place'       => 'success',
                        default              => 'gray',
                    }),

                TextColumn::make('scoring_method')
                    ->label('Scoring')
                    ->formatStateUsing(fn (?string $state, $record) => match ($state) {
                        'judges_total'   => 'Judges total (' . ($record->judge_count ?? 0) . ')',
                        'judges_average' => 'Judges avg (' . ($record->judge_count ?? 0) . ')',
                        'first_to_n'     => 'First to N (' . ($record->target_score ?? '?') . ')',
                        'win_loss'       => 'Win / Loss',
                        default          => $state ?? '—',
                    }),
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
                    ->hidden(fn () => $this->getOwnerRecord()->status !== 'draft')
                    ->before(fn ($record) => $record->divisions()->delete())
                    ->modalDescription(function ($record) {
                        $count = $record->divisions()->count();
                        return $count > 0
                            ? "This will also delete {$count} division(s) belonging to this event type."
                            : 'Are you sure you want to remove this event type?';
                    }),
            ]);
    }
}
