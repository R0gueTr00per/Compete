<?php

namespace App\Filament\Portal\Pages;

use App\Models\Competition;
use App\Models\CompetitorProfile;
use App\Models\Division;
use App\Models\EnrolmentEvent;
use App\Models\Rank;
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
use Livewire\Attributes\Url;

class EnrolPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $title           = 'Enrol';
    protected static ?string $navigationIcon  = 'heroicon-o-pencil-square';
    protected static ?string $navigationLabel = 'Enrol';
    protected static string  $view            = 'filament.portal.pages.enrol-page';
    protected static ?string $slug            = 'enrol';

    #[Url]
    public ?int    $profile_id          = null;
    #[Url]
    public ?int    $competition_id      = null;
    public ?string $dojo_type           = null;
    public ?string $dojo_name           = null;
    public ?string $guest_style         = null;
    public ?int    $rank_id             = null;
    public ?float  $weight_kg           = null;
    public array   $selected_entries    = [];
    public array   $yakusuko_partners   = [];
    public bool    $details_confirmed   = false;

    public function mount(): void
    {
        $activeProfiles = auth()->user()->ownedProfiles()
            ->where('is_active', true)
            ->where('profile_complete', true)
            ->get();

        if (! $this->profile_id && $activeProfiles->count() === 1) {
            $this->profile_id = $activeProfiles->first()->id;
        }

        if (! $this->competition_id) {
            $open = Competition::where('status', 'open')->where('organisation_id', app('tenant')?->id)->orderBy('competition_date')->first();
            if ($open) {
                $this->competition_id = $open->id;
            }
        }
    }

    public function getSelectedProfile(): ?CompetitorProfile
    {
        if (! $this->profile_id) {
            return null;
        }
        return auth()->user()->ownedProfiles()
            ->where('id', $this->profile_id)
            ->where('is_active', true)
            ->first();
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
        $activeProfiles = auth()->user()->ownedProfiles()
            ->where('is_active', true)
            ->where('profile_complete', true)
            ->get();

        return $form->schema([
            // Profile selector — only shown when user has multiple eligible profiles
            Section::make('Who are you enrolling?')
                ->visible($activeProfiles->count() > 1)
                ->disabled(fn () => $this->details_confirmed)
                ->schema([
                    Select::make('profile_id')
                        ->label('Select profile')
                        ->options(
                            $activeProfiles->mapWithKeys(fn ($p) => [
                                $p->id => $p->full_name . ($p->isChild() ? ' (child)' : ''),
                            ])
                        )
                        ->required()
                        ->live()
                        ->afterStateUpdated(function () {
                            $this->selected_entries  = [];
                            $this->yakusuko_partners = [];
                            $this->details_confirmed = false;
                        }),
                ]),

            Section::make('Competition')
                ->disabled(fn () => $this->details_confirmed)
                ->schema([
                    Select::make('competition_id')
                        ->label('Select competition')
                        ->options(function () {
                            if (! $this->profile_id) {
                                return [];
                            }
                            $profile = $this->getSelectedProfile();
                            $enrolledIds = $profile
                                ? $profile->enrolments()->pluck('competition_id')
                                : collect();

                            return Competition::where('status', 'open')
                                ->whereNotIn('id', $enrolledIds)
                                ->where('organisation_id', app('tenant')?->id)
                                ->orderBy('competition_date')
                                ->pluck('name', 'id');
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
                ->visible(fn () => $this->competition_id !== null && $this->profile_id !== null)
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
                        ->options(fn () => \App\Models\Dojo::active()->where('organisation_id', app('tenant')?->id)->orderBy('name')->pluck('name', 'name'))
                        ->searchable()
                        ->visible(fn () => $this->dojo_type === 'lfp')
                        ->requiredIf('dojo_type', 'lfp'),

                    TextInput::make('guest_style')
                        ->label('Martial arts style')
                        ->maxLength(100)
                        ->visible(fn () => $this->dojo_type === 'guest')
                        ->requiredIf('dojo_type', 'guest'),

                    Select::make('rank_id')
                        ->label('Rank / Level')
                        ->options(fn () => Rank::where('organisation_id', Competition::find($this->competition_id)?->organisation_id)
                            ->orderBy('sort_order')
                            ->pluck('name', 'id'))
                        ->required()
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function () {
                            $this->details_confirmed = false;
                            $this->selected_entries  = [];
                        }),

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
        $profile = $this->getSelectedProfile();
        if (! $profile) {
            return null;
        }

        return (object) [
            'gender'    => $profile->gender,
            'age'       => $profile->age,
            'rank_id'   => $this->rank_id,
            'weight_kg' => $this->weight_kg,
        ];
    }

    private function buildEventSchema(): array
    {
        if (! $this->competition_id || ! $this->profile_id) {
            return [];
        }

        $competition = Competition::find($this->competition_id);
        if (! $competition) {
            return [];
        }

        $events = $competition->competitionEvents()
            ->where('status', 'scheduled')
            ->orderBy('running_order')
            ->get();

        $ctx             = $this->buildCtx();
        $divisionService = app(DivisionAssignmentService::class);
        $options         = [];
        $partnerEventMap = [];

        foreach ($events as $event) {
            $eligible = $ctx ? $divisionService->getEligibleDivisions($event, $ctx) : collect();

            foreach ($eligible as $division) {
                $label = "{$division->code} — {$event->name}: {$division->label}";
                if ($this->isDivisionOpen($division)) {
                    $label .= ' (Open)';
                }
                $options["d{$division->id}"] = $label;
            }

            if ($event->requires_partner) {
                $partnerEventMap[$event->id] = $event->name;
            }
        }

        if (empty($options)) {
            return [
                Placeholder::make('no_eligible_divisions')
                    ->label('')
                    ->content('No eligible divisions were found matching your rank, age, and weight. Press Back to adjust your entry details.'),
            ];
        }

        $components = [
            CheckboxList::make('selected_entries')
                ->label('Select divisions to enter')
                ->options($options)
                ->live(),
        ];

        // Partner selects for events that require a partner
        $selectedEventIds = [];
        foreach ($this->selected_entries as $key) {
            $div = Division::find((int) substr($key, 1));
            if ($div) {
                $selectedEventIds[] = $div->competition_event_id;
            }
        }

        foreach (array_intersect(array_keys($partnerEventMap), $selectedEventIds) as $eventId) {
            $eventName = $partnerEventMap[$eventId];
            $components[] = Select::make("yakusuko_partners.{$eventId}")
                ->label("Partner for {$eventName}")
                ->options(
                    CompetitorProfile::where('profile_complete', true)
                        ->where('is_active', true)
                        ->where('organisation_id', app('tenant')?->id)
                        ->where('id', '!=', $this->profile_id)
                        ->get()
                        ->mapWithKeys(fn ($p) => [
                            $p->id => $p->full_name,
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
        if (! $this->competition_id || empty($this->selected_entries) || ! $this->profile_id) {
            return '';
        }

        $competition = Competition::find($this->competition_id);
        if (! $competition) {
            return '';
        }

        $count  = count($this->selected_entries);
        $isLate = $competition->isLateEnrolment();

        $fee   = app(EnrolmentService::class)->calculateFee($competition, $count, $isLate);
        $lines = ["**{$count} event entr" . ($count === 1 ? 'y' : 'ies') . "** selected."];
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
        if (! $this->profile_id) {
            Notification::make()->title('Select a profile to continue.')->info()->send();
            return;
        }
        if (! $this->competition_id) {
            Notification::make()->title('Select a competition to continue.')->info()->send();
            return;
        }
        if (! $this->dojo_type) {
            Notification::make()->title('Select a membership type (LFP or Guest) to continue.')->info()->send();
            return;
        }
        if (! $this->rank_id) {
            Notification::make()->title('Select a rank to continue.')->info()->send();
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

    public function cancel(): void
    {
        $this->redirect(\App\Filament\Portal\Pages\Dashboard::getUrl(), navigate: true);
    }

    public function submit(): void
    {
        $profile = $this->getSelectedProfile();

        if (! $profile || ! $profile->profile_complete) {
            Notification::make()
                ->title('Please complete your profile before enrolling.')
                ->warning()
                ->send();
            $this->redirect(route('filament.portal.pages.profiles'));
            return;
        }

        if (! $this->competition_id || empty($this->selected_entries)) {
            Notification::make()->title('Please select at least one event.')->warning()->send();
            return;
        }

        if (! $this->dojo_type || ! $this->rank_id) {
            Notification::make()->title('Please fill in your competition details (dojo and rank).')->warning()->send();
            return;
        }

        $competition = Competition::find($this->competition_id);
        if (! $competition || ! $competition->isEnrolmentOpen()) {
            Notification::make()->title('Enrolment is not currently open for this competition.')->danger()->send();
            return;
        }

        if ($competition->enrolments()->where('competitor_profile_id', $profile->id)->exists()) {
            Notification::make()->title('This profile is already enrolled in this competition.')->danger()->send();
            return;
        }

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
            'dojo_type'   => $this->dojo_type,
            'dojo_name'   => $this->dojo_type === 'lfp' ? $this->dojo_name : null,
            'guest_style' => $this->dojo_type === 'guest' ? $this->guest_style : null,
            'rank_id'     => $this->rank_id,
            'weight_kg'   => $this->weight_kg,
        ];

        $enrolment = app(EnrolmentService::class)->enrol(
            $profile,
            $competition,
            $competitionEventIds,
            $divisionsByEvent,
            $entryDetails
        );

        // Handle Yakusuko partner linking
        foreach ($this->yakusuko_partners as $eventId => $partnerProfileId) {
            if (! $partnerProfileId) {
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
                ->where('competitor_profile_id', $partnerProfileId)
            )->where('competition_event_id', $eventId)->first();

            if ($partnerEe) {
                app(EnrolmentService::class)->resolveYakusukoPartner($myEe, $partnerEe);
            }
        }

        Notification::make()->title('Enrolment submitted successfully!')->success()->send();

        $this->redirect(route('filament.portal.pages.my-enrolments'));
    }
}
