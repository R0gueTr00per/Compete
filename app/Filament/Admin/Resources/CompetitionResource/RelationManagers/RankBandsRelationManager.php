<?php

namespace App\Filament\Admin\Resources\CompetitionResource\RelationManagers;

use App\Models\Division;
use App\Models\RankBand;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RankBandsRelationManager extends RelationManager
{
    protected static string $relationship = 'rankBands';
    protected static ?string $title = 'Rank Bands';

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('label')
                ->required()
                ->maxLength(50)
                ->placeholder('e.g. 10–6 Kyu')
                ->columnSpanFull(),

            TextInput::make('description')
                ->maxLength(255)
                ->nullable()
                ->columnSpanFull(),

            Placeholder::make('rank_hint')
                ->label('')
                ->content('Rank scale: 9th kyu = −9, 1st kyu = −1, experience = 0, 1st dan = 1, 10th dan = 10. Leave blank for open/no restriction.')
                ->columnSpanFull(),

            Grid::make(2)->schema([
                TextInput::make('rank_min')
                    ->label('Rank min')
                    ->numeric()
                    ->nullable()
                    ->placeholder('e.g. −9'),

                TextInput::make('rank_max')
                    ->label('Rank max')
                    ->numeric()
                    ->nullable()
                    ->placeholder('e.g. −1'),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('label'),
                TextColumn::make('rank_min')->label('Min rank')->default('—'),
                TextColumn::make('rank_max')->label('Max rank')->default('—'),
                TextColumn::make('description')->default('—'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([
                CreateAction::make()
                    ->hidden(fn () => $this->getOwnerRecord()->status !== 'draft')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['sort_order'] = (RankBand::where('competition_id', $this->getOwnerRecord()->id)->max('sort_order') ?? 0) + 1;
                        return $data;
                    })
                    ->before(function (array $data, $action) {
                        if ($error = $this->overlapError($data)) {
                            Notification::make()->danger()->title('Overlapping rank range')->body($error)->send();
                            $action->halt();
                        }
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->hidden(fn () => $this->getOwnerRecord()->status !== 'draft')
                    ->before(function (array $data, $record, $action) {
                        if ($error = $this->overlapError($data, $record->id)) {
                            Notification::make()->danger()->title('Overlapping rank range')->body($error)->send();
                            $action->halt();
                        }
                    }),

                DeleteAction::make()
                    ->hidden(fn () => $this->getOwnerRecord()->status !== 'draft')
                    ->before(fn ($record) => Division::where('rank_band_id', $record->id)->delete())
                    ->modalDescription(function ($record) {
                        $count = Division::where('rank_band_id', $record->id)->count();
                        return $count > 0
                            ? "This will also delete {$count} division(s) that use this rank band."
                            : 'Are you sure you want to delete this rank band?';
                    }),
            ]);
    }

    private function overlapError(array $data, ?int $excludeId = null): ?string
    {
        $rankMax = isset($data['rank_max']) && $data['rank_max'] !== '' ? (int) $data['rank_max'] : null;

        // Open-ended (no max rank) entries are allowed to overlap — skip validation.
        if ($rankMax === null) {
            return null;
        }

        $rankMin = isset($data['rank_min']) && $data['rank_min'] !== '' ? (int) $data['rank_min'] : -99;

        $overlap = RankBand::where('competition_id', $this->getOwnerRecord()->id)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->whereNotNull('rank_max')
            ->whereRaw('COALESCE(rank_min, -99) <= ?', [$rankMax])
            ->whereRaw('rank_max >= ?', [$rankMin])
            ->first();

        return $overlap ? "Rank range overlaps with \"{$overlap->label}\"." : null;
    }
}
