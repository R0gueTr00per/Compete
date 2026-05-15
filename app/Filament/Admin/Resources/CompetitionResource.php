<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Actions\HistoryTableAction;
use App\Filament\Admin\Resources\CompetitionResource\Pages;
use App\Filament\Admin\Resources\CompetitionResource\RelationManagers;
use App\Models\Competition;
use App\Models\Division;
use App\Models\Enrolment;
use App\Models\EnrolmentEvent;
use App\Models\JudgeScore;
use App\Models\Result;
use App\Services\DivisionAssignmentService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CompetitionResource extends Resource
{
    protected static ?string $model = Competition::class;
    protected static ?string $navigationIcon = 'heroicon-o-trophy';
    protected static ?string $navigationGroup = 'Competitions';
    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole(['competition_administrator', 'system_admin']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->hasRole(['competition_administrator', 'system_admin']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Details')
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
                ]),

            Section::make('Status')
                ->schema([
                    Select::make('status')
                        ->options([
                            'draft'    => 'Draft',
                            'open'     => 'Open for enrolment',
                            'closed'   => 'Closed',
                            'check_in' => 'Check-in',
                            'running'  => 'Running',
                            'complete' => 'Complete',
                        ])
                        ->required()
                        ->default('draft'),
                ]),

            Section::make('Fees')
                ->columns(3)
                ->schema([
                    TextInput::make('fee_first_event')
                        ->label('First event fee ($)')
                        ->numeric()
                        ->required()
                        ->default(38.00)
                        ->prefix('$'),

                    TextInput::make('fee_additional_event')
                        ->label('Additional event fee ($)')
                        ->numeric()
                        ->required()
                        ->default(12.00)
                        ->prefix('$'),

                    TextInput::make('late_surcharge')
                        ->label('Late surcharge ($)')
                        ->numeric()
                        ->required()
                        ->default(15.00)
                        ->prefix('$'),
                ]),

            Section::make('Locations')
                ->description('Define the mats / areas for this competition. These appear as columns in the scheduling board.')
                ->schema([
                    Repeater::make('locations')
                        ->label(false)
                        ->simple(
                            TextInput::make('location')
                                ->placeholder('e.g. Mat 1')
                                ->required()
                                ->maxLength(50)
                        )
                        ->addActionLabel('Add location')
                        ->reorderableWithButtons()
                        ->reorderableWithDragAndDrop(false),
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
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'draft'    => 'Draft',
                        'open'     => 'Open',
                        'closed'   => 'Closed',
                        'check_in' => 'Check-in',
                        'running'  => 'Running',
                        'complete' => 'Complete',
                        default    => ucfirst($state),
                    })
                    ->color(fn (string $state) => match ($state) {
                        'draft'    => 'gray',
                        'open'     => 'success',
                        'closed'   => 'gray',
                        'check_in' => 'warning',
                        'running'  => 'info',
                        'complete' => 'primary',
                        default    => 'gray',
                    }),

                TextColumn::make('enrolments_count')
                    ->label('Enrolments')
                    ->counts('enrolments')
                    ->sortable(),

            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft'    => 'Draft',
                        'open'     => 'Open',
                        'closed'   => 'Closed',
                        'check_in' => 'Check-in',
                        'running'  => 'Running',
                        'complete' => 'Complete',
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
                    Action::make('enrolments')
                        ->label('Enrolments')
                        ->icon('heroicon-o-clipboard-document-list')
                        ->color('gray')
                        ->url(fn (Competition $record) => route('filament.admin.resources.enrolments.index') . '?competition_id=' . $record->id),
                    Action::make('advance')
                        ->label(fn (Competition $record) => match ($record->status) {
                            'draft'    => 'Open Enrolments',
                            'open'     => 'Close Enrolments',
                            'closed'   => 'Begin Check-ins',
                            'check_in' => 'Start Competition',
                            'running'  => 'Conclude Competition',
                            default    => 'Advance',
                        })
                        ->icon(fn (Competition $record) => match ($record->status) {
                            'draft'    => 'heroicon-o-lock-open',
                            'open'     => 'heroicon-o-lock-closed',
                            'closed'   => 'heroicon-o-clipboard-document-check',
                            'check_in' => 'heroicon-o-play',
                            'running'  => 'heroicon-o-flag',
                            default    => 'heroicon-o-arrow-right',
                        })
                        ->color(fn (Competition $record) => match ($record->status) {
                            'draft'    => 'success',
                            'open'     => 'warning',
                            'closed'   => 'primary',
                            'check_in' => 'info',
                            'running'  => 'danger',
                            default    => 'gray',
                        })
                        ->requiresConfirmation(fn (Competition $record) =>
                            $record->status !== 'draft' ||
                            $record->allDivisions()
                                ->whereNull('divisions.location_label')
                                ->whereNotIn('divisions.status', ['combined'])
                                ->count() > 0
                        )
                        ->modalDescription(function (Competition $record) {
                            return match ($record->status) {
                                'draft' => (function () use ($record) {
                                    $unscheduled = $record->allDivisions()
                                        ->whereNull('divisions.location_label')
                                        ->whereNotIn('divisions.status', ['combined'])
                                        ->count();
                                    return "{$unscheduled} division(s) have not been assigned to a location. Open for enrolment anyway?";
                                })(),
                                'open'     => 'Close enrolments for this competition?',
                                'closed'   => 'This will begin the check-in phase. Scoring will not be active until the competition starts.',
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
                        ->visible(fn (Competition $record) => $record->status !== 'complete')
                        ->action(fn (Competition $record) => $record->update(['status' => match ($record->status) {
                            'draft'    => 'open',
                            'open'     => 'closed',
                            'closed'   => 'check_in',
                            'check_in' => 'running',
                            'running'  => 'complete',
                            default    => $record->status,
                        }])),
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
                                ->helperText('Copies the full event structure. Enrolments are not copied.'),
                        ])
                        ->fillForm(fn (Competition $record): array => [
                            'name'             => $record->name . ' (Copy)',
                            'competition_date' => $record->competition_date?->addYear(),
                        ])
                        ->action(function (Competition $record, array $data, DivisionAssignmentService $svc) {
                            $new = Competition::create([
                                'name'                 => $data['name'],
                                'competition_date'     => $data['competition_date'],
                                'start_time'           => $record->start_time,
                                'checkin_time'         => $record->checkin_time,
                                'location_name'        => $record->location_name,
                                'location_address'     => $record->location_address,
                                'enrolment_due_date'   => null,
                                'fee_first_event'      => $record->fee_first_event,
                                'fee_additional_event' => $record->fee_additional_event,
                                'late_surcharge'       => $record->late_surcharge,
                                'status'               => 'draft',
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
                        ->visible(fn () => auth()->user()?->hasRole('system_admin'))
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
                                  . "<strong>Warning:</strong> This competition has <strong>{$enrolmentCount} enrolment(s)</strong>"
                                  . ($resultCount > 0 ? " and <strong>{$resultCount} result record(s)</strong>" : '')
                                  . ". If fees have been collected, you should reconcile payments before deleting."
                                  . "</p>"
                                : '';

                            return new \Illuminate\Support\HtmlString(
                                "<p>Permanently deleting <strong>{$record->name}</strong> will destroy:</p>"
                                . "<ul style='margin-top:.5rem;padding-left:1.25rem;list-style:disc'>"
                                . "<li><strong>{$enrolmentCount}</strong> enrolment(s) and all enrolment event records</li>"
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
            'config'   => Pages\ManageCompetitionConfig::route('/{record}/config'),
            'events'   => Pages\ManageCompetitionEvents::route('/{record}/events'),
            'schedule' => Pages\ManageCompetitionSchedule::route('/{record}/schedule'),
        ];
    }
}
