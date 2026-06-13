<?php

namespace App\Filament\OrgAdmin\Pages;

use App\Models\Competition;
use App\Models\CompetitorProfile;
use App\Models\Division;
use App\Models\Dojo;
use App\Models\OrganisationMembership;
use App\Models\User;
use App\Notifications\AdminCreatedAccountNotification;
use App\Notifications\AdminCreatedParentAccountNotification;
use App\Models\Rank;
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
use App\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CreateAdminEnrolment extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-plus-circle';
    protected static ?string $navigationGroup = 'Competitions';
    protected static ?int    $navigationSort  = 3;
    protected static ?string $navigationLabel = 'Create Registration';
    protected static string  $view            = 'filament.admin.pages.create-admin-enrolment';
    protected static ?string $slug            = 'create-enrolment';

    public static function canAccess(): bool
    {
        $tenant = app('tenant');
        if (! $tenant) return true;
        $user = auth()->user();
        if ($user->isOrgAdmin($tenant)) return true;
        return $user->getActiveOfficialRoleFor($tenant)?->can_access_create_enrolment ?? false;
    }

    public ?int    $competition_id       = null;
    public ?int    $competitor_profile_id = null;
    public bool    $create_new_user      = false;
    public bool    $is_child_profile     = false;
    public ?string $new_surname          = null;
    public ?string $new_first_name       = null;
    public ?string $new_email            = null;
    public ?string $new_parent_email     = null;
    public ?string $new_dob              = null;
    public ?string $new_gender           = null;
    public ?string $dojo_type            = null;
    public ?string $dojo_name            = null;
    public ?int    $rank_id              = null;
    public ?float  $weight_kg            = null;
    public array   $selected_entries     = [];
    public bool    $details_confirmed    = false;

    public function mount(): void
    {
        $today = now()->toDateString();

        $orgId = app('tenant')?->id;

        $competition = Competition::whereIn('status', ['open', 'running'])
            ->where('organisation_id', $orgId)
            ->where('competition_date', $today)
            ->first()
            ?? Competition::whereIn('status', ['open', 'running'])
                ->where('organisation_id', $orgId)
                ->orderBy('competition_date')
                ->first();

        if ($competition) {
            $this->competition_id = $competition->id;
        }

        $this->dojo_type = 'lfp';
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
                            Competition::whereIn('status', ['open', 'running', 'enrolments_closed'])
                                ->where('organisation_id', app('tenant')?->id)
                                ->where('is_template', false)
                                ->orderByDesc('competition_date')
                                ->pluck('name', 'id')
                        )
                        ->required()
                        ->live()
                        ->afterStateUpdated(function () {
                            $this->selected_entries       = [];
                            $this->competitor_profile_id  = null;
                            $this->details_confirmed      = false;
                        })
                        ->columnSpanFull(),

                    Toggle::make('create_new_user')
                        ->label('Create a new competitor')
                        ->live()
                        ->afterStateUpdated(function () {
                            $this->competitor_profile_id = null;
                            $this->is_child_profile      = false;
                            $this->new_email             = null;
                            $this->new_parent_email      = null;
                            $this->new_surname           = $this->new_first_name = null;
                            $this->new_dob               = $this->new_gender = null;
                            $this->details_confirmed     = false;
                        })
                        ->columnSpanFull(),

                    Select::make('competitor_profile_id')
                        ->label('Select existing competitor')
                        ->options(
                            CompetitorProfile::with('owner')
                                ->where('is_active', true)
                                ->where('organisation_id', app('tenant')?->id)
                                ->get()
                                ->sortBy('surname')
                                ->mapWithKeys(fn ($p) => [
                                    $p->id => $p->surname . ', ' . $p->first_name
                                        . ' (age ' . ($p->age ?? '?') . ')',
                                ])
                        )
                        ->required()
                        ->searchable()
                        ->live()
                        ->visible(fn () => ! $this->create_new_user)
                        ->afterStateUpdated(function () {
                            $this->selected_entries  = [];
                            $this->dojo_type         = 'lfp';
                            $this->rank_id           = null;
                            $this->details_confirmed = false;
                        })
                        ->columnSpanFull(),
                ]),

            Section::make('New competitor details')
                ->visible(fn () => $this->create_new_user)
                ->disabled(fn () => $this->details_confirmed)
                ->columns(2)
                ->schema([
                    Toggle::make('is_child_profile')
                        ->label('This is a family member profile (requires primary account login)')
                        ->live()
                        ->afterStateUpdated(function () {
                            $this->new_email        = null;
                            $this->new_parent_email = null;
                            $this->details_confirmed = false;
                        })
                        ->columnSpanFull(),

                    TextInput::make('new_first_name')
                        ->label('First name')
                        ->required()
                        ->maxLength(100),

                    TextInput::make('new_surname')
                        ->label('Surname')
                        ->required()
                        ->maxLength(100),

                    TextInput::make('new_email')
                        ->label('Email address')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->rules([Rule::unique('users', 'email')->where('organisation_id', app('tenant')?->id)])
                        ->validationMessages(['unique' => 'A competitor already exists with this email.'])
                        ->visible(fn () => ! $this->is_child_profile),

                    TextInput::make('new_parent_email')
                        ->label('Parent / Guardian email')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->live(debounce: 500)
                        ->hint(function () {
                            if (! $this->new_parent_email || ! filter_var($this->new_parent_email, FILTER_VALIDATE_EMAIL)) {
                                return null;
                            }
                            $user = User::where('email', $this->new_parent_email)->where('organisation_id', app('tenant')?->id)->first();
                            if ($user) {
                                $name = $user->selfProfile?->full_name ?? $user->email;
                                return "Existing account found — {$name}";
                            }
                            return 'No account found — a new account will be created';
                        })
                        ->hintColor(function () {
                            if (! $this->new_parent_email || ! filter_var($this->new_parent_email, FILTER_VALIDATE_EMAIL)) {
                                return null;
                            }
                            return User::where('email', $this->new_parent_email)->where('organisation_id', app('tenant')?->id)->exists() ? 'success' : 'warning';
                        })
                        ->visible(fn () => $this->is_child_profile),

                    Radio::make('new_gender')
                        ->label('Gender')
                        ->options(['M' => 'Male', 'F' => 'Female'])
                        ->required()
                        ->inline(),

                    DatePicker::make('new_dob')
                        ->label('Date of birth')
                        ->required()
                        ->maxDate(now()->subYears(4)),
                ]),

            Section::make('Competition entry details')
                ->description('Rank, weight, and ' . strtolower(tenant_group_name()) . ' for this competition.')
                ->visible(fn () => $this->competition_id && ($this->competitor_profile_id || ($this->create_new_user && $this->newCompetitorReady())))
                ->disabled(fn () => $this->details_confirmed)
                ->columns(2)
                ->schema([
                    Select::make('dojo_name')
                        ->label(tenant_group_name())
                        ->options(fn () => Dojo::active()->where('organisation_id', app('tenant')?->id)->orderBy('name')->pluck('name', 'name'))
                        ->searchable()
                        ->required(),

                    Select::make('rank_id')
                        ->label('Rank / Level')
                        ->options(fn () => Rank::where('organisation_id', app('tenant')?->id)->orderBy('sort_order')->pluck('name', 'id'))
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
        if ($this->create_new_user && $this->new_dob && $this->new_gender) {
            return (object) [
                'gender'    => $this->new_gender,
                'age'       => \Carbon\Carbon::parse($this->new_dob)->age,
                'rank_id'   => $this->rank_id,
                'weight_kg' => $this->weight_kg,
            ];
        }

        $profile = CompetitorProfile::find($this->competitor_profile_id);
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
        if (! $this->competition_id || (! $this->competitor_profile_id && ! $this->create_new_user)) {
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

        $existingEnrolment = $this->competitor_profile_id
            ? $competition->enrolments()
                ->where('competitor_profile_id', $this->competitor_profile_id)
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
                $options["d{$division->id}"] = "{$division->code} — {$event->name}: {$division->label}";
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
                ->searchable(false)
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

        $existingCount = $this->competitor_profile_id
            ? ($competition->enrolments()
                ->where('competitor_profile_id', $this->competitor_profile_id)
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

    private function newCompetitorReady(): bool
    {
        if (! $this->new_surname || ! $this->new_first_name || ! $this->new_dob || ! $this->new_gender) {
            return false;
        }
        if ($this->is_child_profile) {
            return (bool) $this->new_parent_email;
        }
        return (bool) ($this->new_email && ! $this->getErrorBag()->has('new_email'));
    }

    public function isReadyToSubmit(): bool
    {
        if (! $this->details_confirmed) return false;
        if (! $this->competition_id || ! $this->dojo_type || ! $this->rank_id) return false;
        if (empty($this->selected_entries)) return false;
        if ($this->create_new_user) {
            return $this->newCompetitorReady();
        }
        return (bool) $this->competitor_profile_id;
    }

    public function nextHint(): void
    {
        if (! $this->competition_id) {
            Notification::make()->title('Select a competition to continue.')->info()->send();
            return;
        }
        if (! $this->competitor_profile_id && ! $this->create_new_user) {
            Notification::make()->title('Select or create a competitor to continue.')->info()->send();
            return;
        }
        if ($this->create_new_user && (! $this->new_surname || ! $this->new_first_name || ! $this->new_dob || ! $this->new_gender)) {
            Notification::make()->title('Complete the new competitor details to continue.')->info()->send();
            return;
        }
        if ($this->create_new_user && $this->is_child_profile && ! $this->new_parent_email) {
            Notification::make()->title('Enter the parent / guardian email to continue.')->info()->send();
            return;
        }
        if ($this->create_new_user && ! $this->is_child_profile && ! $this->new_email) {
            Notification::make()->title('Complete the new competitor details to continue.')->info()->send();
            return;
        }
        if ($this->create_new_user && ! $this->is_child_profile && $this->new_email && User::where('email', $this->new_email)->where('organisation_id', app('tenant')?->id)->exists()) {
            $this->addError('new_email', 'A user with that email already exists. Select them from the existing competitor list.');
            return;
        }
        if (! $this->rank_id) {
            Notification::make()->title('Select a rank / level to continue.')->info()->send();
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
    }

    public function submit(): void
    {
        if (! $this->competition_id || empty($this->selected_entries)) {
            Notification::make()->title('Please select a competition and at least one event.')->warning()->send();
            return;
        }

        if (! $this->dojo_type || ! $this->rank_id) {
            Notification::make()->title('Please fill in the competitor\'s entry details (' . strtolower(tenant_group_name()) . ' and rank).')->warning()->send();
            return;
        }

        $newAdultUser  = null;
        $newParentUser = null;

        if ($this->create_new_user) {
            if (! $this->new_surname || ! $this->new_first_name || ! $this->new_dob || ! $this->new_gender) {
                Notification::make()->title('Please fill in all new competitor details.')->warning()->send();
                return;
            }

            if ($this->is_child_profile) {
                if (! $this->new_parent_email) {
                    Notification::make()->title('Please enter the parent / guardian email.')->warning()->send();
                    return;
                }

                $org = app('tenant');
                $parentUser = User::where('email', $this->new_parent_email)->where('organisation_id', $org?->id)->first();

                if (! $parentUser) {
                    $parentUser = User::create([
                        'email'           => $this->new_parent_email,
                        'organisation_id' => $org?->id,
                        'password'        => Hash::make(Str::random(32)),
                        'status'          => 'active',
                    ]);
                    $parentUser->forceFill(['email_verified_at' => now()])->save();
                    $parentUser->assignRole('user');
                    $newParentUser = $parentUser;
                }

                if ($org && ! OrganisationMembership::where('user_id', $parentUser->id)->where('organisation_id', $org->id)->exists()) {
                    OrganisationMembership::create([
                        'organisation_id'    => $org->id,
                        'user_id'            => $parentUser->id,
                        'role'               => 'competitor',
                        'status'             => 'active',
                        'invited_by_user_id' => auth()->id(),
                        'joined_at'          => now(),
                    ]);
                }

                $newProfile = CompetitorProfile::create([
                    'owner_user_id'    => $parentUser->id,
                    'user_id'          => null,
                    'organisation_id'  => $org?->id,
                    'profile_type'     => 'family_member',
                    'surname'          => $this->new_surname,
                    'first_name'       => $this->new_first_name,
                    'date_of_birth'    => $this->new_dob,
                    'gender'           => $this->new_gender,
                    'is_active'        => true,
                    'profile_complete' => true,
                ]);
            } else {
                if (! $this->new_email) {
                    Notification::make()->title('Please fill in all new competitor details.')->warning()->send();
                    return;
                }

                $org = app('tenant');

                if (User::where('email', $this->new_email)->where('organisation_id', $org?->id)->exists()) {
                    Notification::make()->title('A user with that email already exists. Select them from the existing competitor list.')->warning()->send();
                    return;
                }

                $newAdultUser = User::create([
                    'email'           => $this->new_email,
                    'organisation_id' => $org?->id,
                    'password'        => Hash::make(Str::random(32)),
                    'status'          => 'active',
                ]);
                $newAdultUser->forceFill(['email_verified_at' => now()])->save();
                $newAdultUser->assignRole('user');
                if ($org) {
                    OrganisationMembership::create([
                        'organisation_id'    => $org->id,
                        'user_id'            => $newAdultUser->id,
                        'role'               => 'competitor',
                        'status'             => 'active',
                        'invited_by_user_id' => auth()->id(),
                        'joined_at'          => now(),
                    ]);
                }

                $newProfile = CompetitorProfile::create([
                    'owner_user_id'    => $newAdultUser->id,
                    'user_id'          => $newAdultUser->id,
                    'organisation_id'  => $org?->id,
                    'profile_type'     => 'self',
                    'surname'          => $this->new_surname,
                    'first_name'       => $this->new_first_name,
                    'date_of_birth'    => $this->new_dob,
                    'gender'           => $this->new_gender,
                    'is_active'        => true,
                    'profile_complete' => true,
                ]);
            }

            $this->competitor_profile_id = $newProfile->id;
        }

        if (! $this->competitor_profile_id) {
            Notification::make()->title('Please select or create a competitor.')->warning()->send();
            return;
        }

        $profile     = CompetitorProfile::find($this->competitor_profile_id);
        $competition = Competition::find($this->competition_id);

        if (! $profile || ! $competition) {
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

        if (empty($competitionEventIds)) {
            Notification::make()->title('Please select at least one event.')->warning()->send();
            return;
        }

        $entryDetails = [
            'dojo_type'  => $this->dojo_type,
            'dojo_name'  => $this->dojo_name,
            'rank_id'    => $this->rank_id,
            'weight_kg'  => $this->weight_kg,
        ];

        $suppressServiceNotification = $newAdultUser !== null || $newParentUser !== null;

        $enrolment = app(EnrolmentService::class)->enrol(
            $profile,
            $competition,
            $competitionEventIds,
            $divisionsByEvent,
            $entryDetails,
            notify: ! $suppressServiceNotification,
        );

        if ($newAdultUser !== null) {
            $resetToken = Password::broker()->createToken($newAdultUser);
            $newAdultUser->notify(new AdminCreatedAccountNotification($enrolment, $resetToken));
        } elseif ($newParentUser !== null) {
            $resetToken = Password::broker()->createToken($newParentUser);
            $newParentUser->notify(new AdminCreatedParentAccountNotification($enrolment, $resetToken));
        }

        Notification::make()->title('Enrolment created successfully.')->success()->send();

        $this->reset(['selected_entries', 'details_confirmed', 'competitor_profile_id',
            'create_new_user', 'is_child_profile', 'new_email', 'new_parent_email',
            'new_surname', 'new_first_name', 'new_dob', 'new_gender',
            'dojo_name', 'rank_id', 'weight_kg']);
        $this->dojo_type = 'lfp';
    }
}
