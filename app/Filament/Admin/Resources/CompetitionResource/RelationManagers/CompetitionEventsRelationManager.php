<?php

namespace App\Filament\Admin\Resources\CompetitionResource\RelationManagers;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
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

                Select::make('tournament_format')
                    ->label('Tournament format')
                    ->options([
                        'once_off'           => 'Single performance (all perform, ranked by score)',
                        'round_robin'        => 'Round robin',
                        'single_elimination' => 'Single elimination bracket',
                        'double_elimination' => 'Double elimination bracket',
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
                    ->required(),

                TextInput::make('judge_count')
                    ->label('Number of judges')
                    ->numeric()
                    ->default(0)
                    ->nullable(),

                TextInput::make('target_score')
                    ->label('Target score (first-to-N)')
                    ->numeric()
                    ->nullable(),
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
                        default              => 'Single perf',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'round_robin'        => 'info',
                        'single_elimination' => 'warning',
                        'double_elimination' => 'danger',
                        default              => 'gray',
                    }),

                TextColumn::make('scoring_method')
                    ->label('Scoring')
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'judges_total'   => 'Judges total',
                        'judges_average' => 'Judges avg',
                        'first_to_n'     => 'First to N',
                        'win_loss'       => 'Win / Loss',
                        default          => $state ?? '—',
                    }),
            ])
            ->paginated(false)
            ->headerActions([CreateAction::make()])
            ->actions([
                EditAction::make()
                    ->requiresConfirmation(fn ($record) => $record->divisions()->exists())
                    ->modalHeading('Edit event type')
                    ->modalDescription(fn ($record) => $record->divisions()->count() . ' division(s) belong to this event type and will be deleted when you save. You can regenerate divisions afterwards.')
                    ->after(fn ($record) => $record->divisions()->delete()),

                DeleteAction::make()
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
