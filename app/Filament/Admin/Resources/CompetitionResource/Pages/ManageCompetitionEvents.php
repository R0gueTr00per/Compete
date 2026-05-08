<?php

namespace App\Filament\Admin\Resources\CompetitionResource\Pages;

use App\Filament\Admin\Resources\CompetitionResource;
use App\Models\Competition;
use App\Models\CompetitionEvent;
use App\Models\Division;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ManageCompetitionEvents extends ManageRelatedRecords
{
    protected static string $resource = CompetitionResource::class;
    protected static string $relationship = 'allDivisions';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationLabel(): string
    {
        return 'Events';
    }

    public function getTitle(): string
    {
        return $this->getRecord()->name . ' — Events';
    }

    public function getBreadcrumb(): string
    {
        return 'Events';
    }

    public function getRelationManagers(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('config')
                ->label('Configuration')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->url(fn () => CompetitionResource::getUrl('config', ['record' => $this->getRecord()])),

            Action::make('copyFromPrevious')
                ->label('Copy from previous competition')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Copy from previous competition')
                ->modalDescription('This will DELETE all existing event types and divisions for this competition, then copy them from the most recent previous competition. This cannot be undone.')
                ->modalSubmitActionLabel('Yes, clear and copy')
                ->action(function () {
                    $previous = Competition::where('id', '!=', $this->getRecord()->id)
                        ->orderByDesc('competition_date')
                        ->first();

                    if (! $previous) {
                        Notification::make()->warning()->title('No previous competition found.')->send();
                        return;
                    }

                    $this->getRecord()->allDivisions()->delete();
                    $this->getRecord()->competitionEvents()->delete();

                    $ageBandMap     = $this->getRecord()->ageBands()->pluck('id', 'label');
                    $rankBandMap    = $this->getRecord()->rankBands()->pluck('id', 'label');
                    $weightClassMap = $this->getRecord()->weightClasses()->pluck('id', 'label');

                    foreach ($previous->competitionEvents()->with('divisions.ageBand', 'divisions.rankBand', 'divisions.weightClass')->get() as $prevEvent) {
                        $newEvent = CompetitionEvent::create([
                            'competition_id'  => $this->getRecord()->id,
                            'event_type_id'   => $prevEvent->event_type_id,
                            'scoring_method'  => $prevEvent->scoring_method,
                            'division_filter' => $prevEvent->division_filter,
                            'judge_count'     => $prevEvent->judge_count,
                            'target_score'    => $prevEvent->target_score,
                            'status'          => 'scheduled',
                        ]);

                        foreach ($prevEvent->divisions as $div) {
                            Division::create([
                                'competition_event_id' => $newEvent->id,
                                'code'                 => $div->code,
                                'label'                => $div->label,
                                'age_band_id'          => $div->ageBand ? ($ageBandMap[$div->ageBand->label] ?? null) : null,
                                'rank_band_id'         => $div->rankBand ? ($rankBandMap[$div->rankBand->label] ?? null) : null,
                                'weight_class_id'      => $div->weightClass ? ($weightClassMap[$div->weightClass->label] ?? null) : null,
                                'sex'                  => $div->sex,
                                'running_order'        => $div->running_order,
                                'status'               => 'pending',
                            ]);
                        }
                    }

                    Notification::make()
                        ->success()
                        ->title("Copied event types and divisions from {$previous->name}")
                        ->send();
                }),

            Action::make('schedule')
                ->label('Scheduling')
                ->icon('heroicon-o-calendar-days')
                ->color('warning')
                ->url(fn () => CompetitionResource::getUrl('schedule', ['record' => $this->getRecord()])),

            Action::make('back')
                ->label('Back to competition')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => CompetitionResource::getUrl('edit', ['record' => $this->getRecord()])),
        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        return Division::create($data);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Division')->columns(2)->schema([
                Select::make('competition_event_id')
                    ->label('Event type')
                    ->options(fn () => $this->getRecord()->competitionEvents()
                        ->with('eventType')
                        ->get()
                        ->pluck('eventType.name', 'id')
                    )
                    ->required()
                    ->searchable()
                    ->columnSpanFull(),

                TextInput::make('code')
                    ->label('Division code')
                    ->maxLength(20)
                    ->required()
                    ->placeholder('e.g. KA01'),

                Select::make('sex')
                    ->label('Sex')
                    ->options(['M' => 'Male', 'F' => 'Female'])
                    ->nullable()
                    ->placeholder('Mixed'),

                Select::make('age_band_id')
                    ->label('Age band')
                    ->options(fn () => $this->getRecord()->ageBands()->pluck('label', 'id'))
                    ->nullable()
                    ->searchable(),

                Select::make('rank_band_id')
                    ->label('Rank band')
                    ->options(fn () => $this->getRecord()->rankBands()->pluck('label', 'id'))
                    ->nullable()
                    ->searchable(),

                Select::make('weight_class_id')
                    ->label('Weight class')
                    ->options(fn () => $this->getRecord()->weightClasses()->pluck('label', 'id'))
                    ->nullable()
                    ->searchable(),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('competitionEvent.eventType.name')
                    ->label('Event type')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('label')
                    ->label('Division')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending'   => 'gray',
                        'assigned'  => 'info',
                        'running'   => 'warning',
                        'complete'  => 'success',
                        'cancelled' => 'danger',
                        default     => 'gray',
                    }),
            ])
            ->groups([
                Group::make('competitionEvent.eventType.name')
                    ->label('Event type')
                    ->collapsible()
                    ->titlePrefixedWithLabel(false),
            ])
            ->defaultGroup('competitionEvent.eventType.name')
            ->defaultSort('code')
            ->defaultPaginationPageOption('all')
            ->emptyStateIcon('heroicon-o-squares-plus')
            ->emptyStateHeading('No divisions yet')
            ->emptyStateDescription('Divisions are copied automatically when a competition is created. Use "Copy from previous competition" to reset them.')
            ->filters([])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                TableAction::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('This division will be marked as cancelled and hidden from scoring. You can reinstate it later.')
                    ->visible(fn (Division $record) => ! in_array($record->status, ['cancelled', 'complete']))
                    ->action(fn (Division $record) => $record->update(['status' => 'cancelled'])),
                TableAction::make('reinstate')
                    ->label('Reinstate')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn (Division $record) => $record->status === 'cancelled')
                    ->action(fn (Division $record) => $record->update([
                        'status' => $record->location_label ? 'assigned' : 'pending',
                    ])),
                DeleteAction::make(),
            ]);
    }
}
