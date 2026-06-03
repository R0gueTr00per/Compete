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

    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn ($q) => $q->with('competition'))
            ->columns([
                TextColumn::make('competition.name')
                    ->label('Competition')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('enrolled_at')
                    ->label('Registered')
                    ->date(tenant_date_format())
                    ->sortable(),

                TextColumn::make('fee_calculated')
                    ->label('Fee')
                    ->money(tenant_currency())
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
                    ->label('View registrations')
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
