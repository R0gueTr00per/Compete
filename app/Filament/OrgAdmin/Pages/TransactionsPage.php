<?php

namespace App\Filament\OrgAdmin\Pages;

use App\Models\Competition;
use App\Models\Enrolment;
use App\Notifications\Notification;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransactionsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon  = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Transactions';
    protected static ?string $navigationGroup = 'Registrations';
    protected static ?int    $navigationSort  = 5;
    protected static string  $view            = 'filament.org-admin.pages.transactions';

    public static function canAccess(): bool
    {
        $tenant = app('tenant');
        return $tenant && (auth()->user()?->isOrgAdmin($tenant) ?? false);
    }

    public function getTotals(): array
    {
        $base = Enrolment::query()
            ->whereNotIn('status', ['draft'])
            ->whereHas('competition', fn (Builder $q) => $q->where('organisation_id', app('tenant')?->id));

        $totalFees   = (clone $base)->sum('fee_calculated');
        $totalPaid   = (clone $base)->where('payment_status', 'received')->sum('payment_amount');
        $outstanding = (clone $base)->where('payment_status', 'outstanding')
            ->whereNotIn('status', ['withdrawn'])
            ->sum('fee_calculated');

        return [
            'total_fees'  => (float) $totalFees,
            'total_paid'  => (float) $totalPaid,
            'outstanding' => (float) $outstanding,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Enrolment::query()
                    ->whereNotIn('status', ['draft'])
                    ->whereHas('competition', fn (Builder $q) => $q->where('organisation_id', app('tenant')?->id))
                    ->with(['competitor', 'competition'])
            )
            ->defaultSort('payment_received_at', 'desc')
            ->columns([
                TextColumn::make('competitor.full_name')
                    ->label('Competitor')
                    ->searchable(['first_name', 'surname'])
                    ->sortable(query: fn ($q, $d) => $q->join('competitor_profiles', 'enrolments.competitor_profile_id', '=', 'competitor_profiles.id')->orderBy('competitor_profiles.surname', $d)),

                TextColumn::make('competition.name')
                    ->label('Competition')
                    ->sortable()
                    ->searchable()
                    ->description(fn (Enrolment $r) => $r->competition ? tenant_date($r->competition->competition_date) : null)
                    ->visibleFrom('sm'),

                TextColumn::make('fee_calculated')
                    ->label('Fee')
                    ->sortable()
                    ->html()
                    ->formatStateUsing(function ($state, Enrolment $r) {
                        $amount = tenant_money($state);
                        $tag    = match (true) {
                            $r->is_late && $r->is_official_discount => 'late + official',
                            $r->is_late                             => 'late',
                            $r->is_official_discount                => 'official',
                            default                                 => null,
                        };
                        if (! $tag) {
                            return e($amount);
                        }
                        return e($amount) . ' <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium bg-warning-100 text-warning-700 dark:bg-warning-900/40 dark:text-warning-300">' . e($tag) . '</span>';
                    }),

                TextColumn::make('payment_amount')
                    ->label('Paid')
                    ->formatStateUsing(fn ($state) => $state ? tenant_money($state) : '—')
                    ->sortable()
                    ->visibleFrom('sm'),

                TextColumn::make('amount_owing')
                    ->label('Owing')
                    ->getStateUsing(fn (Enrolment $record) => $record->payment_status === 'received' ? null : $record->fee_calculated)
                    ->formatStateUsing(fn ($state) => $state ? tenant_money($state) : '—')
                    ->color(fn ($state) => $state ? 'warning' : null)
                    ->sortable(false)
                    ->visibleFrom('sm'),

                TextColumn::make('payment_received_at')
                    ->label('Date Paid')
                    ->formatStateUsing(fn ($state) => $state ? tenant_date($state) : '—')
                    ->sortable(),

                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'received'    => 'Paid',
                        'outstanding' => 'Outstanding',
                        default       => ucfirst($state),
                    })
                    ->color(fn (string $state) => match ($state) {
                        'received'    => 'success',
                        'outstanding' => 'warning',
                        default       => 'gray',
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'pending'    => 'Pending',
                        'confirmed'  => 'Confirmed',
                        'checked_in' => 'Checked In',
                        'withdrawn'  => 'Withdrawn',
                        default      => ucfirst($state),
                    })
                    ->color(fn (string $state) => match ($state) {
                        'pending'    => 'warning',
                        'confirmed'  => 'success',
                        'checked_in' => 'info',
                        'withdrawn'  => 'danger',
                        default      => 'gray',
                    })
                    ->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('competition_id')
                    ->label('Competition')
                    ->options(fn () => Competition::where('organisation_id', app('tenant')?->id)
                        ->orderByDesc('competition_date')
                        ->pluck('name', 'id'))
                    ->searchable(),

                SelectFilter::make('payment_status')
                    ->label('Payment')
                    ->options([
                        'outstanding' => 'Outstanding',
                        'received'    => 'Paid',
                    ]),

                SelectFilter::make('status')
                    ->options([
                        'pending'    => 'Pending',
                        'confirmed'  => 'Confirmed',
                        'checked_in' => 'Checked in',
                        'withdrawn'  => 'Withdrawn',
                    ]),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('recordPayment')
                        ->label('Record payment')
                        ->icon('heroicon-o-credit-card')
                        ->visible(fn (Enrolment $r) => $r->payment_status !== 'received' && $r->status !== 'withdrawn')
                        ->form([
                            TextInput::make('amount')
                                ->label('Amount received')
                                ->numeric()
                                ->prefix('$')
                                ->required()
                                ->default(fn (Enrolment $r) => number_format((float) $r->fee_calculated, 2)),
                        ])
                        ->action(function (Enrolment $record, array $data): void {
                            $record->forceFill([
                                'payment_status'      => 'received',
                                'payment_amount'      => $data['amount'],
                                'payment_received_at' => now(),
                            ])->save();
                            Notification::make()->title('Payment recorded.')->success()->send();
                        }),

                    Action::make('clearPayment')
                        ->label('Clear payment')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (Enrolment $r) => $r->payment_status === 'received')
                        ->requiresConfirmation()
                        ->action(function (Enrolment $record): void {
                            $record->forceFill([
                                'payment_status'      => 'outstanding',
                                'payment_amount'      => null,
                                'payment_received_at' => null,
                            ])->save();
                            Notification::make()->title('Payment cleared.')->warning()->send();
                        }),
                ]),
            ])
            ->defaultPaginationPageOption(25);
    }

    public function getTitle(): string
    {
        return 'Transactions';
    }
}
