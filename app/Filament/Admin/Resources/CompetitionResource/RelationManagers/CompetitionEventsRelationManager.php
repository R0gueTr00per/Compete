<?php

namespace App\Filament\Admin\Resources\CompetitionResource\RelationManagers;

use App\Models\EventType;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Set;
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
                Select::make('event_type_id')
                    ->label('Event type')
                    ->options(EventType::orderBy('name')->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function (Set $set, ?string $state) {
                        $et = EventType::find($state);
                        if ($et) {
                            $set('scoring_method', $et->scoring_method);
                            $set('judge_count', $et->judge_count);
                            $set('target_score', $et->default_target_score);
                        }
                    })
                    ->columnSpanFull(),

                Select::make('scoring_method')
                    ->options([
                        'judges_total'   => 'Judges scores total',
                        'judges_average' => 'Judges scores averaged',
                        'first_to_n'     => 'First to N points',
                        'win_loss'       => 'Win / Loss',
                    ])
                    ->nullable(),

                TextInput::make('judge_count')
                    ->label('Number of judges')
                    ->numeric()
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
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('eventType.name')
                    ->label('Event type')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('scoring_method')
                    ->label('Scoring')
                    ->formatStateUsing(function (?string $state, $record) {
                        $method = $state ?? $record->eventType?->scoring_method;
                        return match ($method) {
                            'judges_total'   => 'Judges total',
                            'judges_average' => 'Judges avg',
                            'first_to_n'     => 'First to N',
                            'win_loss'       => 'Win / Loss',
                            default          => $method ?? '—',
                        };
                    }),

            ])
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
