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
                ->maxValue(99),

            TextInput::make('max_age')
                ->label('Max age')
                ->numeric()
                ->nullable()
                ->minValue(0)
                ->maxValue(99),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('label'),
                TextColumn::make('min_age')->label('Min'),
                TextColumn::make('max_age')->label('Max'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([
                CreateAction::make()
                    ->hidden(fn () => $this->getOwnerRecord()->status !== 'draft')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['sort_order'] = (AgeBand::where('competition_id', $this->getOwnerRecord()->id)->max('sort_order') ?? 0) + 1;
                        return $data;
                    })
                    ->before(function (array $data, $action) {
                        if ($error = $this->validateAgeBand($data)) {
                            Notification::make()->danger()->title('Invalid age range')->body($error)->send();
                            $action->halt();
                        }
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->hidden(fn () => $this->getOwnerRecord()->status !== 'draft')
                    ->before(function (array $data, $record, $action) {
                        if ($error = $this->validateAgeBand($data, $record->id)) {
                            Notification::make()->danger()->title('Invalid age range')->body($error)->send();
                            $action->halt();
                        }
                    }),

                DeleteAction::make()
                    ->hidden(fn () => $this->getOwnerRecord()->status !== 'draft')
                    ->before(fn ($record) => Division::where('age_band_id', $record->id)->delete())
                    ->modalDescription(function ($record) {
                        $count = Division::where('age_band_id', $record->id)->count();
                        return $count > 0
                            ? "This will also delete {$count} division(s) that use this age band."
                            : 'Are you sure you want to delete this age band?';
                    }),
            ]);
    }

    private function validateAgeBand(array $data, ?int $excludeId = null): ?string
    {
        $minAge = isset($data['min_age']) && $data['min_age'] !== '' ? (int) $data['min_age'] : null;
        $maxAge = isset($data['max_age']) && $data['max_age'] !== '' ? (int) $data['max_age'] : null;

        if ($minAge !== null && $maxAge !== null && $minAge >= $maxAge) {
            return 'Min age must be less than max age.';
        }

        // Only check overlap when both bounds are provided; open-ended ranges are allowed to overlap.
        if ($minAge !== null && $maxAge !== null) {
            $overlap = AgeBand::where('competition_id', $this->getOwnerRecord()->id)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->whereNotNull('min_age')
                ->whereNotNull('max_age')
                ->whereRaw('min_age <= ?', [$maxAge])
                ->whereRaw('max_age >= ?', [$minAge])
                ->first();

            if ($overlap) {
                return "Age range overlaps with \"{$overlap->label}\".";
            }
        }

        return null;
    }
}
