<?php

namespace App\Filament\Portal\Pages;

use App\Models\Competition;
use App\Models\Division;
use App\Models\Dojo;
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
    public array   $selected_entries    = [];
    public array   $yakusuko_partners   = [];
    public bool    $details_confirmed   = false;

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
                ->disabled(fn () => $this->details_confirmed)
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
                            $this->selected_entries  = [];
                            $this->yakusuko_partners = [];
                            $this->details_confirmed = false;
                        }),
                ]),

            Section::make('Your details for this competition')
                ->description('Rank, weight, and dojo may change between competitions — please confirm them here.')
                ->visible(fn () => $this->competition_id !== null)
                ->disabled(fn () => $this->details_confirmed)
                ->columns(2)
                ->schema([
                    Radio::make('dojo_type')
                        ->label('Membership type')
                        ->options(['lfp' => 'LFP Dojo member', 'guest' => 'Guest competitor'])
                        ->required()
                        ->inline()
                        ->live()
                        ->afterStateUpdated(function () {
                            $this->details_confirmed = false;
                            $this->selected_entries  = [];
                        })
                        ->columnSpanFull(),

                    Select::make('dojo_name')
                        ->label('LFP Dojo')
                        ->options(fn () => Dojo::active()->orderBy('name')->pluck('name', 'name'))
                        ->searchable()
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
                        ->afterStateUpdated(function () {
                            $this->details_confirmed = false;
                            $this->selected_entries  = [];
                        })
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
                ->visible(fn () => $this->details_confirmed)
                ->schema(fn () => $this->buildEventSchema()),

            Section::make('Fee summary')
                ->visible(fn () => count($this->selected_entries) > 0)
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
            ->where('status', 'scheduled')
            ->orderBy('running_order')
            ->get(); // no pagination — show all eligible events

        $existingEnrolment = $competition->enrolments()
            ->where('competitor_id', auth()->id())
            ->with('activeEvents.division')
            ->first();

        $disabledKeys = [];
        if ($existingEnrolment) {
            foreach ($existingEnrolment->activeEvents as $ee) {
                $disabledKeys[] = $ee->division_id
                    ? "d{$ee->division_id}"
                    : "e{$ee->competition_event_id}";
            }
        }

        $ctx             = $this->buildCtx();
        $divisionService = app(DivisionAssignmentService::class);
        $options         = [];
        $yakusukoEventIds = [];

        foreach ($events as $event) {
            $eligible = $ctx ? $divisionService->getEligibleDivisions($event, $ctx) : collect();

            foreach ($eligible as $division) {
                $label = "{$event->event_code} — {$event->name}: {$division->label}";
                if ($this->isDivisionOpen($division)) {
                    $label .= ' (Open)';
                }
                $options["d{$division->id}"] = $label;
            }

            if ($event->requires_partner) {
                $yakusukoEventIds[] = $event->id;
            }
        }

        if (empty($options)) {
            return [
                Placeholder::make('no_eligible_divisions')
                    ->label('')
                    ->content('No eligible divisions were found matching your rank, age, and weight. Press Back to adjust your entry details.'),
            ];
        }

        $availableKeys = array_diff(array_keys($options), $disabledKeys);
        if (empty($availableKeys)) {
            return [
                Placeholder::make('all_enrolled')
                    ->label('')
                    ->content('You are already enrolled in all eligible divisions for this competition.'),
            ];
        }

        $components = [
            CheckboxList::make('selected_entries')
                ->label('Select divisions to enter')
                ->options($options)
                ->disableOptionWhen(fn (string $value) => in_array($value, $disabledKeys))
                ->live()
                ->helperText($disabledKeys ? 'Greyed-out entries are already in your enrolment.' : null),
        ];

        // Yakusuko partner selects — shown for partner events where any division is selected
        $selectedEventIds = [];
        foreach ($this->selected_entries as $key) {
            $div = Division::find((int) substr($key, 1));
            if ($div) {
                $selectedEventIds[] = $div->competition_event_id;
            }
        }

        foreach (array_intersect($yakusukoEventIds, $selectedEventIds) as $eventId) {
            $components[] = Select::make("yakusuko_partners.{$eventId}")
                ->label("Yakusuko partner for event #{$eventId}")
                ->options(
                    User::whereHas('competitorProfile', fn ($q) => $q->where('profile_complete', true))
                        ->where('id', '!=', auth()->id())
                        ->get()
                        ->mapWithKeys(fn ($u) => [
                            $u->id => ($u->competitorProfile->first_name ?? '') . ' '
                                    . ($u->competitorProfile->surname ?? '')
                                    . " ({$u->email})",
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
        if (! $this->competition_id || empty($this->selected_entries)) {
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

        $newCount = count($this->selected_entries);
        $total    = $existingCount + $newCount;
        $isLate   = $competition->isLateEnrolment();

        $fee   = app(EnrolmentService::class)->calculateFee($competition, $total, $isLate);
        $lines = ["**{$newCount} new event entr" . ($newCount === 1 ? 'y' : 'ies') . "** added to your enrolment."];
        if ($isLate) {
            $lines[] = "Late surcharge of \${$competition->late_surcharge} applies.";
        }
        $lines[] = "**Total fee: \${$fee}**";

        return implode("\n\n", $lines);
    }

    public function isReadyToSubmit(): bool
    {
        return $this->details_confirmed && ! empty($this->selected_entries);
    }

    public function nextHint(): void
    {
        if (! $this->competition_id) {
            Notification::make()->title('Select a competition to continue.')->info()->send();
            return;
        }
        if (! $this->dojo_type) {
            Notification::make()->title('Select a membership type (LFP or Guest) to continue.')->info()->send();
            return;
        }
        if (! $this->rank_type) {
            Notification::make()->title('Select a rank type to continue.')->info()->send();
            return;
        }
        if ($this->rank_type === 'kyu' && ! $this->rank_kyu) {
            Notification::make()->title('Enter your Kyu grade to continue.')->info()->send();
            return;
        }
        if ($this->rank_type === 'dan' && ! $this->rank_dan) {
            Notification::make()->title('Enter your Dan grade to continue.')->info()->send();
            return;
        }

        if (! $this->details_confirmed) {
            $this->details_confirmed = true;
            $this->selected_entries  = [];
            return;
        }

        Notification::make()->title('Select at least one division to enter.')->info()->send();
    }

    public function goBack(): void
    {
        $this->details_confirmed = false;
        $this->selected_entries  = [];
        $this->yakusuko_partners = [];
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

        if (! $this->competition_id || empty($this->selected_entries)) {
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

        // Parse flat division entries into event + division maps
        $divisionsByEvent = [];

        foreach ($this->selected_entries as $key) {
            $divisionId = (int) substr($key, 1);
            $division   = Division::find($divisionId);
            if ($division) {
                $divisionsByEvent[$division->competition_event_id][] = $divisionId;
            }
        }

        $competitionEventIds = array_keys($divisionsByEvent);

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
            $competitionEventIds,
            $divisionsByEvent,
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
