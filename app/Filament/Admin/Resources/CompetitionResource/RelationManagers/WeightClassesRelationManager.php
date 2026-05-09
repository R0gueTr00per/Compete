<?php

namespace App\Filament\Admin\Resources\CompetitionResource\RelationManagers;

use App\Models\Division;
use App\Models\WeightClass;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WeightClassesRelationManager extends RelationManager
{
    protected static string $relationship = 'weightClasses';
    protected static ?string $title = 'Weight Classes';

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('label')
                ->required()
                ->maxLength(50)
                ->placeholder('e.g. Flyweight'),

            TextInput::make('max_kg')
                ->label('Max weight (kg)')
                ->numeric()
                ->nullable()
                ->suffix('kg')
                ->helperText('Leave blank for the open/heavyweight class.'),

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
                TextColumn::make('full_label')->label('Name'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([
                CreateAction::make()
                    ->before(function (array $data, $action) {
                        if ($error = $this->duplicateError($data)) {
                            Notification::make()->danger()->title('Duplicate weight class')->body($error)->send();
                            $action->halt();
                        }
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->before(function (array $data, $record, $action) {
                        if ($error = $this->duplicateError($data, $record->id)) {
                            Notification::make()->danger()->title('Duplicate weight class')->body($error)->send();
                            $action->halt();
                        }
                    })
                    ->requiresConfirmation(fn ($record) => Division::where('weight_class_id', $record->id)->exists())
                    ->modalHeading('Edit weight class')
                    ->modalDescription(fn ($record) => Division::where('weight_class_id', $record->id)->count() . ' division(s) use this weight class and will be deleted when you save. You can regenerate divisions afterwards.')
                    ->after(fn ($record) => Division::where('weight_class_id', $record->id)->delete()),

                DeleteAction::make()
                    ->before(fn ($record) => Division::where('weight_class_id', $record->id)->delete())
                    ->modalDescription(function ($record) {
                        $count = Division::where('weight_class_id', $record->id)->count();
                        return $count > 0
                            ? "This will also delete {$count} division(s) that use this weight class."
                            : 'Are you sure you want to delete this weight class?';
                    }),
            ]);
    }

    private function duplicateError(array $data, ?int $excludeId = null): ?string
    {
        $maxKg = isset($data['max_kg']) && $data['max_kg'] !== '' ? $data['max_kg'] : null;

        $query = WeightClass::where('competition_id', $this->getOwnerRecord()->id)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId));

        $duplicate = $maxKg === null
            ? $query->whereNull('max_kg')->first()
            : $query->where('max_kg', $maxKg)->first();

        if (! $duplicate) {
            return null;
        }

        return $maxKg === null
            ? "An open/heavyweight class already exists (\"{$duplicate->label}\")."
            : "A weight class with max {$maxKg} kg already exists (\"{$duplicate->label}\").";
    }
}
