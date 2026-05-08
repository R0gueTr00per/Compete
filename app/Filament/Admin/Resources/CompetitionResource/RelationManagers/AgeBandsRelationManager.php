<?php

namespace App\Filament\Admin\Resources\CompetitionResource\RelationManagers;

use App\Models\AgeBand;
use App\Models\Division;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AgeBandsRelationManager extends RelationManager
{
    protected static string $relationship = 'ageBands';
    protected static ?string $title = 'Age Bands';

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('label')
                ->required()
                ->maxLength(50)
                ->placeholder('e.g. 9–11'),

            TextInput::make('min_age')
                ->label('Min age')
                ->numeric()
                ->nullable()
                ->minValue(0)
                ->maxValue(120),

            TextInput::make('max_age')
                ->label('Max age')
                ->numeric()
                ->nullable()
                ->minValue(0)
                ->maxValue(120),

            TextInput::make('sort_order')
                ->numeric()
                ->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('sort_order')->label('#')->sortable(),
                TextColumn::make('label'),
                TextColumn::make('min_age')->label('Min'),
                TextColumn::make('max_age')->label('Max'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([
                CreateAction::make()
                    ->before(function (array $data, $action) {
                        if ($error = $this->overlapError($data)) {
                            Notification::make()->danger()->title('Overlapping age range')->body($error)->send();
                            $action->halt();
                        }
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->before(function (array $data, $record, $action) {
                        if ($error = $this->overlapError($data, $record->id)) {
                            Notification::make()->danger()->title('Overlapping age range')->body($error)->send();
                            $action->halt();
                        }
                    })
                    ->requiresConfirmation(fn ($record) => Division::where('age_band_id', $record->id)->exists())
                    ->modalHeading('Edit age band')
                    ->modalDescription(fn ($record) => Division::where('age_band_id', $record->id)->count() . ' division(s) use this age band and will be deleted when you save. You can regenerate divisions afterwards.')
                    ->after(fn ($record) => Division::where('age_band_id', $record->id)->delete()),

                DeleteAction::make()
                    ->before(fn ($record) => Division::where('age_band_id', $record->id)->delete())
                    ->modalDescription(function ($record) {
                        $count = Division::where('age_band_id', $record->id)->count();
                        return $count > 0
                            ? "This will also delete {$count} division(s) that use this age band."
                            : 'Are you sure you want to delete this age band?';
                    }),
            ]);
    }

    private function overlapError(array $data, ?int $excludeId = null): ?string
    {
        $newMin = isset($data['min_age']) ? (int) $data['min_age'] : 0;
        $newMax = isset($data['max_age']) ? (int) $data['max_age'] : 999;

        $overlap = AgeBand::where('competition_id', $this->getOwnerRecord()->id)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->whereRaw('COALESCE(min_age, 0) <= ?', [$newMax])
            ->whereRaw('COALESCE(max_age, 999) >= ?', [$newMin])
            ->first();

        return $overlap ? "Age range overlaps with \"{$overlap->label}\"." : null;
    }
}
