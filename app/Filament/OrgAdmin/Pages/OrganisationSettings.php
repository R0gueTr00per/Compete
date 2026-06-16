<?php

namespace App\Filament\OrgAdmin\Pages;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Illuminate\Support\HtmlString;
use App\Notifications\Notification;
use Filament\Pages\Page;

class OrganisationSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Preferences';
    protected static ?string $navigationGroup = 'System';
    protected static ?int $navigationSort = 99;
    protected static string $view = 'filament.org-admin.pages.organisation-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $tenant = app('tenant');
        return $tenant && (auth()->user()?->isOrgAdmin($tenant) ?? false);
    }

    public function mount(): void
    {
        $tenant = app('tenant');
        $groupName = $tenant?->group_name;
        $groupPreset = in_array($groupName, ['Dojo', 'Club', 'Team', null]) ? ($groupName ?? 'Dojo') : 'Other';

        $this->form->fill([
            'ai_context'               => $tenant?->ai_context,
            'ai_tone_presets'          => $tenant?->ai_tone_presets ?? [],
            'auto_email_insights'      => $tenant?->auto_email_insights ?? true,
            'insights_auto_refresh'         => $tenant?->insights_auto_refresh ?? true,
            'competitor_summaries_enabled'  => $tenant?->competitor_summaries_enabled ?? true,
            'dashboard_closed_days'    => $tenant?->dashboard_closed_days ?? 7,
            'timezone'                 => $tenant?->timezone,
            'date_format'              => $tenant?->date_format,
            'currency'                 => $tenant?->currency,
            'cancellation_days_before'    => $tenant?->cancellation_days_before ?? 0,
            'supported_payment_methods'   => $tenant?->supported_payment_methods ?? ['cash'],
            'instructors_can_collect_payments' => $tenant?->instructors_can_collect_payments ?? false,
            'group_name_preset'           => $groupPreset,
            'group_name_custom'           => $groupPreset === 'Other' ? $groupName : null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('AI Insights')
                    ->description('Customise how AI analyses your competitions.')
                    ->schema([
                        Textarea::make('ai_context')
                            ->label('Organisation context for AI')
                            ->placeholder('e.g. This is a judo competition organised by Judo Australia. Competitors are graded from white belt (10th kyu) to black belt (1st dan and above).')
                            ->helperText('This text is included in every AI insight prompt. Describe your sport, grading system, or any domain knowledge that helps the AI give better advice.')
                            ->rows(4)
                            ->maxLength(1000)
                            ->rules(['not_regex:/<[^>]+>/']),
                        CheckboxList::make('ai_tone_presets')
                            ->label('Tone presets')
                            ->helperText('Selected presets are appended to your context and shape how the AI writes its insights.')
                            ->options([
                                'humorous'      => '😄 Humorous — light humour and an upbeat tone',
                                'sensei'        => '🥋 Your sensei — speaks as an instructor to a student',
                                'motivational'  => '🔥 Motivational coach — high-energy and encouraging',
                                'traditional'   => '⛩️ Traditional — formal language with honour and discipline',
                                'brief'         => '⚡ Brief — short sentences, no fluff',
                                'parent_friendly' => '👨‍👩‍👧 Parent-friendly — warm, community-focused tone',
                            ])
                            ->columns(2)
                            ->gridDirection('row'),
                        Toggle::make('insights_auto_refresh')
                            ->label('Auto-generate insights when competition status changes')
                            ->helperText('When enabled, AI insights are automatically regenerated each time a competition moves to a new status.')
                            ->default(true),
                        Toggle::make('auto_email_insights')
                            ->label('Email all administrators when insights are generated')
                            ->helperText('When enabled, all active organisation administrators receive an email each time insights are generated.')
                            ->default(true),
                        Placeholder::make('_competitor_summaries_divider')
                            ->label('')
                            ->content(new HtmlString('<div class="border-t border-gray-200 dark:border-white/10 pt-2"><p class="text-sm font-semibold text-gray-950 dark:text-white">Competitor Summaries</p></div>'))
                            ->columnSpanFull(),
                        Toggle::make('competitor_summaries_enabled')
                            ->label('Enable AI competitor summaries on the portal')
                            ->helperText('When enabled, personalised AI summaries are generated for competitors and shown on their portal dashboard after a competition completes.')
                            ->default(true),
                    ]),

                Section::make('Dashboard')
                    ->description('Control what appears on your organisation dashboard.')
                    ->schema([
                        TextInput::make('dashboard_closed_days')
                            ->label('Days to keep completed competitions on dashboard')
                            ->helperText('Completed competitions are hidden from the dashboard after this many days.')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(365)
                            ->default(7)
                            ->suffix('days'),
                    ]),

                Section::make('Registration')
                    ->description('Control cancellation behaviour for competitors.')
                    ->schema([
                        TextInput::make('cancellation_days_before')
                            ->label('Allow cancellation with refund up to X days before competition')
                            ->helperText('Competitors can withdraw and receive a fee return up to this many days before the competition date. Set to 0 to allow cancellation with refund right up to the competition day.')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(365)
                            ->default(0)
                            ->suffix('days'),
                    ]),

                Section::make('Payments')
                    ->description('Configure the payment methods your organisation accepts.')
                    ->schema([
                        CheckboxList::make('supported_payment_methods')
                            ->label('Accepted payment methods')
                            ->helperText('Selected methods will appear as options when recording payments against transactions.')
                            ->options([
                                'cash'   => 'Cash',
                                'stripe' => 'Stripe — Online payments (coming soon)',
                            ])
                            ->default(['cash'])
                            ->live(),
                        Toggle::make('instructors_can_collect_payments')
                            ->label('Allow instructors to collect payments in person')
                            ->helperText(fn (Get $get) => in_array('cash', $get('supported_payment_methods') ?? [])
                                ? 'When enabled, instructors get an "Accept Payment" screen where they can scan a competitor\'s QR code (or search by name) and record their payment on the spot.'
                                : 'Requires Cash to be an accepted payment method above.')
                            ->disabled(fn (Get $get) => ! in_array('cash', $get('supported_payment_methods') ?? []))
                            ->default(false),
                    ]),

                Section::make('Contact')
                    ->description('Contact details shown to competitors in competition emails.')
                    ->schema([
                        TextInput::make('contact_phone')
                            ->label('Phone')
                            ->maxLength(50),
                        TextInput::make('contact_email')
                            ->label('Email address')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('website')
                            ->label('Website')
                            ->url()
                            ->maxLength(500),
                    ]),

                Section::make('Groups')
                    ->description('Customise what groups are called in your organisation.')
                    ->schema([
                        Radio::make('group_name_preset')
                            ->label('Group name')
                            ->options([
                                'Dojo'  => 'Dojo',
                                'Club'  => 'Club',
                                'Team'  => 'Team',
                                'Other' => 'Other',
                            ])
                            ->default('Dojo')
                            ->live(),
                        TextInput::make('group_name_custom')
                            ->label('Custom group name')
                            ->placeholder('e.g. Academy, Studio, Gym')
                            ->helperText('Singular form — plural will be this name with "s" appended.')
                            ->maxLength(50)
                            ->required()
                            ->visible(fn (Get $get) => $get('group_name_preset') === 'Other'),
                    ]),

                Section::make('Regional')
                    ->description('Set your timezone, date format, and currency for consistent display across the platform.')
                    ->schema([
                        Select::make('timezone')
                            ->label('Timezone')
                            ->options(
                                collect(\DateTimeZone::listIdentifiers())
                                    ->mapWithKeys(fn ($tz) => [$tz => $tz])
                                    ->toArray()
                            )
                            ->searchable()
                            ->placeholder('Select timezone'),
                        Select::make('date_format')
                            ->label('Date format')
                            ->options([
                                'd M Y'  => '29 May 2026',
                                'd/m/Y'  => '29/05/2026',
                                'm/d/Y'  => '05/29/2026',
                                'Y-m-d'  => '2026-05-29',
                            ])
                            ->placeholder('Select date format'),
                        TextInput::make('currency')
                            ->label('Currency code')
                            ->placeholder('e.g. AUD, USD, EUR')
                            ->helperText('ISO 4217 currency code used for displaying fees.')
                            ->maxLength(10)
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->dehydrateStateUsing(fn ($state) => strtoupper((string) $state) ?: null),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $tenant = app('tenant');
        if (! $tenant) return;

        $data = $this->form->getState();

        $groupPreset = $data['group_name_preset'] ?? 'Dojo';
        $groupName   = $groupPreset === 'Other'
            ? ($data['group_name_custom'] ?? 'Dojo')
            : $groupPreset;

        $tenant->update([
            'ai_context'               => strip_tags($data['ai_context'] ?? ''),
            'ai_tone_presets'          => $data['ai_tone_presets'] ?? [],
            'auto_email_insights'      => $data['auto_email_insights'] ?? true,
            'insights_auto_refresh'         => $data['insights_auto_refresh'] ?? true,
            'competitor_summaries_enabled'  => $data['competitor_summaries_enabled'] ?? true,
            'dashboard_closed_days'    => $data['dashboard_closed_days'] ?? 7,
            'timezone'                 => $data['timezone'] ?? null,
            'date_format'              => $data['date_format'] ?? null,
            'currency'                 => $data['currency'] ?? null,
            'cancellation_days_before'  => $data['cancellation_days_before'] ?? 0,
            'supported_payment_methods' => $data['supported_payment_methods'] ?? ['cash'],
            'instructors_can_collect_payments' => $data['instructors_can_collect_payments'] ?? false,
            'contact_phone'             => $data['contact_phone'] ?? null,
            'contact_email'            => $data['contact_email'] ?? null,
            'website'                  => $data['website'] ?? null,
            'group_name'               => $groupName,
        ]);

        Notification::make()
            ->success()
            ->title('Preferences saved')
            ->send();

        $this->redirect(static::getUrl());
    }

    public function getTitle(): string
    {
        return 'Preferences';
    }
}
