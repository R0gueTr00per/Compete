<?php

namespace App\Filament\Admin\Widgets;

use App\Models\OrganisationAnnualFeeReminder;
use App\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class AnnualFeeRemindersWidget extends BaseWidget
{
    protected static ?string $heading = 'Annual fee reminders';

    public function table(Table $table): Table
    {
        return $table
            ->query(OrganisationAnnualFeeReminder::active()->with('organisation'))
            ->defaultSort('due_date')
            ->columns([
                TextColumn::make('organisation.name')
                    ->label('Organisation')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('due_date')
                    ->label('Due date')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn (OrganisationAnnualFeeReminder $r) => ($r->organisation?->currency ?: 'AUD') . ' ' . number_format((float) $r->amount, 2)),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn () => 'Reminder')
                    ->color('warning'),
            ])
            ->actions([
                Action::make('dismiss')
                    ->label('Received')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (OrganisationAnnualFeeReminder $record) {
                        $record->dismiss();
                        Notification::make()->title('Reminder dismissed')->success()->send();
                    }),
            ])
            ->emptyStateHeading('No annual fee reminders right now');
    }
}
