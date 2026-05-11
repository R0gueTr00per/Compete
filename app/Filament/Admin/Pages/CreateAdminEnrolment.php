<?php

namespace App\Filament\Admin\Pages;

use App\Models\Competition;
use App\Models\CompetitorProfile;
use App\Models\Division;
use App\Models\Dojo;
use App\Models\User;
use App\Services\DivisionAssignmentService;
use App\Services\EnrolmentService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateAdminEnrolment extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-plus-circle';
    protected static ?string $navigationGroup = 'Competitions';
    protected static ?int    $navigationSort  = 3;
    protected static ?string $navigationLabel = 'Create Enrolment';
    protected static string  $view            = 'filament.admin.pages.create-admin-enrolment';
    protected static ?string $slug            = 'create-enrolment';

    public ?int    $competition_id     = null;
    public ?int    $competitor_id      = null;
    public bool    $create_new_user    = false;
    public ?string $new_name           = null;
    public ?string $new_email          = null;
    public ?string $new_surname        = null;
    public ?string $new_first_name     = null;
    public ?string $new_dob            = null;
    public ?string $new_gender         = null;
    public ?string $dojo_type          = null;
    public ?string $dojo_name          = null;
    public ?string $guest_style        = null;
    public ?string $rank_type          = null;
    public ?int    $rank_kyu           = null;
    public ?int    $rank_dan           = null;
    public ?int    $experience_years   = null;
    public ?int    $experience_months  = null;
    public ?float  $weight_kg          = null;
    public array   $selected_entries    = [];
    public bool    $details_confirmed   = false;

    public function mount(): void
    {
        $today = now()->toDateString();

        $competition = Competition::whereIn('status', ['open', 'running'])
            ->where('competition_date', $today)
            ->first()
            ?? Competition::whereIn('status', ['open', 'running'])
                ->orderBy('competition_date')
                ->first();

        if ($competition) {
            $this->competition_id = $competition->id;
        }
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Competition & Competitor')
                ->columns(2)
                ->disabled(fn () => $this->details_confirmed)
                ->schema([
                    Select::make('competition_id')
                        ->label('Competition')
                        ->options(
                            Competition::whereIn('status', ['open', 'running', 'closed'])
                                ->orderByDesc('competition_date')
                                ->pluck('name', 'id')
                        )
                        ->required()
                        ->live()
                        ->afterStateUpdated(function () {
                            $this->selected_entries  = [];
                            $this->competitor_id     = null;
                            $this->details_confirmed = false;
                        })
                        ->columnSpanFull(),

                    Toggle::make('create_new_user')
                        ->label('Create a new competitor account')
                        ->live()
                        ->afterStateUpdated(function () {
                            $this->competitor_id     = null;
                            $this->new_name          = $this->new_email = null;
                            $this->new_surname       = $this->new_first_name = null;
                            $this->new_dob           = $this->new_gender = null;
                            $this->details_confirmed = false;
                        })
                        ->columnSpanFull(),

                    Select::make('competitor_id')
                        ->label('Select existing competitor')
                        ->options(
                            User::with('competitorProfile')
                                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['user']))
                                ->orWhereDoesntHave('roles')
                                ->get()
                                ->mapWithKeys(fn ($u) => [
                                    $u->id => ($u->competitorProfile?->surname ?? '')
                                        . ', ' . ($u->competitorProfile?->first_name ?? $u->name)
                                        . ' (' . $u->email . ')',
                                ])
                        )
                        ->required()
                        ->searchable()
                        ->live()
                        ->visible(fn () => ! $this->create_new_user)
                        ->afterStateUpdated(function () {
                            $this->selected_entries  = [];
                            $this->dojo_type         = null;
                            $this->rank_type         = null;
                            $this->details_confirmed = false;
                        })
                        ->columnSpanFull(),
                ]),

            Section::make('New competitor details')
                ->visible(fn () => $this->create_new_user)
                ->disabled(fn () => $this->details_confirmed)
                ->columns(2)
                ->schema([
                    TextInput::make('new_surname')
                        ->label('Surname')
                        ->required()
                        ->maxLength(100),

                    TextInput::make('new_first_name')
                        ->label('First name')
                        ->required()
                        ->maxLength(100),

                    TextInput::make('new_email')
                        ->label('Email address')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique('users', 'email')
                        ->validationMessages(['unique' => 'A competitor already exists with this email.']),

                    Select::make('new_gender')
                        ->label('Gender')
                        ->options(['M' => 'Male', 'F' => 'Female'])
                        ->required(),

                    DatePicker::make('new_dob')
                        ->label('Date of birth')
                        ->required()
                        ->maxDate(now()->subYears(5)),
                ]),

            Section::make('Competition entry details')
                ->description('Rank, weight, and dojo for this competition.')
                ->visible(fn () => $this->competition_id && ($this->competitor_id || ($this->create_new_user && $this->new_email)))
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
                        ->visible(fn () => $this->dojo_type === 'guest'),

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
                        ->visible(fn () => $this->rank_type === 'kyu'),

                    TextInput::make('rank_dan')
                        ->label('Dan grade')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(10)
                        ->suffix('Dan')
                        ->visible(fn () => $this->rank_type === 'dan'),

                    TextInput::make('experience_years')
                        ->label('Years')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(80)
                        ->visible(fn () => $this->rank_type === 'experience'),

                    TextInput::make('experience_months')
                        ->label('Months')
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
        if ($this->create_new_user && $this->new_dob && $this->new_gender) {
            return (object) [
                'gender'            => $this->new_gender,
                'age'               => \Carbon\Carbon::parse($this->new_dob)->age,
                'rank_type'         => $this->rank_type,
                'rank_kyu'          => $this->rank_kyu,
                'rank_dan'          => $this->rank_dan,
                'experience_years'  => $this->experience_years,
                'experience_months' => $this->experience_months,
                'weight_kg'         => $this->weight_kg,
            ];
        }

        $competitor = User::find($this->competitor_id);
        $profile    = $competitor?->competitorProfile;
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
        if (! $this->competition_id || (! $this->competitor_id && ! $this->create_new_user)) {
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

        $existingEnrolment = $this->competitor_id
            ? $competition->enrolments()
                ->where('competitor_id', $this->competitor_id)
                ->with('activeEvents.division')
                ->first()
            : null;

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

        foreach ($events as $event) {
            $eligible = $ctx ? $divisionService->getEligibleDivisions($event, $ctx) : collect();

            foreach ($eligible as $division) {
                $options["d{$division->id}"] = "{$event->event_code} — {$event->name}: {$division->label}";
            }
        }

        if (empty($options)) {
            return [
                Placeholder::make('no_eligible_divisions')
                    ->label('')
                    ->content('No eligible divisions were found for this competitor\'s rank, age, and weight. Press Back to adjust the entry details.'),
            ];
        }

        $availableKeys = array_diff(array_keys($options), $disabledKeys);
        if (empty($availableKeys)) {
            return [
                Placeholder::make('all_enrolled')
                    ->label('')
                    ->content('This competitor is already enrolled in all eligible divisions for this competition.'),
            ];
        }

        return [
            CheckboxList::make('selected_entries')
                ->label('Select divisions to enter')
                ->options($options)
                ->disableOptionWhen(fn (string $value) => in_array($value, $disabledKeys))
                ->live()
                ->helperText($disabledKeys ? 'Greyed-out entries are already enrolled.' : null),
        ];
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

        $existingCount = $this->competitor_id
            ? ($competition->enrolments()
                ->where('competitor_id', $this->competitor_id)
                ->withCount('activeEvents')
                ->first()?->active_events_count ?? 0)
            : 0;

        $newCount = count($this->selected_entries);
        $isLate   = $competition->isLateEnrolment();
        $total    = $existingCount + $newCount;
        $fee      = app(EnrolmentService::class)->calculateFee($competition, $total, $isLate);

        $lines = ["**{$newCount} event entr" . ($newCount === 1 ? 'y' : 'ies') . "** added."];
        if ($isLate) {
            $lines[] = "Late surcharge of \${$competition->late_surcharge} applies.";
        }
        $lines[] = "**Total fee: \${$fee}**";

        return implode("\n\n", $lines);
    }

    public function isReadyToSubmit(): bool
    {
        if (! $this->details_confirmed) return false;
        if (! $this->competition_id || ! $this->dojo_type || ! $this->rank_type) return false;
        if ($this->rank_type === 'kyu' && ! $this->rank_kyu) return false;
        if ($this->rank_type === 'dan' && ! $this->rank_dan) return false;
        if (empty($this->selected_entries)) return false;
        if ($this->create_new_user) {
            return (bool) ($this->new_email && $this->new_surname && $this->new_first_name && $this->new_dob && $this->new_gender);
        }
        return (bool) $this->competitor_id;
    }

    public function nextHint(): void
    {
        if (! $this->competition_id) {
            Notification::make()->title('Select a competition to continue.')->info()->send();
            return;
        }
        if (! $this->competitor_id && ! $this->create_new_user) {
            Notification::make()->title('Select or create a competitor to continue.')->info()->send();
            return;
        }
        if ($this->create_new_user && (! $this->new_email || ! $this->new_surname || ! $this->new_first_name || ! $this->new_dob || ! $this->new_gender)) {
            Notification::make()->title('Complete the new competitor details to continue.')->info()->send();
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
            Notification::make()->title('Enter the Kyu grade to continue.')->info()->send();
            return;
        }
        if ($this->rank_type === 'dan' && ! $this->rank_dan) {
            Notification::make()->title('Enter the Dan grade to continue.')->info()->send();
            return;
        }

        // All entry details complete — reveal the events section
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
    }

    public function submit(): void
    {
        if (! $this->competition_id || empty($this->selected_entries)) {
            Notification::make()->title('Please select a competition and at least one event.')->warning()->send();
            return;
        }

        if (! $this->dojo_type || ! $this->rank_type) {
            Notification::make()->title('Please fill in the competitor\'s entry details (dojo and rank).')->warning()->send();
            return;
        }

        // Create new user if requested
        if ($this->create_new_user) {
            if (! $this->new_email || ! $this->new_surname || ! $this->new_first_name || ! $this->new_dob || ! $this->new_gender) {
                Notification::make()->title('Please fill in all new competitor details.')->warning()->send();
                return;
            }

            if (User::where('email', $this->new_email)->exists()) {
                Notification::make()->title('A user with that email already exists. Select them from the existing competitor list.')->warning()->send();
                return;
            }

            $newUser = User::create([
                'name'              => $this->new_first_name . ' ' . $this->new_surname,
                'email'             => $this->new_email,
                'password'          => Hash::make(Str::random(32)),
                'email_verified_at' => now(),
                'status'            => 'active',
            ]);
            $newUser->assignRole('user');

            CompetitorProfile::create([
                'user_id'          => $newUser->id,
                'surname'          => $this->new_surname,
                'first_name'       => $this->new_first_name,
                'date_of_birth'    => $this->new_dob,
                'gender'           => $this->new_gender,
                'profile_complete' => true,
            ]);

            $this->competitor_id = $newUser->id;
        }

        if (! $this->competitor_id) {
            Notification::make()->title('Please select or create a competitor.')->warning()->send();
            return;
        }

        $competitor  = User::find($this->competitor_id);
        $competition = Competition::find($this->competition_id);

        if (! $competitor || ! $competition) {
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

        if (empty($competitionEventIds)) {
            Notification::make()->title('Please select at least one event.')->warning()->send();
            return;
        }

        $entryDetails = [
            'dojo_type'         => $this->dojo_type,
            'dojo_name'         => $this->dojo_type === 'lfp' ? $this->dojo_name : null,
            'guest_style'       => $this->dojo_type === 'guest' ? $this->guest_style : null,
            'rank_type'         => $this->rank_type,
            'rank_kyu'          => $this->rank_type === 'kyu' ? $this->rank_kyu : null,
            'rank_dan'          => $this->rank_type === 'dan' ? $this->rank_dan : null,
            'experience_years'  => $this->rank_type === 'experience' ? $this->experience_years : null,
            'experience_months' => $this->rank_type === 'experience' ? $this->experience_months : null,
            'weight_kg'         => $this->weight_kg,
        ];

        app(EnrolmentService::class)->enrol(
            $competitor,
            $competition,
            $competitionEventIds,
            $divisionsByEvent,
            $entryDetails
        );

        Notification::make()->title('Enrolment created successfully.')->success()->send();

        $this->reset(['selected_entries', 'details_confirmed', 'competitor_id',
            'create_new_user', 'new_name', 'new_email', 'new_surname', 'new_first_name', 'new_dob', 'new_gender',
            'dojo_type', 'dojo_name', 'guest_style', 'rank_type', 'rank_kyu', 'rank_dan',
            'experience_years', 'experience_months', 'weight_kg']);
    }
}
