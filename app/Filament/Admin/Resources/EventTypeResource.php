<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\EventTypeResource\Pages;
use App\Models\EventType;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EventTypeResource extends Resource
{
    protected static ?string $model = EventType::class;
    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';
    protected static ?string $navigationGroup = 'Competitions';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationLabel = 'Event Types';
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make()->columns(2)->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(100)
                    ->columnSpanFull(),

                Select::make('scoring_method')
                    ->options([
                        'judges_total'   => 'Judges scores total',
                        'judges_average' => 'Judges scores averaged',
                        'first_to_n'     => 'First to N points',
                        'win_loss'       => 'Win / Loss',
                    ])
                    ->required(),

                Select::make('division_filter')
                    ->label('Division filter')
                    ->options([
                        'age_rank_sex'   => 'Age + Rank + Sex',
                        'age_sex'        => 'Age + Sex',
                        'weight_sex'     => 'Weight + Sex',
                        'age_rank'       => 'Age + Rank (no sex split)',
                        'age_only'       => 'Age only (no sex or rank split)',
                        'age_weight'     => 'Age + Weight (no sex split)',
                        'age_weight_sex' => 'Age + Weight + Sex',
                    ])
                    ->required(),

                TextInput::make('judge_count')
                    ->label('Number of judges')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->maxValue(5),

                TextInput::make('default_target_score')
                    ->label('Default target score (for first-to-N events)')
                    ->numeric()
                    ->nullable(),

                Toggle::make('requires_partner')
                    ->label('Requires a partner (e.g. Yakusuko)')
                    ->inline(false),

                Toggle::make('requires_weight_check')
                    ->label('Requires weight confirmation at check-in')
                    ->inline(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->sortable()->searchable(),

                TextColumn::make('scoring_method')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'judges_total', 'judges_average' => 'info',
                        'first_to_n'                     => 'warning',
                        'win_loss'                       => 'success',
                        default                          => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'judges_total'   => 'Judges total',
                        'judges_average' => 'Judges avg',
                        'first_to_n'     => 'First to N',
                        'win_loss'       => 'Win/Loss',
                        default          => $state,
                    }),

                TextColumn::make('division_filter')
                    ->label('Division filter')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'age_rank_sex' => 'Age + Rank + Sex',
                        'age_sex'      => 'Age + Sex',
                        'weight_sex'   => 'Weight + Sex',
                        'age_rank'     => 'Age + Rank',
                        'age_only'     => 'Age only',
                        default        => $state,
                    }),

                TextColumn::make('judge_count')->label('Judges'),

                IconColumn::make('requires_partner')->label('Partner')->boolean(),
                IconColumn::make('requires_weight_check')->label('Weight check')->boolean(),
            ])
            ->actions([EditAction::make()])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEventTypes::route('/'),
            'edit'  => Pages\EditEventType::route('/{record}/edit'),
        ];
    }
}
