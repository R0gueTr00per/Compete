<?php

namespace App\Filament\OrgAdmin\Resources\CompetitionResource\RelationManagers;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\HtmlString;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Validation\Rules\Unique;
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
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, Set $set, Get $get) {
                        if ($state && ! $get('event_code')) {
                            $set('event_code', strtoupper(mb_substr($state, 0, 2)));
                        }
                    })
                    ->columnSpanFull(),

                TextInput::make('event_code')
                    ->label('Event code')
                    ->helperText('Short prefix for division codes (e.g. PS).')
                    ->required()
                    ->maxLength(10)
                    ->unique(
                        table: 'competition_events',
                        column: 'event_code',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, RelationManager $livewire) =>
                            $rule->where('competition_id', $livewire->ownerRecord->id),
                    ),

                Select::make('division_filter')
                    ->label('Division filter')
                    ->options([
                        'age_rank_sex'   => 'Age + rank + sex',
                        'age_sex'        => 'Age + sex',
                        'age_rank'       => 'Age + rank',
                        'age_only'       => 'Age only',
                        'weight_sex'     => 'Weight + sex',
                        'age_weight'     => 'Age + weight',
                        'age_weight_sex' => 'Age + weight + sex',
                    ])
                    ->required()
                    ->live(),

                Select::make('tournament_format')
                    ->label('Tournament format')
                    ->options([
                        'once_off'           => 'Single performance',
                        'round_robin'        => 'Round robin',
                        'single_elimination' => 'Single elimination bracket',
                        'double_elimination' => 'Double elimination bracket',
                        'repechage'          => 'Single elimination with repechage',
                        'se_3rd_place'       => 'SE with 3rd place playoff',
                    ])
                    ->helperText('Single performance: all compete, ranked by score.')
                    ->default('once_off')
                    ->required()
                    ->live(),

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
                    ->nullable()
                    ->helperText('Pre-fills judge score inputs on the scoring screen.')
                    ->hidden(fn (Get $get) => ! in_array($get('scoring_method'), ['judges_total', 'judges_average'])),

                TextInput::make('target_score')
                    ->label('Target score (first-to-N)')
                    ->numeric()
                    ->nullable()
                    ->hidden(fn (Get $get) => $get('scoring_method') !== 'first_to_n'),

                Toggle::make('requires_partner')
                    ->label('Requires partner')
                    ->helperText('Competitors must nominate a partner when enrolling.'),

                Section::make('Bracket Options')
                    ->hidden(fn (Get $get) => in_array($get('tournament_format'), ['once_off', 'round_robin', null]))
                    ->columnSpanFull()
                    ->schema([
                        Radio::make('bracket_sort')
                            ->label('Build division brackets using')
                            ->options([
                                'first_name'         => 'First name',
                                'surname'            => 'Surname',
                                'registration_order' => 'Registration order',
                                'random'             => 'Randomised',
                            ])
                            ->default('first_name')
                            ->columnSpanFull(),

                        Section::make('First-round matching options')
                            ->compact()
                            ->columnSpanFull()
                            ->schema([
                                Toggle::make('manual_pairing')
                                    ->label('Manual pairing')
                                    ->helperText('Manually assign first-round matchups. Disables all options below.')
                                    ->live()
                                    ->afterStateUpdated(function (bool $state, Set $set) {
                                        if ($state) {
                                            $set('bracket_first_round_order', null);
                                            $set('bracket_prefer_different_dojo', false);
                                            $set('bracket_avoid_repeat_matchups', false);
                                        }
                                    })
                                    ->columnSpanFull(),

                                Select::make('bracket_first_round_order')
                                    ->label('First-round ordering')
                                    ->placeholder('None')
                                    ->options(function (Get $get) {
                                        $filter = $get('division_filter');
                                        $opts   = [];
                                        if (in_array($filter, ['age_rank_sex', 'age_rank'])) {
                                            $opts['seed_by_rank'] = 'Seed by rank';
                                        }
                                        if (in_array($filter, ['age_rank_sex', 'age_sex', 'age_rank', 'age_only', 'age_weight', 'age_weight_sex'])) {
                                            $opts['match_similar_age'] = 'Match similar age';
                                        }
                                        if (in_array($filter, ['weight_sex', 'age_weight', 'age_weight_sex'])) {
                                            $opts['match_similar_weight'] = 'Match similar weight';
                                        }
                                        return $opts;
                                    })
                                    ->helperText('Seed by rank: top seeds placed apart. Match similar age/weight: closest competitors paired together.')
                                    ->disabled(fn (Get $get) => (bool) $get('manual_pairing'))
                                    ->columnSpanFull(),

                                Toggle::make('bracket_prefer_different_dojo')
                                    ->label('Prefer different dojo/club')
                                    ->helperText('Avoids same-dojo pairings in round 1.')
                                    ->disabled(fn (Get $get) => (bool) $get('manual_pairing')),

                                Toggle::make('bracket_avoid_repeat_matchups')
                                    ->label('Avoid repeat matchups')
                                    ->helperText('Avoids re-pairing competitors who have already met in this competition.')
                                    ->disabled(fn (Get $get) => (bool) $get('manual_pairing')),
                            ]),

                        Placeholder::make('bracket_options_note')
                            ->hiddenLabel()
                            ->content(new HtmlString('<p class="text-sm text-gray-500">Note: Changes take effect on the next scored event. Already-generated brackets are unaffected.</p>'))
                            ->columnSpanFull(),
                    ]),
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
                    })
                    ->visibleFrom('sm'),

                TextColumn::make('scoring_method')
                    ->label('Scoring')
                    ->formatStateUsing(fn (?string $state, $record) => match ($state) {
                        'judges_total'   => 'Judges total (' . ($record->judge_count ?? 0) . ')',
                        'judges_average' => 'Judges avg (' . ($record->judge_count ?? 0) . ')',
                        'first_to_n'     => 'First to N (' . ($record->target_score ?? '?') . ')',
                        'win_loss'       => 'Win / Loss',
                        default          => $state ?? '—',
                    })
                    ->visibleFrom('sm'),
            ])
            ->defaultSort('name')
            ->paginated(false)
            ->headerActions([
                CreateAction::make()
                    ->hidden(fn () => $this->getOwnerRecord()->status !== 'planning'),
            ])
            ->actions([
                EditAction::make()
                    ->hidden(fn () => $this->getOwnerRecord()->status !== 'planning'),

                DeleteAction::make()
                    ->hidden(fn () => $this->getOwnerRecord()->status !== 'planning')
                    ->modalDescription(function ($record) {
                        if ($record->enrolmentEvents()->exists()) {
                            $enrolCount = $record->enrolmentEvents()->count();
                            return "Cannot delete — this event type has {$enrolCount} enrolment(s). Remove all enrolments first.";
                        }
                        $count = $record->divisions()->count();
                        return $count > 0
                            ? "This will also delete {$count} division(s) belonging to this event type."
                            : 'Are you sure you want to remove this event type?';
                    })
                    ->before(function ($record, $action) {
                        if ($record->enrolmentEvents()->exists()) {
                            Notification::make()
                                ->danger()
                                ->title('Cannot delete — remove all enrolments for this event type first.')
                                ->send();
                            $action->cancel();
                        }
                        $record->divisions()->delete();
                    }),
            ]);
    }
}
