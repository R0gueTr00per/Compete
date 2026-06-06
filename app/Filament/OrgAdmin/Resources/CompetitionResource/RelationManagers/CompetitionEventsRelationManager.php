<?php

namespace App\Filament\OrgAdmin\Resources\CompetitionResource\RelationManagers;

use App\Models\CompetitionEvent;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\HtmlString;
use App\Notifications\Notification;
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
            Tabs::make()->tabs([
                Tab::make('Basic')->columns(2)->schema([
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
                            'age_only'       => 'Age only',
                            'age_rank'       => 'Age + rank',
                            'age_rank_sex'   => 'Age + rank + sex',
                            'age_sex'        => 'Age + sex',
                            'age_weight'     => 'Age + weight',
                            'age_weight_sex' => 'Age + weight + sex',
                            'rank_sex'       => 'Rank + sex',
                            'weight_sex'     => 'Weight + sex',
                        ])
                        ->required()
                        ->live(),

                    Toggle::make('requires_partner')
                        ->label('Requires partner')
                        ->helperText('Competitors must nominate a partner when enrolling.')
                        ->columnSpanFull(),

                    Toggle::make('rollcall_required')
                        ->label('Roll call required')
                        ->helperText('When off, scoring opens directly and all competitors are assumed present.')
                        ->default(true)
                        ->columnSpanFull(),

                    TextInput::make('default_max_competitors')
                        ->label('Target number of competitors')
                        ->helperText('Default target per division. Can be set per division on the Events page.')
                        ->numeric()
                        ->nullable()
                        ->minValue(1),

                    Toggle::make('update_divisions')
                        ->label('Update all divisions to this target')
                        ->helperText('Overwrites any per-division target already set.')
                        ->default(false)
                        ->hidden(fn (?CompetitionEvent $record) => !$record || !$record->divisions()->exists())
                        ->columnSpanFull(),
                ]),

                Tab::make('Scoring')->columns(2)->schema([
                    Select::make('tournament_format')
                        ->label('Tournament format')
                        ->options([
                            'double_elimination' => 'Double elimination bracket',
                            'round_robin'        => 'Round robin',
                            'se_3rd_place'       => 'SE with 3rd place playoff',
                            'single_elimination' => 'Single elimination bracket',
                            'repechage'          => 'Single elimination with repechage',
                            'once_off'           => 'Single performance',
                        ])
                        ->helperText(fn (Get $get) => match ($get('tournament_format')) {
                            'round_robin'        => 'All competitors face each other; ranked by win count.',
                            'single_elimination' => 'Bracket — losers are eliminated immediately.',
                            'double_elimination' => 'Bracket — competitors need two losses to be eliminated.',
                            'repechage'          => 'Single elimination — bracket losers get a second chance via repechage.',
                            'se_3rd_place'       => 'Single elimination with a separate 3rd place playoff match.',
                            default              => 'All competitors perform once and are ranked by score.',
                        })
                        ->default('once_off')
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('scoring_method', null)),

                    Select::make('scoring_method')
                        ->label('Scoring method')
                        ->options(fn (Get $get) => $get('tournament_format') === 'once_off'
                            ? [
                                'judges_average' => 'Judges scores averaged',
                                'judges_total'   => 'Judges scores total',
                            ]
                            : [
                                'first_to_n'     => 'First to N points',
                                'timed_points'   => 'Timed points',
                                'win_loss'       => 'Win / Loss',
                            ]
                        )
                        ->required()
                        ->live(),

                    TextInput::make('judge_count')
                        ->label('Number of judges')
                        ->numeric()
                        ->default(0)
                        ->nullable()
                        ->live()
                        ->hidden(fn (Get $get) => ! in_array($get('scoring_method'), ['judges_total', 'judges_average'])),

                    Toggle::make('high_low_drop')
                        ->label('High-low drop')
                        ->helperText('Drop the highest and lowest judge scores before calculating. Requires at least 4 judges.')
                        ->hidden(fn (Get $get) => ! in_array($get('scoring_method'), ['judges_total', 'judges_average']))
                        ->rules([
                            fn (Get $get) => function (string $attribute, mixed $value, \Closure $fail) use ($get) {
                                if ($value && (int) $get('judge_count') < 4) {
                                    $fail('High-low drop requires at least 4 judges.');
                                }
                            },
                        ]),

                    TextInput::make('min_score')
                        ->label('Min score')
                        ->numeric()
                        ->step(0.1)
                        ->minValue(0)
                        ->nullable()
                        ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 1) : null)
                        ->helperText('Lowest score a judge may enter.')
                        ->hidden(fn (Get $get) => ! in_array($get('scoring_method'), ['judges_total', 'judges_average'])),

                    TextInput::make('max_score')
                        ->label('Max score')
                        ->numeric()
                        ->step(0.1)
                        ->minValue(0)
                        ->nullable()
                        ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 1) : null)
                        ->helperText('Highest score a judge may enter.')
                        ->hidden(fn (Get $get) => ! in_array($get('scoring_method'), ['judges_total', 'judges_average']))
                        ->rules([
                            fn (Get $get) => function (string $attr, mixed $value, \Closure $fail) use ($get) {
                                if ($value === null || $value === '') return;
                                $max = (float) $value;
                                $min = $get('min_score') !== null && $get('min_score') !== '' ? (float) $get('min_score') : null;
                                if ($min !== null && $max <= $min) $fail('Max score must be greater than min score.');
                            },
                        ]),

                    TextInput::make('default_score')
                        ->label('Default judge score')
                        ->numeric()
                        ->step(0.1)
                        ->nullable()
                        ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 1) : null)
                        ->helperText('Pre-fills judge score inputs on the scoring screen. Ignored when score categories are defined.')
                        ->hidden(fn (Get $get) => ! in_array($get('scoring_method'), ['judges_total', 'judges_average']))
                        ->rules([
                            fn (Get $get) => function (string $attr, mixed $value, \Closure $fail) use ($get) {
                                if ($value === null || $value === '') return;
                                $v   = (float) $value;
                                $min = $get('min_score') !== null && $get('min_score') !== '' ? (float) $get('min_score') : null;
                                $max = $get('max_score') !== null && $get('max_score') !== '' ? (float) $get('max_score') : null;
                                if ($min !== null && $v < $min) $fail("Default score must be ≥ min score ({$min}).");
                                if ($max !== null && $v > $max) $fail("Default score must be ≤ max score ({$max}).");
                            },
                        ]),

                    Radio::make('score_category_mode')
                        ->label('Judge score entry')
                        ->options([
                            'single'   => 'Single score — one score per judge',
                            'sum'      => 'Categories (sum) — categories scored, judge total = sum',
                            'weighted' => 'Categories (weighted) — each category × its weight %',
                        ])
                        ->default('single')
                        ->live()
                        ->hidden(fn (Get $get) => ! in_array($get('scoring_method'), ['judges_total', 'judges_average']))
                        ->columnSpanFull(),

                    Repeater::make('scoreCategories')
                        ->label('Score categories')
                        ->helperText(fn (Get $get) => ($get('score_category_mode') ?? 'single') === 'weighted'
                            ? 'Weights must total 100%.'
                            : 'Each category score is summed to produce the judge total.')
                        ->relationship()
                        ->orderColumn('sort_order')
                        ->schema([
                            TextInput::make('name')
                                ->label('Name')
                                ->required()
                                ->maxLength(100),
                            TextInput::make('weight')
                                ->label('Weight %')
                                ->numeric()
                                ->step(0.01)
                                ->minValue(0.01)
                                ->maxValue(100)
                                ->required(fn (Get $get) => ($get('../../score_category_mode') ?? 'single') === 'weighted')
                                ->live(onBlur: true)
                                ->suffix('%')
                                ->hidden(fn (Get $get) => ($get('../../score_category_mode') ?? 'single') !== 'weighted')
                                ->default(function (Get $get) {
                                    if (($get('../../score_category_mode') ?? 'single') !== 'weighted') return null;
                                    $used = collect($get('../../scoreCategories') ?? [])
                                        ->sum(fn ($item) => isset($item['weight']) && $item['weight'] !== '' ? (float) $item['weight'] : 0);
                                    $remaining = round(100 - $used, 2);
                                    return $remaining > 0 ? $remaining : null;
                                }),
                        ])
                        ->columns(3)
                        ->addActionLabel('Add category')
                        ->reorderable()
                        ->rules([
                            fn (Get $get) => function (string $attribute, mixed $value, \Closure $fail) use ($get) {
                                if (empty($value)) return;
                                if (($get('score_category_mode') ?? 'single') !== 'weighted') return;
                                $items = collect($value);
                                if ($items->contains(fn ($item) => ! isset($item['weight']) || (string) $item['weight'] === '')) return;
                                $total = $items->sum('weight');
                                if (abs((float) $total - 100) > 0.01) {
                                    $fail("Category weights must total exactly 100% (currently {$total}%).");
                                }
                            },
                        ])
                        ->hidden(fn (Get $get) => ! in_array($get('scoring_method'), ['judges_total', 'judges_average']) || ($get('score_category_mode') ?? 'single') === 'single')
                        ->columnSpanFull(),

                    Placeholder::make('weight_total')
                        ->hiddenLabel()
                        ->content(fn (Get $get): string => 'Weight total: ' . number_format(collect($get('scoreCategories') ?? [])->sum('weight'), 2) . '%')
                        ->hidden(fn (Get $get) => ! in_array($get('scoring_method'), ['judges_total', 'judges_average']) || empty($get('scoreCategories')) || ($get('score_category_mode') ?? 'single') !== 'weighted')
                        ->columnSpanFull(),

                    TextInput::make('target_score')
                        ->label('Target score (first-to-N)')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->requiredIf('scoring_method', 'first_to_n')
                        ->hidden(fn (Get $get) => $get('scoring_method') !== 'first_to_n'),

                    TagsInput::make('increment_buttons')
                        ->label('Score increment buttons')
                        ->placeholder('Add a value')
                        ->helperText('Values shown as tap buttons on the scoring screen (e.g. 1, 2, 3). Leave blank for a single +1 button.')
                        ->nestedRecursiveRules(['numeric', 'min:0.1'])
                        ->hidden(fn (Get $get) => ! in_array($get('scoring_method'), ['first_to_n', 'timed_points']))
                        ->columnSpanFull(),

                    TextInput::make('round_duration_seconds')
                        ->label('Round duration')
                        ->numeric()
                        ->nullable()
                        ->suffix('seconds')
                        ->helperText('e.g. 180 = 3 minutes. Leave blank for no timer.')
                        ->hidden(fn (Get $get) => ! in_array($get('scoring_method'), ['first_to_n', 'timed_points', 'win_loss'])),

                    TextInput::make('tiebreak_duration_seconds')
                        ->label('Tiebreak duration')
                        ->numeric()
                        ->nullable()
                        ->suffix('seconds')
                        ->helperText('Duration of the tiebreak period when scores are tied at time expiry.')
                        ->hidden(fn (Get $get) => ! in_array($get('scoring_method'), ['first_to_n', 'timed_points'])),

                    Radio::make('tiebreak_mode')
                        ->label('Tiebreak mode')
                        ->options([
                            'sudden_death' => 'Sudden death — first to score wins (inputs locked)',
                            'overtime'     => 'Overtime — scoring continues; head judge if still tied at end',
                        ])
                        ->default('sudden_death')
                        ->hidden(fn (Get $get) => ! in_array($get('scoring_method'), ['first_to_n', 'timed_points']))
                        ->live()
                        ->columnSpanFull(),

                    Radio::make('overtime_rounds')
                        ->label('Overtime rounds allowed')
                        ->options([
                            1 => '1 round — head judge decides if still tied',
                            2 => '2 rounds — head judge decides if still tied after both',
                        ])
                        ->default(1)
                        ->hidden(fn (Get $get) => ! in_array($get('scoring_method'), ['first_to_n', 'timed_points']) || $get('tiebreak_mode') !== 'overtime')
                        ->columnSpanFull(),

                    Section::make('Places Awarded')
                        ->columns(['default' => 1, 'sm' => 3])
                        ->columnSpanFull()
                        ->compact()
                        ->schema([
                            Select::make('awarded_places_2')
                                ->label('2 competitors')
                                ->options(['1' => 'Winner only (1st)', '2' => '1st and 2nd'])
                                ->default('2')
                                ->required(),

                            Select::make('awarded_places_3')
                                ->label('3 competitors')
                                ->options(['1' => 'Winner only (1st)', '2' => '1st and 2nd', '3' => '1st, 2nd and 3rd'])
                                ->default('3')
                                ->required(),

                            Select::make('awarded_places_4plus')
                                ->label('4+ competitors')
                                ->options(['1' => 'Winner only (1st)', '2' => '1st and 2nd', '3' => '1st, 2nd and 3rd'])
                                ->default('3')
                                ->required(),
                        ]),
                ]),

                Tab::make('Sorting')
                    ->hidden(fn (Get $get) => ! in_array($get('tournament_format'), ['once_off', null]))
                    ->schema([
                        Radio::make('competitor_sort')
                            ->label('Display competitors in order of')
                            ->options([
                                'first_name'         => 'First name',
                                'surname'            => 'Surname',
                                'registration_order' => 'Registration order',
                                'random'             => 'Randomised',
                            ])
                            ->default('first_name'),
                    ]),

                Tab::make('Brackets')
                    ->hidden(fn (Get $get) => in_array($get('tournament_format'), ['once_off', null]))
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
                            ->hidden(fn (Get $get) => in_array($get('tournament_format'), ['round_robin'])),

                        Section::make('First-round matching options')
                            ->compact()
                            ->hidden(fn (Get $get) => in_array($get('tournament_format'), ['round_robin']))
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
                                        if (in_array($filter, ['age_rank_sex', 'age_rank', 'rank_sex'])) {
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
                                    ->label(fn () => 'Prefer different ' . strtolower(tenant_group_name()))
                                    ->helperText(fn () => 'Avoids same-' . strtolower(tenant_group_name()) . ' pairings in round 1.')
                                    ->disabled(fn (Get $get) => (bool) $get('manual_pairing')),

                                Toggle::make('bracket_avoid_repeat_matchups')
                                    ->label('Avoid repeat matchups')
                                    ->helperText('Avoids re-pairing competitors who have already met in this competition.')
                                    ->disabled(fn (Get $get) => (bool) $get('manual_pairing')),
                            ]),

                        Placeholder::make('bracket_options_note')
                            ->hiddenLabel()
                            ->content(new HtmlString('<p class="text-sm text-gray-500">Note: Changes take effect on the next scored event. Already-generated brackets are unaffected.</p>'))
                            ->hidden(fn (Get $get) => in_array($get('tournament_format'), ['round_robin'])),
                    ]),

                Tab::make('Penalties')->schema([
                    Section::make()
                        ->hiddenLabel()
                        ->compact()
                        ->schema([
                            Toggle::make('penalty_config.warn.enabled')
                                ->label('Warnings')
                                ->inline(false)
                                ->live()
                                ->hidden(fn (Get $get) => in_array($get('scoring_method'), ['judges_total', 'judges_average'])),

                            TextInput::make('penalty_config.warn.auto_dq_after')
                                ->label('Auto-DQ after N warnings')
                                ->helperText('Leave blank to disable.')
                                ->numeric()->integer()->minValue(1)->nullable()
                                ->hidden(fn (Get $get) => in_array($get('scoring_method'), ['judges_total', 'judges_average']) || ! $get('penalty_config.warn.enabled')),

                            TagsInput::make('penalty_config.warn.reasons')
                                ->label('Warning reasons')
                                ->placeholder('Type and press Enter')
                                ->hidden(fn (Get $get) => in_array($get('scoring_method'), ['judges_total', 'judges_average']) || ! $get('penalty_config.warn.enabled')),

                            Toggle::make('penalty_config.dq.enabled')
                                ->label('DQ')
                                ->inline(false)
                                ->live(),

                            TagsInput::make('penalty_config.dq.reasons')
                                ->label('DQ reasons')
                                ->placeholder('Type and press Enter')
                                ->hidden(fn (Get $get) => ! $get('penalty_config.dq.enabled')),

                            Toggle::make('penalty_config.forfeit.enabled')
                                ->label('Forfeit')
                                ->inline(false)
                                ->live(),

                            TagsInput::make('penalty_config.forfeit.reasons')
                                ->label('Forfeit reasons')
                                ->placeholder('Type and press Enter')
                                ->hidden(fn (Get $get) => ! $get('penalty_config.forfeit.enabled')),

                            Toggle::make('penalty_config.deduction.enabled')
                                ->label('−1 deduction')
                                ->inline(false)
                                ->hidden(fn (Get $get) => ! in_array($get('scoring_method'), ['first_to_n', 'timed_points'])),

                            Toggle::make('penalty_config.opponent_point.enabled')
                                ->label('+1 to opponent')
                                ->inline(false)
                                ->hidden(fn (Get $get) => ! in_array($get('scoring_method'), ['first_to_n', 'timed_points'])),
                        ]),
                ]),
            ])->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        $shouldUpdateDivisions = false;

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
                        'timed_points'   => 'Timed points',
                        'win_loss'       => 'Win / Loss',
                        default          => $state ?? '—',
                    })
            ])
            ->defaultSort('name')
            ->paginated(false)
            ->headerActions([
                CreateAction::make()
                    ->hidden(fn () => $this->getOwnerRecord()->status !== 'planning'),
            ])
            ->actions([
                EditAction::make()
                    ->hidden(fn () => $this->getOwnerRecord()->status !== 'planning')
                    ->mutateFormDataUsing(function (array $data) use (&$shouldUpdateDivisions): array {
                        $shouldUpdateDivisions = (bool) ($data['update_divisions'] ?? false);
                        unset($data['update_divisions']);
                        return $data;
                    })
                    ->after(function (CompetitionEvent $record) use (&$shouldUpdateDivisions): void {
                        if ($shouldUpdateDivisions) {
                            $count = $record->divisions()->update(['max_competitors' => $record->default_max_competitors]);
                            Notification::make()
                                ->success()
                                ->title("{$count} division(s) updated to match the new target.")
                                ->send();
                        }
                    }),

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
