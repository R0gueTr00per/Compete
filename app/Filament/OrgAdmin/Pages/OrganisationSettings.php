<?php

namespace App\Filament\OrgAdmin\Pages;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
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
        $this->form->fill([
            'ai_context'             => $tenant?->ai_context,
            'auto_email_insights'    => $tenant?->auto_email_insights ?? true,
            'insights_auto_refresh'  => $tenant?->insights_auto_refresh ?? true,
            'dashboard_closed_days'  => $tenant?->dashboard_closed_days ?? 7,
            'timezone'               => $tenant?->timezone,
            'date_format'            => $tenant?->date_format,
            'currency'               => $tenant?->currency,
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
                            ->maxLength(1000),
                        Toggle::make('insights_auto_refresh')
                            ->label('Auto-generate insights when competition status changes')
                            ->helperText('When enabled, AI insights are automatically regenerated each time a competition moves to a new status.')
                            ->default(true),
                        Toggle::make('auto_email_insights')
                            ->label('Email all org admins when insights are generated')
                            ->helperText('When enabled, all active organisation administrators receive an email each time insights are generated.')
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

        $tenant->update([
            'ai_context'            => $data['ai_context'] ?? null,
            'auto_email_insights'   => $data['auto_email_insights'] ?? true,
            'insights_auto_refresh' => $data['insights_auto_refresh'] ?? true,
            'dashboard_closed_days' => $data['dashboard_closed_days'] ?? 7,
            'timezone'              => $data['timezone'] ?? null,
            'date_format'           => $data['date_format'] ?? null,
            'currency'              => $data['currency'] ?? null,
        ]);

        Notification::make()
            ->success()
            ->title('Preferences saved')
            ->send();
    }

    public function getTitle(): string
    {
        return 'Preferences';
    }
}
