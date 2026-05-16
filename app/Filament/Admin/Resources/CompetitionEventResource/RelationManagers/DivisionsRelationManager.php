<?php

namespace App\Filament\Admin\Resources\CompetitionEventResource\RelationManagers;

use App\Models\Division;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rules\Unique;

class DivisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'divisions';
    protected static ?string $title = 'Divisions';

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('code')
                ->required()
                ->maxLength(20)
                ->default(function (RelationManager $livewire): string {
                    $prefix = $livewire->ownerRecord->event_code;
                    if (! $prefix) {
                        return ''; // no event code set — leave blank
                    }
                    $lastNum = Division::where('competition_event_id', $livewire->ownerRecord->id)
                        ->whereNotNull('code')
                        ->where('code', 'like', $prefix . '%')
                        ->pluck('code')
                        ->map(fn ($c) => (int) substr($c, strlen($prefix)))
                        ->filter(fn ($n) => $n > 0)
                        ->max();
                    return $prefix . str_pad(($lastNum ?? 0) + 1, 2, '0', STR_PAD_LEFT);
                })
                ->unique(
                    table: 'divisions',
                    column: 'code',
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule, RelationManager $livewire) =>
                        $rule->where('competition_event_id', $livewire->ownerRecord->id),
                ),

            Select::make('sex')
                ->options(['M' => 'Male', 'F' => 'Female', 'mixed' => 'Mixed'])
                ->required()
                ->default('mixed'),

            Select::make('rank_band_id')
                ->label('Rank')
                ->placeholder('Open (All Ranks)')
                ->options(function (RelationManager $livewire) {
                    return $livewire->ownerRecord->competition->rankBands()
                        ->orderBy('sort_order')
                        ->pluck('label', 'id');
                })
                ->nullable(),

            TextInput::make('label')->required()->maxLength(255)->columnSpanFull(),

            Select::make('status')
                ->options([
                    'scheduled' => 'Scheduled',
                    'running'   => 'Running',
                    'complete'  => 'Complete',
                    'combined'  => 'Combined',
                ])
                ->required(),

            TextInput::make('target_score')->numeric()->nullable(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('code')->label('Code')->searchable(),
                TextColumn::make('label')->searchable()->wrap(),
                TextColumn::make('sex')->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'scheduled' => 'gray',
                        'running'   => 'info',
                        'complete'  => 'success',
                        'combined'  => 'warning',
                    }),
                TextColumn::make('enrolment_events_count')
                    ->label('Competitors')
                    ->counts('activeEnrolmentEvents'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'scheduled' => 'Scheduled',
                        'running'   => 'Running',
                        'complete'  => 'Complete',
                        'combined'  => 'Combined',
                    ]),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkAction::make('combine')
                    ->label('Combine into one division')
                    ->icon('heroicon-o-arrows-pointing-in')
                    ->requiresConfirmation()
                    ->modalDescription('All selected divisions will be merged. Competitors from all selected divisions will be moved into the first selected division. The others will be marked as Combined.')
                    ->action(function (Collection $records) {
                        if ($records->count() < 2) {
                            Notification::make()->title('Select at least 2 divisions to combine.')->warning()->send();
                            return;
                        }

                        $primary = $records->first();
                        $others = $records->slice(1);

                        foreach ($others as $division) {
                            // Move all active enrolment events to primary division
                            $division->activeEnrolmentEvents()->update(['division_id' => $primary->id]);

                            $division->update([
                                'status'           => 'combined',
                                'combined_into_id' => $primary->id,
                            ]);
                        }

                        // Update primary label to reflect merge
                        $primary->update([
                            'label' => $primary->label . ' (Combined)',
                        ]);

                        Notification::make()->title('Divisions combined successfully.')->success()->send();
                    }),
            ]);
    }
}
