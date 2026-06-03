<?php

namespace App\Filament\Portal\Pages;

use App\Models\Competition;
use App\Models\CompetitorProfile;
use App\Models\Division;
use App\Models\Enrolment;
use App\Models\EnrolmentCart;
use App\Models\Rank;
use App\Services\DivisionAssignmentService;
use App\Services\EnrolmentService;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use App\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class EnrolPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $title                    = 'Register';
    protected static string  $view                     = 'filament.portal.pages.enrol-page';
    protected static ?string $slug                     = 'enrol';
    protected static bool    $shouldRegisterNavigation = false;

    // URL-bound — dashboard passes these so the form is pre-filled
    #[Url]
    public ?int $competition_id = null;
    #[Url]
    public ?int $profile_id     = null;
    #[Url(as: 'redirect_to')]
    public ?string $redirectTo  = null;

    // Cart ID (the user's single global draft cart)
    public ?int $cartId = null;

    // Entry form state
    public ?string $dojo_type         = null;
    public ?string $dojo_name         = null;
    public ?string $guest_style       = null;
    public ?int    $rank_id           = null;
    public ?float  $weight_kg         = null;
    public array   $custom_fields     = [];
    public bool    $details_confirmed = false;
    public array   $selected_entries  = [];
    public array   $yakusuko_partners = [];

    // ── Mount ───────────────────────────────────────────────────────────────

    public function mount(): void
    {
        // Find the user's single global draft cart
        $draft = EnrolmentCart::where('user_id', auth()->id())
            ->where('status', 'draft')
            ->latest()
            ->first();

        if ($draft) {
            $this->cartId = $draft->id;
        }

        // Pre-fill from the last enrolment for this profile (or any owned profile)
        $lastQuery = Enrolment::whereNotIn('status', ['draft'])
            ->whereHas('competitor', function ($q) {
                $q->where('owner_user_id', auth()->id())
                  ->orWhere('user_id', auth()->id());
            });

        if ($this->profile_id) {
            $lastQuery->where('competitor_profile_id', $this->profile_id);
        }

        $last = $lastQuery->latest('enrolled_at')->first();

        $this->dojo_type   = $last?->dojo_type   ?? 'lfp';
        $this->dojo_name   = $last?->dojo_name;
        $this->guest_style = $last?->guest_style;
        $this->rank_id     = $last?->rank_id;
        $this->weight_kg   = $last?->weight_kg ? (float) $last->weight_kg : null;

        // competition_id from URL takes priority; otherwise auto-select
        if ($this->competition_id === null) {
            $open = Competition::where('status', 'open')
                ->where('organisation_id', app('tenant')?->id)
                ->orderBy('competition_date')
                ->first();
            if ($open) {
                $this->competition_id = $open->id;
            }
        }
    }

    // ── Page header — cart shortcut ──────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        $count = $this->getCartCount();
        if ($count === 0) {
            return [];
        }
        return [
            Action::make('viewCart')
                ->label("Cart ({$count})")
                ->icon('heroicon-o-shopping-cart')
                ->color('primary')
                ->badge($count)
                ->url(CartPage::getUrl()),
        ];
    }

    // ── Blade helpers ────────────────────────────────────────────────────────

    public function getOrgName(): string
    {
        return app('tenant')?->name ?? 'Dojo';
    }

    public function getAvailableProfiles(): array
    {
        $alreadyEnrolledIds = $this->competition_id
            ? Enrolment::where('competition_id', $this->competition_id)
                ->whereIn('status', ['pending', 'confirmed', 'checked_in'])
                ->pluck('competitor_profile_id')
                ->toArray()
            : [];

        $inCartIds = $this->cartId
            ? Enrolment::where('cart_id', $this->cartId)
                ->where('status', 'draft')
                ->where('competition_id', $this->competition_id)
                ->pluck('competitor_profile_id')
                ->toArray()
            : [];

        $excludeIds = array_unique(array_merge($alreadyEnrolledIds, $inCartIds));

        return auth()->user()->ownedProfiles()
            ->where('is_active', true)
            ->where('profile_complete', true)
            ->whereNotIn('id', $excludeIds)
            ->get()
            ->map(fn ($p) => [
                'id'     => $p->id,
                'name'   => $p->full_name,
                'family' => $p->isFamilyMember(),
            ])
            ->toArray();
    }

    public function getSelectedCompetitionName(): ?string
    {
        return $this->competition_id
            ? Competition::find($this->competition_id)?->name
            : null;
    }

    public function getSelectedProfileName(): ?string
    {
        return $this->profile_id
            ? CompetitorProfile::find($this->profile_id)?->full_name
            : null;
    }

    public function getCartCount(): int
    {
        if (! $this->cartId) {
            return 0;
        }
        return Enrolment::where('cart_id', $this->cartId)->where('status', 'draft')->count();
    }

    // ── Entry form navigation ────────────────────────────────────────────────

    public function confirmDetails(): void
    {
        if (! $this->dojo_type) {
            Notification::make()->title('Select a membership type to continue.')->info()->send();
            return;
        }
        if (! $this->rank_id) {
            Notification::make()->title('Select a rank to continue.')->info()->send();
            return;
        }
        if (! $this->weight_kg) {
            Notification::make()->title('Enter your weight to continue.')->info()->send();
            return;
        }

        $competition = $this->getSelectedCompetition();

        foreach ($competition?->registration_fields ?? [] as $field) {
            if (! empty($field['required'])) {
                $value = $this->custom_fields[$field['id']] ?? null;
                if ($field['type'] === 'checkbox') {
                    if (! $value) {
                        Notification::make()->title('Please confirm "' . $field['label'] . '" to continue.')->warning()->send();
                        return;
                    }
                } elseif ($value === null || $value === '') {
                    Notification::make()->title('Please fill in "' . $field['label'] . '" to continue.')->warning()->send();
                    return;
                }
            }
        }

        $this->details_confirmed = true;
        $this->selected_entries  = [];
    }

    public function backToDetails(): void
    {
        $this->details_confirmed = false;
        $this->selected_entries  = [];
    }

    public function changeProfile(): void
    {
        $this->profile_id = null;
        $this->clearEntryState();
    }

    // ── Add to cart ──────────────────────────────────────────────────────────

    public function addToCart(): void
    {
        if (! $this->profile_id) {
            Notification::make()->title('Select a competitor first.')->warning()->send();
            return;
        }
        if (empty($this->selected_entries)) {
            Notification::make()->title('Select at least one event.')->warning()->send();
            return;
        }

        $competition = Competition::find($this->competition_id);
        if (! $competition || ! $competition->isEnrolmentOpen()) {
            Notification::make()->title('This competition is not currently open for registration.')->warning()->send();
            return;
        }

        if (! $this->cartId) {
            $cart         = app(EnrolmentService::class)->createOrResumeCart(auth()->user());
            $this->cartId = $cart->id;
        } else {
            $cart = EnrolmentCart::find($this->cartId);
            if (! $cart) {
                Notification::make()->title('Session expired. Please start again.')->danger()->send();
                $this->cartId = null;
                return;
            }
        }

        $profile = CompetitorProfile::find($this->profile_id);

        app(EnrolmentService::class)->saveDraftEntry(
            $cart,
            $competition,
            $profile,
            [
                'dojo_type'              => $this->dojo_type,
                'dojo_name'              => $this->dojo_type === 'lfp' ? $this->dojo_name : null,
                'guest_style'            => $this->dojo_type === 'guest' ? $this->guest_style : null,
                'rank_id'                => $this->rank_id,
                'weight_kg'              => $this->weight_kg,
                'custom_field_responses' => ! empty($this->custom_fields) ? $this->custom_fields : null,
            ],
            $this->selected_entries,
            $this->yakusuko_partners,
        );

        Notification::make()->title($profile->first_name . ' added to cart.')->success()->send();

        $this->profile_id = null;
        $this->clearEntryState();

        $this->redirect($this->resolveReturnUrl());
    }

    public function cancel(): void
    {
        $this->redirect($this->resolveReturnUrl());
    }

    private function resolveReturnUrl(): string
    {
        return match ($this->redirectTo) {
            'dashboard' => route('filament.portal.pages.dashboard'),
            default     => route('filament.portal.pages.dashboard'),
        };
    }

    // ── Form ────────────────────────────────────────────────────────────────

    public function form(Form $form): Form
    {
        return $form->schema([

            Section::make()
                ->visible(fn () => ! $this->details_confirmed)
                ->columns(2)
                ->schema([
                    Radio::make('dojo_type')
                        ->label('Membership type')
                        ->options(fn () => [
                            'lfp'   => app('tenant')?->name . ' member',
                            'guest' => 'Guest competitor',
                        ])
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn () => $this->selected_entries = [])
                        ->extraFieldWrapperAttributes(['style' => 'gap:4px']),

                    Select::make('dojo_name')
                        ->label(fn () => app('tenant')?->name . ' Dojo')
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
                        ->afterStateUpdated(fn () => $this->selected_entries = []),

                    TextInput::make('weight_kg')
                        ->label('Weight (kg)')
                        ->numeric()
                        ->required()
                        ->suffix('kg')
                        ->minValue(5)
                        ->maxValue(250),
                ]),

            Section::make()
                ->visible(fn () => ! $this->details_confirmed && $this->hasVisibleRegistrationFields())
                ->schema(fn () => $this->buildRegistrationFieldSchema()),

            Section::make('Events')
                ->visible(fn () => $this->details_confirmed)
                ->schema(fn () => $this->buildEventSchema()),

            Section::make('Fee summary')
                ->visible(fn () => $this->details_confirmed)
                ->schema([
                    Placeholder::make('fee_summary')
                        ->label('')
                        ->content(fn () => new \Illuminate\Support\HtmlString($this->feeSummaryHtml())),
                ]),
        ]);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function clearEntryState(): void
    {
        $this->dojo_type         = null;
        $this->dojo_name         = null;
        $this->guest_style       = null;
        $this->rank_id           = null;
        $this->weight_kg         = null;
        $this->custom_fields     = [];
        $this->selected_entries  = [];
        $this->yakusuko_partners = [];
        $this->details_confirmed = false;
    }

    public function getSelectedCompetition(): ?Competition
    {
        return $this->competition_id ? Competition::find($this->competition_id) : null;
    }

    private function buildCtx(): ?object
    {
        $profile = $this->profile_id ? CompetitorProfile::find($this->profile_id) : null;
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
                    ->content('No eligible divisions found matching your rank, age, and weight. Go back to adjust your details.'),
            ];
        }

        $components = [
            CheckboxList::make('selected_entries')
                ->label('Select divisions to enter')
                ->options($options)
                ->live(),
        ];

        $selectedEventIds = [];
        foreach ($this->selected_entries as $key) {
            $div = Division::find((int) substr($key, 1));
            if ($div) {
                $selectedEventIds[] = $div->competition_event_id;
            }
        }

        foreach (array_intersect(array_keys($partnerEventMap), $selectedEventIds) as $eventId) {
            $components[] = Select::make("yakusuko_partners.{$eventId}")
                ->label('Partner for ' . $partnerEventMap[$eventId])
                ->options(
                    CompetitorProfile::where('profile_complete', true)
                        ->where('is_active', true)
                        ->where('organisation_id', app('tenant')?->id)
                        ->where('id', '!=', $this->profile_id)
                        ->get()
                        ->mapWithKeys(fn ($p) => [$p->id => $p->full_name])
                )
                ->searchable()
                ->nullable()
                ->helperText('Both you and your partner must independently register.');
        }

        return $components;
    }

    public function toggleCustomField(string $id): void
    {
        $this->custom_fields[$id] = ! ($this->custom_fields[$id] ?? false);
    }

    public function getCheckboxRegistrationFields(): array
    {
        $competition = $this->getSelectedCompetition();
        return collect($competition?->registration_fields ?? [])
            ->filter(fn ($f) => ($f['type'] ?? '') === 'checkbox')
            ->values()
            ->toArray();
    }

    private function hasVisibleRegistrationFields(): bool
    {
        return collect($this->getSelectedCompetition()?->registration_fields ?? [])
            ->contains(fn ($f) => ($f['type'] ?? 'text') !== 'checkbox');
    }

    private function buildRegistrationFieldSchema(): array
    {
        $competition = $this->getSelectedCompetition();
        if (! $competition || empty($competition->registration_fields)) {
            return [];
        }

        return collect($competition->registration_fields)->map(function (array $field) {
            $id       = $field['id'];
            $label    = $field['label'] ?? 'Field';
            $required = (bool) ($field['required'] ?? false);

            // Checkbox fields are rendered as native toggles in the blade — skip them here.
            if (($field['type'] ?? 'text') === 'checkbox') {
                return null;
            }

            $component = match ($field['type'] ?? 'text') {
                'textarea' => Textarea::make("custom_fields.{$id}")->label($label)->maxLength(2000)->rows(3),
                'select'   => Select::make("custom_fields.{$id}")->label($label)->options(
                    collect($field['options'] ?? [])->pluck('value')->filter()->mapWithKeys(fn ($v) => [$v => $v])->all()
                ),
                default    => TextInput::make("custom_fields.{$id}")->label($label)->maxLength(500),
            };

            return $required ? $component->required()->validationMessages(['required' => 'This field is required.']) : $component;
        })->filter()->values()->all();
    }

    private function isDivisionOpen($division): bool
    {
        return $division->age_band_id === null
            && $division->rank_band_id === null
            && $division->weight_class_id === null;
    }

    private function feeSummaryHtml(): string
    {
        $competition = $this->getSelectedCompetition();
        if (! $competition) {
            return '';
        }

        $count      = count($this->selected_entries);
        $isLate     = $competition->isLateEnrolment();
        $profile    = $this->profile_id ? CompetitorProfile::find($this->profile_id) : null;
        $isOfficial = $profile?->account && $competition->isOfficial($profile->account);
        $org        = app('tenant');
        $platformFee = (float) ($org->platform_fee ?? 0);

        if ($count === 0) {
            $lines = ['<p class="text-sm text-gray-500">Select events above to see your fee.</p>'];
            if ($platformFee > 0) {
                $lines[] = '<p class="text-xs text-gray-400 mt-2">A platform service fee of ' . tenant_money($platformFee) . ' per registration will be added at checkout. Payment transaction fees may also apply.</p>';
            } else {
                $lines[] = '<p class="text-xs text-gray-400 mt-2">Payment transaction fees may apply.</p>';
            }
            return implode('', $lines);
        }

        $service    = app(EnrolmentService::class);
        $entryFee   = $service->calculateFee($competition, $count, $isLate, $isOfficial);
        $baseFee    = $service->calculateFee($competition, $count, false, $isOfficial);
        $lateSurcharge = $isLate ? (float) $competition->late_surcharge : null;

        $rows = '';

        if ($isOfficial) {
            $rows .= '<tr><td class="py-1 pr-4 text-sm text-gray-600 dark:text-gray-400">Entry fee <span class="text-primary-600 text-xs">(official rate)</span></td><td class="py-1 text-sm font-medium text-right">' . tenant_money($baseFee) . '</td></tr>';
        } else {
            $rows .= '<tr><td class="py-1 pr-4 text-sm text-gray-600 dark:text-gray-400">Entry fee <span class="text-xs text-gray-400">(' . $count . ' ' . ($count === 1 ? 'event' : 'events') . ')</span></td><td class="py-1 text-sm font-medium text-right">' . tenant_money($baseFee) . '</td></tr>';
        }

        if ($lateSurcharge !== null) {
            $rows .= '<tr><td class="py-1 pr-4 text-sm text-warning-600 flex items-center gap-1"><svg class="h-3 w-3 inline shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg> Late surcharge</td><td class="py-1 text-sm font-medium text-right text-warning-600">' . tenant_money($lateSurcharge) . '</td></tr>';
        }

        $rows .= '<tr class="border-t border-gray-200 dark:border-gray-700"><td class="pt-2 pr-4 text-sm font-bold">Total entry fee</td><td class="pt-2 text-sm font-bold text-right">' . tenant_money($entryFee) . '</td></tr>';

        if ($platformFee > 0) {
            $rows .= '<tr><td colspan="2" class="pt-3 text-xs text-gray-400">+ Platform service fee of ' . tenant_money($platformFee) . ' per registration will be added at checkout.</td></tr>';
        }

        $rows .= '<tr><td colspan="2" class="pt-1 text-xs text-gray-400">Payment transaction fees may apply.</td></tr>';

        return '<table class="w-full">' . $rows . '</table>';
    }
}
