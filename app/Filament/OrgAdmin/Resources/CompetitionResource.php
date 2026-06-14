<?php

namespace App\Filament\OrgAdmin\Resources;

use App\Filament\OrgAdmin\Actions\HistoryTableAction;
use App\Filament\OrgAdmin\Resources\CompetitionResource\Pages;
use App\Filament\OrgAdmin\Resources\CompetitionResource\RelationManagers;
use App\Models\Competition;
use App\Models\Division;
use App\Models\Enrolment;
use App\Models\EnrolmentEvent;
use App\Models\JudgeScore;
use App\Models\Result;
use App\Jobs\SendCompetitionPromoEmailJob;
use App\Jobs\SendCompetitionReminderEmailJob;
use App\Models\CompetitorProfile;
use App\Models\User;
use App\Services\DivisionAssignmentService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Illuminate\Support\Str;
use App\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CompetitionResource extends Resource
{
    protected static ?string $model = Competition::class;
    protected static ?string $navigationIcon = 'heroicon-o-trophy';
    protected static ?string $navigationGroup = 'Competitions';
    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $tenant = app('tenant');
        if (! $tenant) return true;
        return auth()->user()?->isOrgAdmin($tenant) ?? false;
    }

    public static function canCreate(): bool
    {
        $tenant = app('tenant');
        if (! $tenant) return true;
        return auth()->user()?->isOrgAdmin($tenant) ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $tenant = app('tenant');
        if (! $tenant) return true;
        return auth()->user()?->isOrgAdmin($tenant) ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $tenant = app('tenant');
        if (! $tenant) return true;
        return auth()->user()?->isOrgAdmin($tenant) ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('organisation_id', app('tenant')?->id)
            ->where('is_template', false);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make()
                ->columnSpanFull()
                ->tabs([
                    Tab::make('Details')
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

                                    TimePicker::make('end_time')
                                        ->seconds(false)
                                        ->nullable(),

                                    TimePicker::make('checkin_time')
                                        ->seconds(false)
                                        ->nullable(),

                                    TextInput::make('location_name')
                                        ->maxLength(255)
                                        ->helperText('The venue or facility name (e.g. City Sports Centre). Shown on the competitor portal.'),

                                    TextInput::make('location_address')
                                        ->maxLength(500)
                                        ->helperText('Full street address of the venue (e.g. 123 Main St, Brisbane QLD 4000).'),

                                    TextInput::make('location_url')
                                        ->label('Map URL')
                                        ->url()
                                        ->nullable()
                                        ->columnSpanFull()
                                        ->maxLength(2048)
                                        ->helperText('Paste a Google Maps or Apple Maps link. Competitors can tap this to open the map on their device.'),

                                    TextInput::make('target_competitors')
                                        ->label('Target competitors')
                                        ->numeric()
                                        ->minValue(1)
                                        ->step(1),

                                    Select::make('status')
                                        ->options([
                                            'planning'          => 'Planning',
                                            'advertise'         => 'Advertise',
                                            'open'              => 'Open for registration',
                                            'enrolments_closed' => 'Registrations Closed',
                                            'check_in'          => 'Check-in',
                                            'running'           => 'Running',
                                            'complete'          => 'Complete',
                                        ])
                                        ->required()
                                        ->default('planning'),
                                ]),


                        ]),

                    Tab::make('Fees')
                        ->schema([
                            Section::make('Competitor Fees')
                                ->columns(3)
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
                                ->columns(3)
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
                        ]),

                    Tab::make('Registration Fields')
                        ->schema([
                            Section::make()
                                ->description('Custom fields that competitors must fill in when registering.')
                                ->schema([
                                    Repeater::make('registration_fields')
                                        ->label('')
                                        ->schema([
                                            Hidden::make('id')
                                                ->default(fn () => (string) Str::uuid()),
                                            Textarea::make('label')
                                                ->label('Field label')
                                                ->required()
                                                ->maxLength(1000)
                                                ->rows(1)
                                                ->autosize()
                                                ->columnSpan(2),
                                            Select::make('type')
                                                ->label('Type')
                                                ->options([
                                                    'text'     => 'Text',
                                                    'textarea' => 'Paragraph',
                                                    'checkbox' => 'Toggle (yes/no)',
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
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('competition_date')
                    ->date(tenant_date_format())
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'planning'          => 'Planning',
                        'advertise'         => 'Advertise',
                        'open'              => 'Open',
                        'enrolments_closed' => 'Registrations Closed',
                        'check_in'          => 'Check-in',
                        'running'           => 'Running',
                        'complete'          => 'Complete',
                        default             => ucfirst($state),
                    })
                    ->color(fn (string $state) => match ($state) {
                        'planning'          => 'gray',
                        'advertise'         => 'info',
                        'open'              => 'success',
                        'enrolments_closed' => 'gray',
                        'check_in'          => 'warning',
                        'running'           => 'info',
                        'complete'          => 'primary',
                        default             => 'gray',
                    }),

                TextColumn::make('enrolments_count')
                    ->label('Registrations')
                    ->counts('enrolments')
                    ->sortable(),

            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'planning'          => 'Planning',
                        'advertise'         => 'Advertise',
                        'open'              => 'Open',
                        'enrolments_closed' => 'Registrations Closed',
                        'check_in'          => 'Check-in',
                        'running'           => 'Running',
                        'complete'          => 'Complete',
                    ]),
            ])
            ->actions([
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('events')
                        ->label('Events')
                        ->icon('heroicon-o-rectangle-stack')
                        ->color('info')
                        ->url(fn (Competition $record) => static::getUrl('events', ['record' => $record])),
                    Action::make('schedule')
                        ->label('Scheduling')
                        ->icon('heroicon-o-calendar-days')
                        ->color('warning')
                        ->url(fn (Competition $record) => static::getUrl('schedule', ['record' => $record])),
                    Action::make('officials')
                        ->label('Officials')
                        ->icon('heroicon-o-identification')
                        ->color('info')
                        ->url(fn (Competition $record) => static::getUrl('officials', ['record' => $record])),
                    Action::make('enrolments')
                        ->label('Registrations')
                        ->icon('heroicon-o-clipboard-document-list')
                        ->color('gray')
                        ->url(fn (Competition $record) => route('filament.org-admin.resources.enrolments.index') . '?competition_id=' . $record->id),
                    Action::make('openRegistrations')
                        ->label('Open Registrations')
                        ->icon('heroicon-o-lock-open')
                        ->color('success')
                        ->visible(fn (Competition $record) => in_array($record->status, ['planning', 'advertise']))
                        ->modalHeading('Open registrations')
                        ->modalDescription(function (Competition $record) {
                            $warnings = [];

                            $missingTarget = $record->competitionEvents()
                                ->whereNull('default_max_competitors')
                                ->count();
                            if ($missingTarget > 0) {
                                $warnings[] = "{$missingTarget} event(s) are missing a competitor target — schedule times cannot be calculated.";
                            }

                            $missingTiming = $record->competitionEvents()
                                ->whereNull('seconds_per_competitor')
                                ->whereNull('round_duration_seconds')
                                ->count();
                            if ($missingTiming > 0) {
                                $warnings[] = "{$missingTiming} event(s) are missing timing values — schedule times cannot be calculated.";
                            }

                            return $warnings ? implode(' ', $warnings) : null;
                        })
                        ->form([
                            Toggle::make('send_promo_email')
                                ->label('Send promotional email to eligible users')
                                ->helperText('Sends an email to all active users with profiles in this organisation who have not opted out.')
                                ->default(true),
                        ])
                        ->modalSubmitActionLabel('Open registrations')
                        ->action(function (Competition $record, array $data) {
                            $record->update(['status' => 'open']);
                            if ($data['send_promo_email'] ?? false) {
                                SendCompetitionPromoEmailJob::dispatch($record);
                            }
                        }),
                    Action::make('advance')
                        ->label(fn (Competition $record) => match ($record->status) {
                            'open'              => 'Close Registrations',
                            'enrolments_closed' => 'Begin Check-ins',
                            'check_in'          => 'Start Competition',
                            'running'           => 'Conclude Competition',
                            default             => 'Advance',
                        })
                        ->icon(fn (Competition $record) => match ($record->status) {
                            'open'              => 'heroicon-o-lock-closed',
                            'enrolments_closed' => 'heroicon-o-clipboard-document-check',
                            'check_in'          => 'heroicon-o-play',
                            'running'           => 'heroicon-o-flag',
                            default             => 'heroicon-o-arrow-right',
                        })
                        ->color(fn (Competition $record) => match ($record->status) {
                            'open'              => 'warning',
                            'enrolments_closed' => 'primary',
                            'check_in'          => 'info',
                            'running'           => 'danger',
                            default             => 'gray',
                        })
                        ->requiresConfirmation()
                        ->modalDescription(function (Competition $record) {
                            return match ($record->status) {
                                'open'              => 'Close registrations for this competition?',
                                'enrolments_closed' => 'This will begin the check-in phase. Scoring will not be active until the competition starts.',
                                'check_in' => (function () use ($record) {
                                    $completedDivisions = $record->allDivisions()
                                        ->where('divisions.status', 'complete')
                                        ->count();
                                    $msg = 'This will start the competition. Undo check-in will be disabled and scoring will become active.';
                                    if ($completedDivisions > 0) {
                                        $msg .= " Warning: {$completedDivisions} division(s) are already marked as complete.";
                                    }
                                    return $msg;
                                })(),
                                'running'  => 'Conclude this competition? Results will become visible to competitors.',
                                default    => 'Are you sure?',
                            };
                        })
                        ->visible(fn (Competition $record) => ! in_array($record->status, ['planning', 'advertise', 'complete']))
                        ->action(fn (Competition $record) => $record->update(['status' => match ($record->status) {
                            'open'              => 'enrolments_closed',
                            'enrolments_closed' => 'check_in',
                            'check_in'          => 'running',
                            'running'           => 'complete',
                            default             => $record->status,
                        }])),
                    Action::make('sendReminder')
                        ->label('Send reminder email')
                        ->icon('heroicon-o-envelope')
                        ->color('gray')
                        ->visible(fn (Competition $record) => $record->status === 'open')
                        ->requiresConfirmation()
                        ->modalHeading('Send reminder email')
                        ->modalDescription(function (Competition $record) {
                            $orgId         = $record->organisation_id;
                            $orgProfileIds = CompetitorProfile::where('organisation_id', $orgId)
                                ->where('is_active', true)
                                ->pluck('id');
                            $enrolledProfileIds = Enrolment::where('competition_id', $record->id)
                                ->whereIn('competitor_profile_id', $orgProfileIds)
                                ->pluck('competitor_profile_id');
                            $registeredUserIds = CompetitorProfile::whereIn('id', $enrolledProfileIds)
                                ->get()
                                ->flatMap(fn ($p) => array_filter([$p->user_id, $p->owner_user_id]))
                                ->unique();
                            $allUserIds = CompetitorProfile::whereIn('id', $orgProfileIds)
                                ->get()
                                ->flatMap(fn ($p) => array_filter([$p->user_id, $p->owner_user_id]))
                                ->unique();
                            $count = User::whereIn('id', $allUserIds->diff($registeredUserIds))
                                ->where('status', 'active')
                                ->where('receive_competition_emails', true)
                                ->count();
                            return "Send a reminder email to {$count} user(s) who have not yet registered for this competition.";
                        })
                        ->modalSubmitActionLabel('Send reminder')
                        ->action(fn (Competition $record) => SendCompetitionReminderEmailJob::dispatch($record)),
                    Action::make('duplicate')
                        ->label('Duplicate competition')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('gray')
                        ->form([
                            TextInput::make('name')
                                ->label('Name for new competition')
                                ->required()
                                ->maxLength(255),
                            DatePicker::make('competition_date')
                                ->label('Competition date')
                                ->required(),
                            Toggle::make('copy_structure')
                                ->label('Copy event types, bands & divisions')
                                ->default(true)
                                ->helperText('Copies the full event structure. Registrations are not copied.'),
                        ])
                        ->fillForm(fn (Competition $record): array => [
                            'name'             => $record->name . ' (Copy)',
                            'competition_date' => $record->competition_date?->addYear(),
                        ])
                        ->action(function (Competition $record, array $data, DivisionAssignmentService $svc) {
                            $new = Competition::create([
                                'name'                 => $data['name'],
                                'organisation_id'      => $record->organisation_id,
                                'competition_date'     => $data['competition_date'],
                                'start_time'           => $record->start_time,
                                'checkin_time'         => $record->checkin_time,
                                'location_name'        => $record->location_name,
                                'location_address'     => $record->location_address,
                                'location_url'         => $record->location_url,
                                'enrolment_due_date'   => null,
                                'target_competitors'              => $record->target_competitors,
                                'fee_first_event'                 => $record->fee_first_event,
                                'fee_additional_event'            => $record->fee_additional_event,
                                'late_surcharge'                  => $record->late_surcharge,
                                'fee_official_first_event'        => $record->fee_official_first_event,
                                'fee_official_additional_event'   => $record->fee_official_additional_event,
                                'status'                          => 'planning',
                                'registration_fields'  => $record->registration_fields,
                                'copied_from_id'       => $record->id,
                            ]);

                            if ($data['copy_structure']) {
                                $svc->copyDivisionsFromCompetition($record, $new);
                            }

                            Notification::make()
                                ->success()
                                ->title("Competition duplicated: {$new->name}")
                                ->send();
                        }),
                    Action::make('saveAsTemplate')
                        ->label('Save as Template')
                        ->icon('heroicon-o-bookmark')
                        ->color('gray')
                        ->form([
                            TextInput::make('template_name')
                                ->label('Template name')
                                ->required()
                                ->maxLength(255),
                        ])
                        ->fillForm(fn (Competition $record): array => [
                            'template_name' => $record->name,
                        ])
                        ->action(function (Competition $record, array $data, DivisionAssignmentService $svc) {
                            $template = Competition::create([
                                'organisation_id'                 => $record->organisation_id,
                                'name'                            => $data['template_name'],
                                'competition_date'                => null,
                                'enrolment_due_date'              => null,
                                'start_time'                      => $record->start_time,
                                'checkin_time'                    => $record->checkin_time,
                                'location_name'                   => $record->location_name,
                                'location_address'                => $record->location_address,
                                'location_url'                    => $record->location_url,
                                'target_competitors'              => $record->target_competitors,
                                'fee_first_event'                 => $record->fee_first_event,
                                'fee_additional_event'            => $record->fee_additional_event,
                                'late_surcharge'                  => $record->late_surcharge,
                                'fee_official_first_event'        => $record->fee_official_first_event,
                                'fee_official_additional_event'   => $record->fee_official_additional_event,
                                'registration_fields'             => $record->registration_fields,
                                'status'                          => 'planning',
                                'is_template'                     => true,
                                'template_active'                 => true,
                            ]);

                            $svc->copyDivisionsFromCompetition($record, $template);

                            Notification::make()
                                ->success()
                                ->title('Template "' . $data['template_name'] . '" created successfully')
                                ->send();
                        }),
                    Action::make('downloadPdf')
                        ->label('Download results PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('gray')
                        ->visible(fn (Competition $record) => $record->status === 'complete')
                        ->action(function (Competition $record) {
                            $pdf = app(\App\Services\PdfReportService::class)
                                ->generateCompetitionResults($record);
                            $filename = str($record->name)->slug() . '-results.pdf';
                            return response()->streamDownload(fn () => print($pdf), $filename, [
                                'Content-Type' => 'application/pdf',
                            ]);
                        }),
                    HistoryTableAction::make(),
                    Action::make('purge')
                        ->label('Delete competition')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->visible(true)
                        ->modalHeading('Permanently delete competition')
                        ->modalDescription(function (Competition $record) {
                            $enrolmentCount    = $record->enrolments()->count();
                            $eventIds          = $record->competitionEvents()->pluck('id');
                            $enrolmentEventIds = EnrolmentEvent::whereIn(
                                'enrolment_id', $record->enrolments()->select('id')
                            )->pluck('id');
                            $resultCount       = Result::whereIn('enrolment_event_id', $enrolmentEventIds)->count();
                            $divisionCount     = Division::whereIn('competition_event_id', $eventIds)->count();

                            $warning = $enrolmentCount > 0
                                ? "<p style='margin-top:.75rem;padding:.6rem .8rem;background:#fef2f2;border:1px solid #fca5a5;border-radius:.375rem;color:#991b1b'>"
                                  . "<strong>Warning:</strong> This competition has <strong>{$enrolmentCount} registration(s)</strong>"
                                  . ($resultCount > 0 ? " and <strong>{$resultCount} result record(s)</strong>" : '')
                                  . ". If fees have been collected, you should reconcile payments before deleting."
                                  . "</p>"
                                : '';

                            return new \Illuminate\Support\HtmlString(
                                "<p>Permanently deleting <strong>" . e($record->name) . "</strong> will destroy:</p>"
                                . "<ul style='margin-top:.5rem;padding-left:1.25rem;list-style:disc'>"
                                . "<li><strong>{$enrolmentCount}</strong> registration(s) and all registration event records</li>"
                                . "<li><strong>{$resultCount}</strong> result(s) and judge score records</li>"
                                . "<li><strong>{$divisionCount}</strong> division(s) across all events</li>"
                                . "<li>All age bands, rank bands, and weight classes</li>"
                                . "</ul>"
                                . $warning
                                . "<p style='margin-top:.75rem'>This <strong>cannot be undone</strong>. Type the competition name below to confirm.</p>"
                            );
                        })
                        ->modalSubmitActionLabel('Delete permanently')
                        ->form(fn (Competition $record) => [
                            \Filament\Forms\Components\TextInput::make('confirm_name')
                                ->label("Type \"{$record->name}\" to confirm")
                                ->required()
                                ->placeholder($record->name)
                                ->rules([
                                    function () use ($record) {
                                        return function (string $attribute, $value, \Closure $fail) use ($record) {
                                            if ($value !== $record->name) {
                                                $fail("That doesn't match — type \"{$record->name}\" exactly.");
                                            }
                                        };
                                    },
                                ]),
                        ])
                        ->action(function (Competition $record, array $data) {
                            $name        = $record->name;
                            $eventIds    = $record->competitionEvents()->pluck('id');
                            $enrolmentIds = $record->enrolments()->pluck('id');
                            $enrolEventIds = EnrolmentEvent::whereIn('enrolment_id', $enrolmentIds)->pluck('id');
                            $resultIds   = Result::whereIn('enrolment_event_id', $enrolEventIds)->pluck('id');

                            JudgeScore::whereIn('result_id', $resultIds)->delete();
                            Result::whereIn('id', $resultIds)->delete();

                            // Null self-referential partner links before deleting enrolment events
                            EnrolmentEvent::whereIn('id', $enrolEventIds)
                                ->update(['partner_enrolment_event_id' => null]);
                            EnrolmentEvent::whereIn('id', $enrolEventIds)->delete();
                            Enrolment::whereIn('id', $enrolmentIds)->delete();

                            Division::whereIn('competition_event_id', $eventIds)->delete();
                            $record->competitionEvents()->delete();
                            $record->ageBands()->delete();
                            $record->rankBands()->delete();
                            $record->weightClasses()->delete();

                            $record->forceDelete();

                            Notification::make()
                                ->success()
                                ->title("\"{$name}\" permanently deleted.")
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('competition_date', 'desc');
    }

    public static function getRelationManagers(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'    => Pages\ListCompetitions::route('/'),
            'create'   => Pages\CreateCompetition::route('/create'),
            'edit'     => Pages\EditCompetition::route('/{record}/edit'),
            'config'    => Pages\ManageCompetitionConfig::route('/{record}/config'),
            'events'    => Pages\ManageCompetitionEvents::route('/{record}/events'),
            'schedule'  => Pages\ManageCompetitionSchedule::route('/{record}/schedule'),
            'officials' => Pages\ManageCompetitionOfficials::route('/{record}/officials'),
            'insights'  => Pages\ManageCompetitionInsights::route('/{record}/insights'),
            'tasks'     => Pages\ManageCompetitionTasks::route('/{record}/tasks'),
        ];
    }
}
