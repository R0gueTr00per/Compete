<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Actions\HistoryTableAction;
use App\Filament\Admin\Resources\CompetitionResource\Pages;
use App\Filament\Admin\Resources\CompetitionResource\RelationManagers;
use App\Models\Competition;
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
        return auth()->user()?->hasRole(['admin', 'system_admin']);
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->hasRole(['admin', 'system_admin']);
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

                    TimePicker::make('start_time')
                        ->required()
                        ->seconds(false),

                    TimePicker::make('checkin_time')
                        ->seconds(false)
                        ->nullable(),

                    DatePicker::make('enrolment_due_date')
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
                            'draft' => 'Draft',
                            'open' => 'Open for enrolment',
                            'closed' => 'Closed',
                            'running' => 'Running',
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
                    ->color(fn (string $state) => match ($state) {
                        'draft' => 'gray',
                        'open' => 'success',
                        'closed' => 'warning',
                        'running' => 'info',
                        'complete' => 'primary',
                    }),

                TextColumn::make('enrolments_count')
                    ->label('Enrolments')
                    ->counts('enrolments')
                    ->sortable(),

                TextColumn::make('enrolment_due_date')
                    ->label('Due date')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'open' => 'Open',
                        'closed' => 'Closed',
                        'running' => 'Running',
                        'complete' => 'Complete',
                    ]),
            ])
            ->actions([
                EditAction::make(),
                Action::make('config')
                    ->label('Configuration')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->color('gray')
                    ->url(fn (Competition $record) => static::getUrl('config', ['record' => $record])),
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
                ActionGroup::make([
                    Action::make('open')
                        ->label('Open enrolment')
                        ->icon('heroicon-o-lock-open')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalDescription(function (Competition $record) {
                            $unscheduled = $record->allDivisions()
                                ->whereNull('divisions.location_label')
                                ->where('divisions.status', '!=', 'cancelled')
                                ->count();
                            if ($unscheduled > 0) {
                                return "{$unscheduled} division(s) have not been assigned to a location. Are you sure you want to open for enrolment anyway?";
                            }
                            return 'Are you sure you want to open this competition for enrolment?';
                        })
                        ->visible(fn (Competition $record) => $record->status === 'draft')
                        ->action(fn (Competition $record) => $record->update(['status' => 'open'])),
                    Action::make('close')
                        ->label('Close enrolment')
                        ->icon('heroicon-o-lock-closed')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (Competition $record) => $record->status === 'open')
                        ->action(fn (Competition $record) => $record->update(['status' => 'closed'])),
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
