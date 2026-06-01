<?php

namespace App\Filament\OrgAdmin\Resources\CompetitionResource\Pages;

use App\Filament\OrgAdmin\Resources\CompetitionResource;
use App\Models\Competition;
use App\Notifications\Notification;
use App\Services\DivisionAssignmentService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Get;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Illuminate\Support\Str;

class CreateCompetition extends CreateRecord
{
    use HasWizard;

    protected static string $resource = CompetitionResource::class;

    private ?int $templateId = null;

    protected function getSteps(): array
    {
        return [
            Step::make('Start')
                ->schema([
                    Radio::make('start_mode')
                        ->label('')
                        ->options([
                            'blank'    => 'Start blank',
                            'template' => 'Start from a template',
                        ])
                        ->descriptions([
                            'blank'    => 'Set up the competition structure from scratch.',
                            'template' => 'Copy events, bands, divisions, fees, and settings from a saved template.',
                        ])
                        ->default('blank')
                        ->live()
                        ->required(),

                    Select::make('template_id')
                        ->label('Choose template')
                        ->options(fn () => Competition::activeTemplates()
                            ->where('organisation_id', app('tenant')?->id)
                            ->orderBy('name')
                            ->pluck('name', 'id'))
                        ->visible(fn (Get $get) => $get('start_mode') === 'template')
                        ->required(fn (Get $get) => $get('start_mode') === 'template')
                        ->live()
                        ->afterStateUpdated(function ($state, $set) {
                            if (! $state) {
                                return;
                            }
                            $template = Competition::find($state);
                            if (! $template) {
                                return;
                            }
                            $set('fee_first_event', $template->fee_first_event);
                            $set('fee_additional_event', $template->fee_additional_event);
                            $set('late_surcharge', $template->late_surcharge);
                            $set('fee_official_first_event', $template->fee_official_first_event);
                            $set('fee_official_additional_event', $template->fee_official_additional_event);
                            $set('location_name', $template->location_name);
                            $set('location_address', $template->location_address);
                            $set('start_time', $template->start_time);
                            $set('checkin_time', $template->checkin_time);
                            $set('target_competitors', $template->target_competitors);

                            Notification::make()
                                ->info()
                                ->title('Fees pre-filled from template — registration fields will be copied on save')
                                ->send();
                        }),
                ]),

            Step::make('Details')
                ->schema([
                    Section::make()
                        ->columns(2)
                        ->schema([
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),

                            DatePicker::make('competition_date')
                                ->required(),

                            DatePicker::make('enrolment_due_date')
                                ->nullable(),

                            TimePicker::make('start_time')
                                ->required()
                                ->seconds(false),

                            TimePicker::make('checkin_time')
                                ->seconds(false)
                                ->nullable(),

                            TextInput::make('location_name')
                                ->maxLength(255),

                            TextInput::make('location_address')
                                ->maxLength(500),

                            TextInput::make('target_competitors')
                                ->label('Target competitors')
                                ->numeric()
                                ->minValue(1)
                                ->step(1),
                        ]),

                    Section::make('Competitor Fees')
                        ->columns(3)
                        ->hidden(fn (Get $get) => $get('start_mode') === 'template')
                        ->schema([
                            TextInput::make('fee_first_event')
                                ->label('First event fee (' . tenant_currency() . ')')
                                ->numeric()
                                ->required()
                                ->prefix(tenant_currency_symbol()),

                            TextInput::make('fee_additional_event')
                                ->label('Additional event fee (' . tenant_currency() . ')')
                                ->numeric()
                                ->required()
                                ->prefix(tenant_currency_symbol()),

                            TextInput::make('late_surcharge')
                                ->label('Late surcharge (' . tenant_currency() . ')')
                                ->numeric()
                                ->required()
                                ->prefix(tenant_currency_symbol()),
                        ]),

                    Section::make('Official Fees')
                        ->columns(2)
                        ->hidden(fn (Get $get) => $get('start_mode') === 'template')
                        ->schema([
                            TextInput::make('fee_official_first_event')
                                ->label('First event fee (' . tenant_currency() . ')')
                                ->numeric()
                                ->nullable()
                                ->prefix(tenant_currency_symbol())
                                ->helperText('Leave blank to use standard fees for officials.'),

                            TextInput::make('fee_official_additional_event')
                                ->label('Additional event fee (' . tenant_currency() . ')')
                                ->numeric()
                                ->nullable()
                                ->prefix(tenant_currency_symbol()),
                        ]),

                    Section::make('Registration Fields')
                        ->description('Custom fields that competitors must fill in when enrolling.')
                        ->hidden(fn (Get $get) => $get('start_mode') === 'template')
                        ->schema([
                            Repeater::make('registration_fields')
                                ->label('')
                                ->schema([
                                    Hidden::make('id')
                                        ->default(fn () => (string) Str::uuid()),
                                    TextInput::make('label')
                                        ->label('Field label')
                                        ->required()
                                        ->maxLength(100)
                                        ->columnSpan(2),
                                    Select::make('type')
                                        ->label('Type')
                                        ->options([
                                            'text'     => 'Text',
                                            'textarea' => 'Paragraph',
                                            'checkbox' => 'Checkbox (yes/no)',
                                            'select'   => 'Dropdown',
                                        ])
                                        ->required()
                                        ->live(),
                                    Toggle::make('required')
                                        ->label('Required')
                                        ->default(false)
                                        ->inline(false),
                                    Repeater::make('options')
                                        ->label('Dropdown options')
                                        ->simple(
                                            TextInput::make('value')
                                                ->label('Option')
                                                ->required()
                                                ->maxLength(100),
                                        )
                                        ->visible(fn (Get $get) => $get('type') === 'select')
                                        ->addActionLabel('Add option')
                                        ->columnSpanFull()
                                        ->reorderable(false),
                                ])
                                ->columns(4)
                                ->addActionLabel('Add field')
                                ->default([])
                                ->reorderable()
                                ->collapsible()
                                ->itemLabel(fn (array $state): ?string => $state['label'] ?? null),
                        ]),
                ]),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organisation_id'] = app('tenant')?->id;
        $this->templateId = isset($data['template_id']) ? (int) $data['template_id'] : null;
        unset($data['template_id'], $data['start_mode']);
        return $data;
    }

    protected function beforeCreate(): void
    {
        $locations = collect(array_values($this->data['locations'] ?? []))
            ->map(fn ($v) => strtolower(trim((string) (is_array($v) ? ($v['location'] ?? array_values($v)[0] ?? '') : $v))))
            ->filter();

        if ($locations->count() !== $locations->unique()->count()) {
            Notification::make()
                ->danger()
                ->title('Duplicate location')
                ->body('Each location must have a unique name.')
                ->send();
            $this->halt();
        }
    }

    protected function afterCreate(): void
    {
        if (! $this->templateId) {
            return;
        }

        $template = Competition::find($this->templateId);
        if (! $template) {
            return;
        }

        $service = app(DivisionAssignmentService::class);
        $service->copyDivisionsFromCompetition($template, $this->record);

        $this->record->update([
            'copied_from_id'      => $this->templateId,
            'registration_fields' => $template->registration_fields,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
