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
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
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
            ->modifyQueryUsing(fn ($query) => $query->with('competitor.competitorProfile'))
            ->header(view('filament.admin.partials.enrolment-competition-header'))
            ->columns([
                TextColumn::make('competitor_name')
                    ->label('Competitor')
                    ->getStateUsing(fn (Enrolment $record) => trim($record->competitor?->competitorProfile?->first_name . ' ' . $record->competitor?->competitorProfile?->surname) ?: $record->competitor?->email)
                    ->description(fn (Enrolment $record) => $record->display_rank)
                    ->searchable(query: fn ($query, $search) => $query->whereHas('competitor.competitorProfile', fn ($q) => $q->where('first_name', 'like', "%{$search}%")->orWhere('surname', 'like', "%{$search}%"))),

                TextColumn::make('age')
                    ->label('Age')
                    ->state(fn (Enrolment $record) => $record->competitor?->competitorProfile?->age)
                    ->suffix(' yrs')
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'hidden sm:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden sm:table-cell']),

                TextColumn::make('display_rank')
                    ->label('Rank')
                    ->extraHeaderAttributes(['class' => 'hidden sm:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden sm:table-cell']),

                TextColumn::make('weight_kg')
                    ->label('Weight')
                    ->formatStateUsing(fn ($state) => $state ? number_format((float) $state, 1) . ' kg' : '—'),

                TextColumn::make('enrolled_at')
                    ->label('Enrolled')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->extraHeaderAttributes(['class' => 'hidden sm:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden sm:table-cell']),

                IconColumn::make('is_late')
                    ->label('Late')
                    ->boolean()
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->extraHeaderAttributes(['class' => 'hidden sm:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden sm:table-cell']),

                TextColumn::make('fee_calculated')
                    ->label('Fee')
                    ->money('AUD')
                    ->sortable()
                    ->extraHeaderAttributes(['class' => 'hidden sm:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden sm:table-cell']),

                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'received'    => 'Paid',
                        'outstanding' => 'Outstanding',
                        default       => ucfirst($state),
                    })
                    ->color(fn (string $state) => match ($state) {
                        'received'    => 'success',
                        'outstanding' => 'warning',
                        default       => 'gray',
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending'    => 'warning',
                        'confirmed'  => 'success',
                        'checked_in' => 'info',
                        'withdrawn'  => 'danger',
                        default      => 'gray',
                    }),

                TextColumn::make('active_events_count')
                    ->label('Events')
                    ->counts('activeEvents')
                    ->extraHeaderAttributes(['class' => 'hidden sm:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden sm:table-cell']),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending'    => 'Pending',
                        'confirmed'  => 'Confirmed',
                        'checked_in' => 'Checked in',
                        'withdrawn'  => 'Withdrawn',
                    ]),
                SelectFilter::make('payment_status')
                    ->label('Payment')
                    ->options([
                        'outstanding' => 'Outstanding',
                        'received'    => 'Paid',
                    ]),
            ])
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
                            ['enrolment' => $record->load(['activeEvents.competitionEvent', 'activeEvents.division'])]
                        ))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close'),

                    Action::make('addEvents')
                        ->label('Add events')
                        ->icon('heroicon-o-plus-circle')
                        ->color('success')
                        ->form(fn (Enrolment $record) => [
                            CheckboxList::make('selected_entries')
                                ->label('Divisions to add')
                                ->options(function () use ($record) {
                                    $profile = $record->competitor->competitorProfile;
                                    if (! $profile) {
                                        return [];
                                    }
                                    $svc = app(DivisionAssignmentService::class);
                                    $ctx = $svc->buildContext($profile, $record);

                                    $enrolledDivisionIds = $record->activeEvents()
                                        ->whereNotNull('division_id')
                                        ->pluck('division_id')
                                        ->toArray();

                                    $enrolledEventIds = $record->activeEvents()
                                        ->pluck('competition_event_id')
                                        ->toArray();

                                    $events = $record->competition->competitionEvents()
                                        ->where('status', 'scheduled')
                                        ->orderBy('running_order')
                                        ->get();

                                    $options = [];
                                    foreach ($events as $event) {
                                        $eligible = $svc->getEligibleDivisions($event, $ctx);
                                        foreach ($eligible as $division) {
                                            if (in_array($division->id, $enrolledDivisionIds)) {
                                                continue;
                                            }
                                            $options["d{$division->id}"] =
                                                "{$event->event_code} — {$event->name}: {$division->label}";
                                        }
                                    }
                                    return $options;
                                })
                                ->minItems(1),
                        ])
                        ->action(function (Enrolment $record, array $data) {
                            if (empty($data['selected_entries'])) {
                                return;
                            }
                            $divisionsByEvent = [];
                            foreach ($data['selected_entries'] as $key) {
                                $divisionId = (int) substr($key, 1);
                                $division   = Division::find($divisionId);
                                if ($division) {
                                    $divisionsByEvent[$division->competition_event_id][] = $divisionId;
                                }
                            }
                            app(EnrolmentService::class)->enrol(
                                $record->competitor,
                                $record->competition,
                                array_keys($divisionsByEvent),
                                $divisionsByEvent
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
                                    ->with('competitionEvent', 'division')
                                    ->get()
                                    ->mapWithKeys(fn ($ee) => [
                                        $ee->id => $ee->competitionEvent->name
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
                                    ->with('competitionEvent')
                                    ->get()
                                    ->mapWithKeys(fn ($ee) => [
                                        $ee->id => $ee->competitionEvent->name,
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
                                    ->with('competitionEvent')
                                    ->get()
                                    ->mapWithKeys(fn ($ee) => [
                                        $ee->id => $ee->competitionEvent->name
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

                    Action::make('recordPayment')
                        ->label('Record payment')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->form(fn (Enrolment $record) => [
                            TextInput::make('payment_amount')
                                ->label('Amount received ($)')
                                ->numeric()
                                ->prefix('$')
                                ->default(fn () => $record->fee_calculated)
                                ->required(),
                        ])
                        ->action(function (Enrolment $record, array $data) {
                            $record->update([
                                'payment_status' => 'received',
                                'payment_amount' => $data['payment_amount'],
                            ]);
                            Notification::make()->title('Payment recorded.')->success()->send();
                        })
                        ->visible(fn (Enrolment $record) => $record->payment_status !== 'received'),

                    Action::make('markPaymentOutstanding')
                        ->label('Mark payment outstanding')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (Enrolment $record) {
                            $record->update([
                                'payment_status' => 'outstanding',
                                'payment_amount' => null,
                            ]);
                            Notification::make()->title('Payment marked outstanding.')->warning()->send();
                        })
                        ->visible(fn (Enrolment $record) => $record->payment_status === 'received'),

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
