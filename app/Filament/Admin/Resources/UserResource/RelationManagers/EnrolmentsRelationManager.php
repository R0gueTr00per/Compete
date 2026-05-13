<?php

namespace App\Filament\Admin\Resources\UserResource\RelationManagers;

use App\Filament\Admin\Resources\EnrolmentResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EnrolmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrolments';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('competition.name')
                    ->label('Competition')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('enrolled_at')
                    ->label('Enrolled')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('fee_calculated')
                    ->label('Fee')
                    ->money('AUD')
                    ->placeholder('—'),

                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'paid'    => 'success',
                        'partial' => 'warning',
                        'unpaid'  => 'danger',
                        default   => 'gray',
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'checked_in' => 'success',
                        'enrolled'   => 'info',
                        'withdrawn'  => 'danger',
                        default      => 'gray',
                    }),
            ])
            ->defaultSort('enrolled_at', 'desc')
            ->actions([
                Action::make('view')
                    ->label('View enrolments')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn ($record) => EnrolmentResource::getUrl('index', [
                        'tableFilters[competition][value]' => $record->competition_id,
                    ]))
                    ->openUrlInNewTab(),
            ])
            ->headerActions([])
            ->bulkActions([]);
    }
}
