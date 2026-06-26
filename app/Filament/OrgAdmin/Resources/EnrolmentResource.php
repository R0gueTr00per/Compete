<?php

namespace App\Filament\OrgAdmin\Resources;

use App\Filament\OrgAdmin\Actions\HistoryTableAction;
use App\Filament\OrgAdmin\Resources\EnrolmentResource\Pages;
use App\Models\Division;
use App\Models\Enrolment;
use App\Models\EnrolmentEvent;
use App\Services\DivisionAssignmentService;
use App\Services\EnrolmentService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use App\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EnrolmentResource extends Resource
{
    protected static ?string $model             = Enrolment::class;
    protected static ?string $modelLabel        = 'Registration';
    protected static ?string $pluralModelLabel  = 'Registrations';
    protected static ?string $navigationLabel   = 'Registrations';
    protected static string | \BackedEnum | null $navigationIcon    = 'heroicon-o-clipboard-document-check';
    protected static string | \UnitEnum | null $navigationGroup = 'Competitions';
    protected static ?int    $navigationSort  = 2;

    public static function canAccess(): bool
    {
        $tenant = app('tenant');
        if (! $tenant) return true;
        $user = auth()->user();
        if ($user->isOrgAdmin($tenant)) return true;
        return $user->getActiveOfficialRoleFor($tenant)?->can_access_enrolments ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereNotIn('status', ['draft'])
            ->whereHas('competition', fn (Builder $q) => $q->where('organisation_id', app('tenant')?->id));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(25)
            ->modifyQueryUsing(fn ($query) => $query
                ->with([
                    'competitor',
                    'rank',
                    'competition.competitionDays',
                    'cart.acceptedBy',
                    'checkIns',
                    'activeEvents.competitionEvent',
                    'activeEvents.division',
                    'activeEvents.previousDivision',
                    'enrolmentEvents' => fn ($q) => $q->where('removed', true)->with('competitionEvent', 'division'),
                ])
                ->withCount(['enrolmentEvents as removed_events_count' => fn ($q) => $q->where('removed', true)]))
            ->header(function ($livewire) {
                $competition = $livewire->competition_id
                    ? \App\Models\Competition::withCount([
                        'enrolments as enrolled_count' => fn ($q) => $q->whereNotIn('status', ['draft']),
                    ])->find($livewire->competition_id)
                    : null;

                return view('filament.admin.partials.enrolment-competition-header', compact('competition'));
            })
            ->columns([
                TextColumn::make('competitor_name')
                    ->label('Competitor')
                    ->getStateUsing(fn (Enrolment $record) => $record->competitor?->full_name ?: '—')
                    ->description(function (Enrolment $record) {
                        $parts = array_filter([
                            $record->competitor?->age ? $record->competitor->age . ' yrs' : null,
                            ($record->display_rank !== '—') ? $record->display_rank : null,
                            $record->competitor?->gender ? ucfirst($record->competitor->gender) : null,
                            $record->weight_kg ? number_format((float) $record->weight_kg, 1) . ' kg' : null,
                        ]);
                        $suffix = $record->is_late ? ' · Late' : '';
                        return implode(' · ', $parts) . $suffix ?: null;
                    })
                    ->searchable(query: fn ($query, $search) => $query->whereHas('competitor', fn ($q) => $q->where('first_name', 'like', "%{$search}%")->orWhere('surname', 'like', "%{$search}%"))),

                ViewColumn::make('events_column')
                    ->label('Events')
                    ->view('filament.admin.columns.enrolment-events-column')
                    ->visibleFrom('md'),

                TextColumn::make('enrolled_at')
                    ->label('Registered')
                    ->formatStateUsing(fn ($state) => $state ? tenant_datetime($state) : '—')
                    ->sortable()
                    ->visibleFrom('md'),

                TextColumn::make('payment_accepted_by')
                    ->label('Payment taken by')
                    ->getStateUsing(fn (Enrolment $record) => $record->cart?->acceptedBy?->full_name ?? '—')
                    ->visibleFrom('md'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'pending'   => 'Pending',
                        'confirmed' => 'Confirmed',
                        'withdrawn' => 'Withdrawn',
                        default     => ucfirst($state),
                    })
                    ->color(fn (string $state) => match ($state) {
                        'pending'   => 'warning',
                        'confirmed' => 'success',
                        'withdrawn' => 'danger',
                        default     => 'gray',
                    }),

                TextColumn::make('check_in_days')
                    ->label('Check-in')
                    ->badge()
                    ->getStateUsing(function (Enrolment $record): string {
                        $total   = $record->competition?->competitionDays?->count() ?? 0;
                        $checked = $record->checkIns->count();

                        if ($checked === 0) {
                            return '—';
                        }
                        if ($total <= 1) {
                            return 'Checked In';
                        }
                        return "{$checked} of {$total} days";
                    })
                    ->color(function (Enrolment $record): string {
                        $total   = $record->competition?->competitionDays?->count() ?? 0;
                        $checked = $record->checkIns->count();

                        if ($checked === 0) return 'gray';
                        if ($total > 0 && $checked >= $total) return 'success';
                        return 'info';
                    }),

            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending'   => 'Pending',
                        'confirmed' => 'Confirmed',
                        'withdrawn' => 'Withdrawn',
                    ]),
                SelectFilter::make('payment_status')
                    ->label('Payment')
                    ->options([
                        'outstanding' => 'Outstanding',
                        'received'    => 'Paid',
                    ])
                    ->query(function ($query, array $data) {
                        if (empty($data['value'])) {
                            return;
                        }
                        if ($data['value'] === 'received') {
                            $query->whereHas('cart', fn ($q) => $q->where('payment_status', 'received'));
                        } else {
                            $query->whereDoesntHave('cart', fn ($q) => $q->where('payment_status', 'received'));
                        }
                    }),
            ])
            ->headerActions([
                Action::make('createEnrolment')
                    ->label('Add Registration')
                    ->icon('heroicon-o-plus')
                    ->url(fn () => \App\Filament\OrgAdmin\Pages\CreateAdminEnrolment::getUrl()),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('viewEvents')
                        ->label('View events')
                        ->icon('heroicon-o-eye')
                        ->slideOver()
                        ->modalContent(fn (Enrolment $record) => view(
                            'filament.admin.enrolment-events-modal',
                            ['enrolment' => $record->load([
                                'activeEvents.competitionEvent',
                                'activeEvents.division',
                                'activeEvents.previousDivision',
                                'enrolmentEvents' => fn ($q) => $q->where('removed', true)->with('competitionEvent', 'division'),
                                'competition',
                            ])]
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
                                    $profile = $record->competitor;
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
                            Notification::make()->title('Events added to registration.')->success()->send();
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
                                    $profile = $record->competitor;
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
                                $ee->update([
                                    'previous_division_id' => $ee->division_id,
                                    'division_id'          => $division->id,
                                ]);
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
                                    ->with('competitionEvent', 'division')
                                    ->get()
                                    ->mapWithKeys(fn ($ee) => [
                                        $ee->id => $ee->competitionEvent->name
                                            . ($ee->division ? ' — ' . $ee->division->label : ''),
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
                                app(EnrolmentService::class)->removeParticipant(
                                    $ee,
                                    auth()->user(),
                                    $data['reason'],
                                    false,
                                    'admin_cancelled'
                                );
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
                        ->visible(fn (Enrolment $record) => $record->removed_events_count > 0),

                    HistoryTableAction::make(),
                ])->dropdownPlacement('bottom-start'),
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
