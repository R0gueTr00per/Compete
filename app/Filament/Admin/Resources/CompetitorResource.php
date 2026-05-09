<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Actions\HistoryTableAction;
use App\Filament\Admin\Resources\CompetitorResource\Pages;
use App\Models\CompetitorProfile;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CompetitorResource extends Resource
{
    protected static ?string $model = CompetitorProfile::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Competitors';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Competitors';
    protected static ?string $recordTitleAttribute = 'surname';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Personal Details')
                ->columns(2)
                ->schema([
                    TextInput::make('surname')
                        ->required()
                        ->maxLength(100),

                    TextInput::make('first_name')
                        ->required()
                        ->maxLength(100),

                    DatePicker::make('date_of_birth')
                        ->required()
                        ->maxDate(now()->subYears(5)),

                    Radio::make('gender')
                        ->options(['M' => 'Male', 'F' => 'Female'])
                        ->required()
                        ->inline(),

                    TextInput::make('phone')
                        ->tel()
                        ->maxLength(30),

                    TextInput::make('height_cm')
                        ->numeric()
                        ->suffix('cm')
                        ->minValue(50)
                        ->maxValue(250),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('surname')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('first_name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('date_of_birth')
                    ->label('DOB')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('age')
                    ->label('Age')
                    ->sortable(query: fn ($query, string $direction) =>
                        $query->orderBy('date_of_birth', $direction === 'asc' ? 'desc' : 'asc')
                    ),

                TextColumn::make('gender')
                    ->badge()
                    ->color(fn (string $state) => $state === 'M' ? 'info' : 'danger')
                    ->formatStateUsing(fn (string $state) => $state === 'M' ? 'Male' : 'Female'),

                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('user.status')
                    ->label('Account')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active'   => 'success',
                        'pending'  => 'warning',
                        'inactive' => 'danger',
                        default    => 'gray',
                    }),

                IconColumn::make('profile_complete')
                    ->label('Profile')
                    ->boolean(),

                TextColumn::make('enrolments_count')
                    ->label('Enrolments')
                    ->getStateUsing(fn (CompetitorProfile $record) => $record->user?->enrolments()->count() ?? 0)
                    ->sortable(false),
            ])
            ->defaultSort('surname')
            ->filters([
                SelectFilter::make('gender')
                    ->options(['M' => 'Male', 'F' => 'Female']),

                SelectFilter::make('profile_complete')
                    ->label('Profile')
                    ->options([
                        '1' => 'Complete',
                        '0' => 'Incomplete',
                    ]),
            ])
            ->actions([
                EditAction::make(),
                ActionGroup::make([
                    HistoryTableAction::make(),
                ]),
            ]);
    }

    public static function getRelationManagers(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompetitors::route('/'),
            'edit'  => Pages\EditCompetitor::route('/{record}/edit'),
        ];
    }
}
