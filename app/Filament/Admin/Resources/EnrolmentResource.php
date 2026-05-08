<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Actions\HistoryTableAction;
use App\Filament\Admin\Resources\EnrolmentResource\Pages;
use App\Models\Competition;
use App\Models\CompetitionEvent;
use App\Models\Division;
use App\Models\Enrolment;
use App\Models\EnrolmentEvent;
use App\Services\DivisionAssignmentService;
use App\Services\EnrolmentService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EnrolmentResource extends Resource
{
    protected static ?string $model = Enrolment::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Competitions';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('competitor.name')
                    ->label('Competitor')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('enrolled_at')
                    ->label('Enrolled')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                IconColumn::make('is_late')
                    ->label('Late')
                    ->boolean()
                    ->trueColor('warning')
                    ->falseColor('gray'),

                TextColumn::make('fee_calculated')
                    ->label('Fee')
                    ->money('AUD')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending'   => 'warning',
                        'confirmed' => 'success',
                        'withdrawn' => 'danger',
                    }),

                TextColumn::make('active_events_count')
                    ->label('Events')
                    ->counts('activeEvents'),
            ])
            ->filters([
                SelectFilter::make('competition')
                    ->label('Competition')
                    ->relationship('competition', 'name')
                    ->searchable()
                    ->columnSpanFull(),

                SelectFilter::make('status')
                    ->options([
                        'pending'   => 'Pending',
                        'confirmed' => 'Confirmed',
                        'withdrawn' => 'Withdrawn',
                    ]),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(2)
            ->headerActions([
                Action::make('createEnrolment')
                    ->label('Add Enrolment')
                    ->icon('heroicon-o-plus')
                    ->url(fn () => \App\Filament\Admin\Pages\CreateAdminEnrolment::getUrl()),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('viewEvents')
                        ->label('View events')
                        ->icon('heroicon-o-eye')
                        ->modalContent(fn (Enrolment $record) => view(
                            'filament.admin.enrolment-events-modal',
                            ['enrolment' => $record->load(['activeEvents.competitionEvent.eventType', 'activeEvents.division'])]
                        ))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close'),

                    Action::make('addEvents')
                        ->label('Add events')
                        ->icon('heroicon-o-plus-circle')
                        ->color('success')
                        ->form(fn (Enrolment $record) => [
                            CheckboxList::make('event_ids')
                                ->label('Events to add')
                                ->options(function () use ($record) {
                                    $enrolled = $record->enrolmentEvents()
                                        ->where('removed', false)
                                        ->pluck('competition_event_id')
                                        ->toArray();
                                    return $record->competition->competitionEvents()
                                        ->with('eventType')
                                        ->where('status', 'scheduled')
                                        ->whereNotIn('id', $enrolled)
                                        ->get()
                                        ->mapWithKeys(fn ($e) => [
                                            $e->id => $e->event_code . ' — ' . $e->eventType->name,
                                        ])->toArray();
                                })
                                ->minItems(1),
                        ])
                        ->action(function (Enrolment $record, array $data) {
                            if (empty($data['event_ids'])) {
                                return;
                            }
                            app(EnrolmentService::class)->enrol(
                                $record->competitor,
                                $record->competition,
                                $data['event_ids'],
                                []
                            );
                            Notification::make()->title('Events added to enrolment.')->success()->send();
                        }),

                    Action::make('changeDivision')
                        ->label('Change division')
                        ->icon('heroicon-o-arrows-right-left')
                        ->color('warning')
                        ->form(fn (Enrolment $record) => [
                            Select::make('enrolment_event_id')
                                ->label('Event entry')
                                ->options(fn () => $record->activeEvents()
                                    ->with('competitionEvent.eventType', 'division')
                                    ->get()
                                    ->mapWithKeys(fn ($ee) => [
                                        $ee->id => $ee->competitionEvent->eventType->name
                                            . ' — ' . ($ee->division?->full_label ?? 'No division'),
                                    ])
                                )
                                ->required()
                                ->live(),

                            Select::make('division_id')
                                ->label('New division')
                                ->options(function (callable $get) use ($record) {
                                    $eeId = $get('enrolment_event_id');
                                    if (! $eeId) {
                                        return [];
                                    }
                                    $ee      = EnrolmentEvent::with('enrolment')->find($eeId);
                                    $profile = $record->competitor->competitorProfile;
                                    if (! $ee || ! $profile) {
                                        return [];
                                    }
                                    $svc = app(DivisionAssignmentService::class);
                                    $ctx = $svc->buildContext($profile, $ee->enrolment);
                                    return $svc->getEligibleDivisions($ee->competitionEvent, $ctx)
                                        ->pluck('label', 'id')
                                        ->toArray();
                                })
                                ->required(),
                        ])
                        ->action(function (Enrolment $record, array $data) {
                            $ee       = EnrolmentEvent::find($data['enrolment_event_id']);
                            $division = Division::find($data['division_id']);
                            if ($ee && $division && $ee->enrolment_id === $record->id) {
                                $ee->update(['division_id' => $division->id]);
                                Notification::make()->title('Division updated.')->success()->send();
                            }
                        }),

                    Action::make('removeFromEvent')
                        ->label('Remove from event')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->form([
                            Select::make('enrolment_event_id')
                                ->label('Event')
                                ->options(fn (Enrolment $record) => $record->activeEvents()
                                    ->with('competitionEvent.eventType')
                                    ->get()
                                    ->mapWithKeys(fn ($ee) => [
                                        $ee->id => $ee->competitionEvent->eventType->name,
                                    ])
                                )
                                ->required(),

                            Textarea::make('reason')
                                ->label('Reason for removal')
                                ->required()
                                ->rows(2),
                        ])
                        ->action(function (Enrolment $record, array $data) {
                            $ee = EnrolmentEvent::find($data['enrolment_event_id']);
                            if ($ee && $ee->enrolment_id === $record->id) {
                                app(EnrolmentService::class)
                                    ->removeParticipant($ee, auth()->user(), $data['reason']);

                                Notification::make()->title('Competitor removed from event.')->success()->send();
                            }
                        }),

                    Action::make('readdToEvent')
                        ->label('Re-add removed competitor')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('warning')
                        ->form([
                            Select::make('enrolment_event_id')
                                ->label('Removed event')
                                ->options(fn (Enrolment $record) => $record->enrolmentEvents()
                                    ->where('removed', true)
                                    ->with('competitionEvent.eventType')
                                    ->get()
                                    ->mapWithKeys(fn ($ee) => [
                                        $ee->id => $ee->competitionEvent->eventType->name
                                            . ' — ' . $ee->removal_reason,
                                    ])
                                )
                                ->required(),
                        ])
                        ->action(function (Enrolment $record, array $data) {
                            $ee = EnrolmentEvent::find($data['enrolment_event_id']);
                            if ($ee && $ee->enrolment_id === $record->id) {
                                app(EnrolmentService::class)->readdParticipant($ee);
                                Notification::make()->title('Competitor re-added to event.')->success()->send();
                            }
                        })
                        ->visible(fn (Enrolment $record) => $record->enrolmentEvents()
                            ->where('removed', true)->exists()),

                    HistoryTableAction::make(),
                ]),
            ])
            ->defaultSort('enrolled_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEnrolments::route('/'),
        ];
    }
}
