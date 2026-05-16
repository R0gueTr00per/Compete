<?php

namespace App\Filament\Admin\Resources\CompetitionResource\Pages;

use App\Filament\Admin\Resources\CompetitionResource;
use App\Models\Competition;
use App\Models\CompetitionEvent;
use App\Models\Division;
use App\Services\DivisionGenerationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Validation\Rules\Unique;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
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
            Action::make('generateDivisions')
                ->label('Generate Divisions')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->hidden(fn () => $this->getRecord()->status !== 'draft')
                ->requiresConfirmation()
                ->modalHeading('Generate Divisions')
                ->modalDescription(function () {
                    $competition = $this->getRecord();
                    $hasBands    = $competition->ageBands()->exists()
                        || $competition->weightClasses()->exists();

                    if (! $hasBands) {
                        return 'No age bands or weight classes are configured for this competition. '
                            . 'Add them in the Competition Edit screen first, then generate divisions.';
                    }

                    $events    = $competition->competitionEvents()->where('status', 'scheduled')->count();
                    $existing  = $competition->allDivisions()->count();
                    $locked    = $competition->allDivisions()
                        ->whereHas('activeEnrolmentEvents')->count();

                    return "This will create all band combinations for {$events} event type(s). "
                        . ($existing > 0
                            ? "{$existing} existing division(s) will be cleared"
                              . ($locked > 0 ? " ({$locked} with enrolments will be kept)" : '')
                              . '. '
                            : '')
                        . 'Divisions are generated from the competition\'s age bands, rank bands, '
                        . 'and weight classes. You can delete or combine any unwanted divisions afterward.';
                })
                ->modalSubmitActionLabel('Generate')
                ->action(function () {
                    $competition = $this->getRecord();

                    if (! $competition->ageBands()->exists() && ! $competition->weightClasses()->exists()) {
                        Notification::make()
                            ->warning()
                            ->title('No bands configured')
                            ->body('Add age bands, rank bands, and weight classes in the Competition Edit screen first.')
                            ->send();
                        return;
                    }

                    $count = app(DivisionGenerationService::class)->generateForCompetition($competition);

                    Notification::make()
                        ->success()
                        ->title("{$count} division(s) generated.")
                        ->send();
                }),

            Action::make('deleteAllDivisions')
                ->label('Delete all divisions')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->hidden(fn () => $this->getRecord()->status !== 'draft')
                ->requiresConfirmation()
                ->modalHeading('Delete all divisions')
                ->modalDescription(function () {
                    $competition = $this->getRecord();
                    $total       = $competition->allDivisions()->count();
                    $locked      = $competition->allDivisions()->whereHas('activeEnrolmentEvents')->count();
                    if ($locked > 0) {
                        return "Cannot delete — {$locked} division(s) have active enrolments. Remove enrolments first.";
                    }
                    return "This will permanently delete all {$total} division(s) for this competition. This cannot be undone.";
                })
                ->modalSubmitActionLabel('Delete all')
                ->action(function () {
                    $competition = $this->getRecord();
                    $locked      = $competition->allDivisions()->whereHas('activeEnrolmentEvents')->count();
                    if ($locked > 0) {
                        Notification::make()->danger()->title("Cannot delete — {$locked} division(s) have active enrolments.")->send();
                        return;
                    }
                    $eventIds = $competition->competitionEvents()->pluck('id');
                    $count    = Division::whereIn('competition_event_id', $eventIds)->count();
                    Division::whereIn('competition_event_id', $eventIds)->delete();
                    Notification::make()->success()->title("{$count} division(s) deleted.")->send();
                }),

            Action::make('copyFromPrevious')
                ->label('Copy from previous competition')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->hidden(fn () => $this->getRecord()->status !== 'draft')
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

                    if ($this->getRecord()->enrolments()->exists()) {
                        Notification::make()
                            ->danger()
                            ->title('Cannot replace event structure — this competition already has enrolments. Remove all enrolments first.')
                            ->send();
                        return;
                    }

                    // HasManyThrough::delete() is a no-op — use a direct query instead
                    $eventIds = $this->getRecord()->competitionEvents()->pluck('id');
                    Division::whereIn('competition_event_id', $eventIds)->delete();
                    $this->getRecord()->competitionEvents()->delete();

                    // Copy locations from previous competition if current has none
                    if ($this->getRecord()->competitionLocations()->doesntExist() && $previous->competitionLocations()->exists()) {
                        foreach ($previous->competitionLocations()->get() as $loc) {
                            $this->getRecord()->competitionLocations()->create(['name' => $loc->name, 'sort_order' => $loc->sort_order]);
                        }
                    }

                    // Copy bands from previous if the current competition has none yet
                    if ($this->getRecord()->ageBands()->doesntExist()) {
                        foreach ($previous->ageBands as $band) {
                            $this->getRecord()->ageBands()->create([
                                'label'      => $band->label,
                                'min_age'    => $band->min_age,
                                'max_age'    => $band->max_age,
                                'sort_order' => $band->sort_order,
                            ]);
                        }
                    }

                    if ($this->getRecord()->rankBands()->doesntExist()) {
                        foreach ($previous->rankBands as $band) {
                            $this->getRecord()->rankBands()->create([
                                'label'       => $band->label,
                                'description' => $band->description,
                                'rank_min'    => $band->rank_min,
                                'rank_max'    => $band->rank_max,
                                'sort_order'  => $band->sort_order,
                            ]);
                        }
                    }

                    if ($this->getRecord()->weightClasses()->doesntExist()) {
                        foreach ($previous->weightClasses as $band) {
                            $this->getRecord()->weightClasses()->create([
                                'label'      => $band->label,
                                'max_kg'     => $band->max_kg,
                                'sort_order' => $band->sort_order,
                            ]);
                        }
                    }

                    $ageBandMap     = $this->getRecord()->ageBands()->pluck('id', 'label');
                    $rankBandMap    = $this->getRecord()->rankBands()->pluck('id', 'label');
                    $weightClassMap = $this->getRecord()->weightClasses()->pluck('id', 'label');

                    foreach ($previous->competitionEvents()->with('divisions.ageBand', 'divisions.rankBand', 'divisions.weightClass')->get() as $prevEvent) {
                        $newEvent = CompetitionEvent::create([
                            'competition_id'       => $this->getRecord()->id,
                            'name'                 => $prevEvent->name,
                            'scoring_method'       => $prevEvent->scoring_method,
                            'tournament_format'    => $prevEvent->tournament_format,
                            'division_filter'      => $prevEvent->division_filter,
                            'judge_count'          => $prevEvent->judge_count,
                            'target_score'         => $prevEvent->target_score,
                            'default_score'        => $prevEvent->default_score,
                            'requires_partner'     => $prevEvent->requires_partner,
                            'status'               => 'scheduled',
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
        $filterIncludes = fn (array $filters) => fn (Get $get): bool => in_array(
            CompetitionEvent::find($get('competition_event_id'))?->effectiveDivisionFilter() ?? '',
            $filters
        );

        return $form->schema([
            Section::make('Division')->columns(2)->schema([
                Select::make('competition_event_id')
                    ->label('Event type')
                    ->options(fn () => $this->getRecord()->competitionEvents()->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function (?int $state, Set $set) {
                        $set('age_band_id', null);
                        $set('rank_band_id', null);
                        $set('weight_class_id', null);
                        $set('sex', 'mixed');
                        $set('code', '');
                        if (! $state) {
                            return;
                        }
                        $prefix = CompetitionEvent::find($state)?->event_code;
                        if (! $prefix) {
                            return;
                        }
                        $lastNum = Division::where('competition_event_id', $state)
                            ->whereNotNull('code')
                            ->where('code', 'like', $prefix . '%')
                            ->pluck('code')
                            ->map(fn ($c) => (int) substr($c, strlen($prefix)))
                            ->filter(fn ($n) => $n > 0)
                            ->max();
                        $set('code', $prefix . str_pad(($lastNum ?? 0) + 1, 2, '0', STR_PAD_LEFT));
                    }),

                TextInput::make('code')
                    ->label('Division code')
                    ->maxLength(20)
                    ->required()
                    ->unique(
                        table: 'divisions',
                        column: 'code',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get) =>
                            $rule->where('competition_event_id', $get('competition_event_id')),
                    ),

                Select::make('age_band_id')
                    ->label('Age band')
                    ->options(fn () => $this->getRecord()->ageBands()->pluck('label', 'id'))
                    ->nullable()
                    ->searchable()
                    ->visible($filterIncludes(['age_rank_sex', 'age_rank', 'age_sex', 'age_only', 'age_weight', 'age_weight_sex'])),

                Select::make('rank_band_id')
                    ->label('Rank band')
                    ->options(fn () => $this->getRecord()->rankBands()->pluck('label', 'id'))
                    ->nullable()
                    ->searchable()
                    ->visible($filterIncludes(['age_rank_sex', 'age_rank'])),

                Select::make('sex')
                    ->label('Sex')
                    ->options(['M' => 'Male', 'F' => 'Female', 'mixed' => 'Mixed'])
                    ->required()
                    ->default('mixed')
                    ->visible($filterIncludes(['age_rank_sex', 'age_sex', 'weight_sex', 'age_weight_sex'])),

                Select::make('weight_class_id')
                    ->label('Weight class')
                    ->options(fn () => $this->getRecord()->weightClasses()->get()->pluck('full_label', 'id'))
                    ->nullable()
                    ->searchable()
                    ->visible($filterIncludes(['weight_sex', 'age_weight', 'age_weight_sex'])),
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

                TextColumn::make('competitionEvent.name')
                    ->label('Event type')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('label')
                    ->label('Division')
                    ->sortable()
                    ->searchable(),
            ])
            ->defaultGroup(
                Group::make('competitionEvent.name')
                    ->label('Event type')
                    ->titlePrefixedWithLabel(false)
            )
            ->defaultPaginationPageOption('all')
            ->emptyStateIcon('heroicon-o-squares-plus')
            ->emptyStateHeading('No divisions yet')
            ->emptyStateDescription('Divisions are copied automatically when a competition is created. Use "Copy from previous competition" to reset them.')
            ->filters([])
            ->headerActions([
                CreateAction::make()
                    ->hidden(fn () => $this->getRecord()->status !== 'draft'),
            ])
            ->actions([
                EditAction::make()
                    ->hidden(fn () => $this->getRecord()->status !== 'draft'),
                DeleteAction::make()
                    ->hidden(fn () => $this->getRecord()->status !== 'draft'),
            ])
            ->bulkActions([
                BulkAction::make('combine')
                    ->hidden(fn () => $this->getRecord()->status !== 'draft')
                    ->label('Combine into one division')
                    ->icon('heroicon-o-arrows-pointing-in')
                    ->requiresConfirmation()
                    ->modalDescription('Competitors from all selected divisions will be moved into the first selected division. The others will be marked as Combined.')
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records) {
                        if ($records->count() < 2) {
                            Notification::make()->title('Select at least 2 divisions to combine.')->warning()->send();
                            return;
                        }

                        $primary = $records->first();
                        $others  = $records->slice(1);

                        foreach ($others as $division) {
                            $division->activeEnrolmentEvents()->update(['division_id' => $primary->id]);
                            $division->update([
                                'status'           => 'combined',
                                'combined_into_id' => $primary->id,
                            ]);
                        }

                        $primary->update(['label' => $primary->label . ' (Combined)']);

                        Notification::make()->title('Divisions combined.')->success()->send();
                    }),
            ]);
    }
}
