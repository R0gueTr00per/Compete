<?php

namespace App\Filament\Portal\Pages;

use App\Models\Competition;
use App\Models\EnrolmentEvent;
use App\Models\User;
use App\Services\DivisionAssignmentService;
use App\Services\EnrolmentService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class EnrolPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-pencil-square';
    protected static ?string $navigationLabel = 'Enrol';
    protected static string  $view            = 'filament.portal.pages.enrol-page';
    protected static ?string $slug            = 'enrol';

    public ?int    $competition_id      = null;
    public ?string $dojo_type           = null;
    public ?string $dojo_name           = null;
    public ?string $guest_style         = null;
    public ?string $rank_type           = null;
    public ?int    $rank_kyu            = null;
    public ?int    $rank_dan            = null;
    public ?int    $experience_years    = null;
    public ?int    $experience_months   = null;
    public ?float  $weight_kg           = null;
    public array   $selected_event_ids  = [];
    public array   $selected_divisions  = [];
    public array   $yakusuko_partners   = [];

    public function mount(): void
    {
        $open = Competition::where('status', 'open')->orderBy('competition_date')->first();
        if ($open) {
            $this->competition_id = $open->id;
        }
    }

    public function getSelectedCompetition(): ?Competition
    {
        return $this->competition_id ? Competition::find($this->competition_id) : null;
    }

    public function isSelectedCompetitionLocked(): bool
    {
        $comp = $this->getSelectedCompetition();
        return $comp && ! $comp->isEnrolmentOpen();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Competition')
                ->schema([
                    Select::make('competition_id')
                        ->label('Select competition')
                        ->options(function () {
                            $enrolledDraftIds = auth()->user()
                                ->enrolments()
                                ->whereHas('competition', fn ($q) => $q->where('status', 'draft'))
                                ->pluck('competition_id');

                            return Competition::where('status', 'open')
                                ->orWhereIn('id', $enrolledDraftIds)
                                ->orderBy('competition_date')
                                ->get()
                                ->mapWithKeys(fn ($c) => [
                                    $c->id => $c->name . ($c->status === 'draft' ? ' (Draft — locked)' : ''),
                                ]);
                        })
                        ->required()
                        ->live()
                        ->afterStateUpdated(function () {
                            $this->selected_event_ids = [];
                            $this->selected_divisions = [];
                            $this->yakusuko_partners  = [];
                        }),
                ]),

            Section::make('Your details for this competition')
                ->description('Rank, weight, and dojo may change between competitions — please confirm them here.')
                ->visible(fn () => $this->competition_id !== null)
                ->columns(2)
                ->schema([
                    Radio::make('dojo_type')
                        ->label('Membership type')
                        ->options(['lfp' => 'LFP Dojo member', 'guest' => 'Guest competitor'])
                        ->required()
                        ->inline()
                        ->live()
                        ->columnSpanFull(),

                    TextInput::make('dojo_name')
                        ->label('LFP Dojo name')
                        ->maxLength(100)
                        ->visible(fn () => $this->dojo_type === 'lfp')
                        ->requiredIf('dojo_type', 'lfp'),

                    TextInput::make('guest_style')
                        ->label('Martial arts style')
                        ->maxLength(100)
                        ->visible(fn () => $this->dojo_type === 'guest')
                        ->requiredIf('dojo_type', 'guest'),

                    Radio::make('rank_type')
                        ->label('Rank type')
                        ->options([
                            'kyu'        => 'Kyu grade',
                            'dan'        => 'Dan grade',
                            'experience' => 'Years of experience',
                        ])
                        ->required()
                        ->inline()
                        ->live()
                        ->columnSpanFull(),

                    TextInput::make('rank_kyu')
                        ->label('Kyu grade')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(10)
                        ->suffix('Kyu')
                        ->visible(fn () => $this->rank_type === 'kyu')
                        ->requiredIf('rank_type', 'kyu'),

                    TextInput::make('rank_dan')
                        ->label('Dan grade')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(10)
                        ->suffix('Dan')
                        ->visible(fn () => $this->rank_type === 'dan')
                        ->requiredIf('rank_type', 'dan'),

                    TextInput::make('experience_years')
                        ->label('Years')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(80)
                        ->visible(fn () => $this->rank_type === 'experience'),

                    TextInput::make('experience_months')
                        ->label('Months (in addition to years)')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(11)
                        ->visible(fn () => $this->rank_type === 'experience'),

                    TextInput::make('weight_kg')
                        ->label('Weight (kg)')
                        ->numeric()
                        ->suffix('kg')
                        ->minValue(5)
                        ->maxValue(250),
                ]),

            Section::make('Events')
                ->visible(fn () => $this->competition_id !== null && $this->dojo_type !== null && $this->rank_type !== null)
                ->schema(fn () => $this->buildEventSchema()),

            Section::make('Fee summary')
                ->visible(fn () => count($this->selected_event_ids) > 0)
                ->schema([
                    Placeholder::make('fee_summary')
                        ->label('')
                        ->content(fn () => $this->feeSummary()),
                ]),
        ]);
    }

    private function buildCtx(): ?object
    {
        $profile = auth()->user()->competitorProfile;
        if (! $profile) {
            return null;
        }

        return (object) [
            'gender'            => $profile->gender,
            'age'               => $profile->age,
            'rank_type'         => $this->rank_type,
            'rank_kyu'          => $this->rank_kyu,
            'rank_dan'          => $this->rank_dan,
            'experience_years'  => $this->experience_years,
            'experience_months' => $this->experience_months,
            'weight_kg'         => $this->weight_kg,
        ];
    }

    private function buildEventSchema(): array
    {
        if (! $this->competition_id) {
            return [];
        }

        $competition = Competition::find($this->competition_id);
        if (! $competition) {
            return [];
        }

        $events = $competition->competitionEvents()
            ->with('eventType')
            ->where('status', 'scheduled')
            ->orderBy('running_order')
            ->get();

        $existingEnrolment = $competition->enrolments()
            ->where('competitor_id', auth()->id())
            ->with('activeEvents')
            ->first();

        $alreadyEnrolledEventIds = $existingEnrolment
            ? $existingEnrolment->activeEvents->pluck('competition_event_id')->toArray()
            : [];

        $components = [
            CheckboxList::make('selected_event_ids')
                ->label('Select events to enrol in')
                ->options(
                    $events->mapWithKeys(fn ($e) => [
                        $e->id => $e->event_code . ' — ' . $e->eventType->name
                            . ($e->location_label ? " ({$e->location_label})" : ''),
                    ])->toArray()
                )
                ->disableOptionWhen(fn (string $value) => in_array((int) $value, $alreadyEnrolledEventIds))
                ->live()
                ->afterStateUpdated(function () {
                    $this->selected_divisions = array_filter(
                        $this->selected_divisions,
                        fn ($k) => in_array($k, $this->selected_event_ids),
                        ARRAY_FILTER_USE_KEY
                    );
                    $this->yakusuko_partners = array_filter(
                        $this->yakusuko_partners,
                        fn ($k) => in_array($k, $this->selected_event_ids),
                        ARRAY_FILTER_USE_KEY
                    );
                })
                ->helperText($alreadyEnrolledEventIds ? 'Greyed-out events are already in your enrolment.' : null),
        ];

        $ctx             = $this->buildCtx();
        $divisionService = app(DivisionAssignmentService::class);

        foreach ($this->selected_event_ids as $eventId) {
            $event = $events->firstWhere('id', $eventId);
            if (! $event) {
                continue;
            }

            $eligible = $ctx
                ? $divisionService->getEligibleDivisions($event, $ctx)
                : collect();

            if ($eligible->isEmpty()) {
                $components[] = Placeholder::make("division_info_{$eventId}")
                    ->label("{$event->event_code} — {$event->eventType->name} — Division")
                    ->content('No divisions configured for this event yet. You will be assigned when divisions are finalised.');
                continue;
            }

            $components[] = CheckboxList::make("selected_divisions.{$eventId}")
                ->label("{$event->event_code} — {$event->eventType->name} — Select your division(s)")
                ->options(
                    $eligible->mapWithKeys(fn ($d) => [
                        $d->id => $d->label . ($this->isDivisionOpen($d) ? ' (Open)' : ''),
                    ])->toArray()
                )
                ->minItems(1)
                ->helperText('Select one or more divisions. You may enter both an age/rank division and Open.');
        }

        // Yakusuko partner selects
        $yakusukoEventIds = $events
            ->filter(fn ($e) => $e->eventType->requires_partner
                && in_array($e->id, $this->selected_event_ids))
            ->pluck('id');

        foreach ($yakusukoEventIds as $eventId) {
            $components[] = Select::make("yakusuko_partners.{$eventId}")
                ->label("Yakusuko partner for event #{$eventId}")
                ->options(
                    User::whereHas('competitorProfile', fn ($q) => $q->where('profile_complete', true))
                        ->where('id', '!=', auth()->id())
                        ->get()
                        ->mapWithKeys(fn ($u) => [
                            $u->id => ($u->competitorProfile->surname ?? '') . ', '
                                    . ($u->competitorProfile->first_name ?? '') . " ({$u->email})",
                        ])
                )
                ->searchable()
                ->nullable()
                ->helperText('Both you and your partner must independently enrol.');
        }

        return $components;
    }

    private function isDivisionOpen($division): bool
    {
        return $division->age_band_id === null
            && $division->rank_band_id === null
            && $division->weight_class_id === null;
    }

    private function feeSummary(): string
    {
        if (! $this->competition_id || count($this->selected_event_ids) === 0) {
            return '';
        }

        $competition = Competition::find($this->competition_id);
        if (! $competition) {
            return '';
        }

        $existingCount = $competition->enrolments()
            ->where('competitor_id', auth()->id())
            ->withCount('activeEvents')
            ->first()?->active_events_count ?? 0;

        $newCount = 0;
        foreach ($this->selected_event_ids as $eid) {
            $divs = $this->selected_divisions[$eid] ?? [];
            $newCount += max(1, count((array) $divs));
        }
        $total  = $existingCount + $newCount;
        $isLate = $competition->isLateEnrolment();

        $fee   = app(EnrolmentService::class)->calculateFee($competition, $total, $isLate);
        $lines = ["**{$newCount} new event entr" . ($newCount === 1 ? 'y' : 'ies') . "** added to your enrolment."];
        if ($isLate) {
            $lines[] = "Late surcharge of \${$competition->late_surcharge} applies.";
        }
        $lines[] = "**Total fee: \${$fee}**";

        return implode("\n\n", $lines);
    }

    public function submit(): void
    {
        $profile = auth()->user()->competitorProfile;

        if (! $profile || ! $profile->profile_complete) {
            Notification::make()
                ->title('Please complete your profile before enrolling.')
                ->warning()
                ->send();
            $this->redirect(route('filament.portal.pages.profile'));
            return;
        }

        if (! $this->competition_id || count($this->selected_event_ids) === 0) {
            Notification::make()->title('Please select at least one event.')->warning()->send();
            return;
        }

        if (! $this->dojo_type || ! $this->rank_type) {
            Notification::make()->title('Please fill in your competition details (dojo and rank).')->warning()->send();
            return;
        }

        $competition = Competition::find($this->competition_id);
        if (! $competition || ! $competition->isEnrolmentOpen()) {
            Notification::make()->title('Enrolment is not currently open for this competition.')->danger()->send();
            return;
        }

        // Enforce division selection for each event that has divisions configured
        $ctx             = $this->buildCtx();
        $divisionService = app(DivisionAssignmentService::class);
        $events          = $competition->competitionEvents()->with('eventType')->get();

        foreach ($this->selected_event_ids as $eventId) {
            $event    = $events->firstWhere('id', $eventId);
            $eligible = $ctx ? $divisionService->getEligibleDivisions($event, $ctx) : collect();

            if ($eligible->isNotEmpty()) {
                $chosen = array_filter((array) ($this->selected_divisions[$eventId] ?? []));
                if (empty($chosen)) {
                    Notification::make()
                        ->title('Please select a division for: ' . $event->event_code . ' — ' . $event->eventType->name)
                        ->warning()
                        ->send();
                    return;
                }
            }
        }

        $entryDetails = [
            'dojo_type'          => $this->dojo_type,
            'dojo_name'          => $this->dojo_type === 'lfp' ? $this->dojo_name : null,
            'guest_style'        => $this->dojo_type === 'guest' ? $this->guest_style : null,
            'rank_type'          => $this->rank_type,
            'rank_kyu'           => $this->rank_type === 'kyu' ? $this->rank_kyu : null,
            'rank_dan'           => $this->rank_type === 'dan' ? $this->rank_dan : null,
            'experience_years'   => $this->rank_type === 'experience' ? $this->experience_years : null,
            'experience_months'  => $this->rank_type === 'experience' ? $this->experience_months : null,
            'weight_kg'          => $this->weight_kg,
        ];

        $enrolment = app(EnrolmentService::class)->enrol(
            auth()->user(),
            $competition,
            $this->selected_event_ids,
            $this->selected_divisions,
            $entryDetails
        );

        // Handle Yakusuko partner linking
        foreach ($this->yakusuko_partners as $eventId => $partnerId) {
            if (! $partnerId) {
                continue;
            }

            $myEe = $enrolment->enrolmentEvents()
                ->where('competition_event_id', $eventId)
                ->first();

            if (! $myEe) {
                continue;
            }

            $partnerEe = EnrolmentEvent::whereHas('enrolment', fn ($q) => $q
                ->where('competition_id', $competition->id)
                ->where('competitor_id', $partnerId)
            )->where('competition_event_id', $eventId)->first();

            if ($partnerEe) {
                app(EnrolmentService::class)->resolveYakusukoPartner($myEe, $partnerEe);
            }
        }

        Notification::make()->title('Enrolment submitted successfully!')->success()->send();

        $this->redirect(route('filament.portal.pages.my-enrolments'));
    }
}
