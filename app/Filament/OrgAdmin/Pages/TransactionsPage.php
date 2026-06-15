<?php

namespace App\Filament\OrgAdmin\Pages;

use App\Models\Competition;
use App\Models\EnrolmentCart;
use App\Models\Refund;
use App\Notifications\Notification;
use App\Notifications\RefundIssuedNotification;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\HtmlString;
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
    protected static ?string $navigationGroup = null;
    protected static bool    $shouldRegisterNavigation = false;
    protected static string  $view            = 'filament.org-admin.pages.transactions';

    public static function canAccess(): bool
    {
        $tenant = app('tenant');
        return $tenant && (auth()->user()?->isOrgAdmin($tenant) ?? false);
    }

    public function getTotals(): array
    {
        $enrolments = \App\Models\Enrolment::query()
            ->whereNotIn('status', ['draft'])
            ->whereHas('competition', fn (Builder $q) => $q->where('organisation_id', app('tenant')?->id))
            ->with('cart')
            ->get();

        $orgPlatformFee = (float) (app('tenant')?->platform_fee ?? 0);
        $active         = $enrolments->whereNotIn('status', ['withdrawn', 'draft']);
        $carts          = $enrolments->map(fn ($e) => $e->cart)->filter()->unique('id');
        $totalFees      = $active->sum(fn ($e) => $e->fee_calculated + (float) ($e->cart?->platform_fee_rate ?? $orgPlatformFee));
        $totalPaid      = $carts->where('payment_status', 'received')->sum(fn ($c) => (float) ($c->payment_amount ?? $c->total_amount));
        $outstanding    = $carts->where('payment_status', '!=', 'received')
                                ->sum(fn ($c) => $c->outstandingAmount((float) ($c->platform_fee_rate ?? $orgPlatformFee)));

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
                EnrolmentCart::query()
                    ->whereHas('enrolments', fn (Builder $q) => $q->whereNotIn('status', ['draft']))
                    ->whereHas('competition', fn (Builder $q) => $q->where('organisation_id', app('tenant')?->id))
                    ->with([
                        'competition',
                        'user',
                        'enrolments.competitor',
                        'enrolments.activeEvents.competitionEvent',
                        'enrolments.activeEvents.division',
                        'refunds',
                    ])
            )
            ->defaultSort('submitted_at', 'desc')
            ->columns([
                TextColumn::make('submitted_at')
                    ->label('Date')
                    ->formatStateUsing(fn ($state) => $state ? tenant_date($state) : '—')
                    ->sortable(),

                TextColumn::make('competition.name')
                    ->label('Competition')
                    ->sortable()
                    ->searchable()
                    ->description(fn (EnrolmentCart $r) => $r->competition ? tenant_date($r->competition->competition_date) : null),

                TextColumn::make('user.name')
                    ->label('Registered by')
                    ->description(fn (EnrolmentCart $r) => $r->user?->email)
                    ->sortable()
                    ->searchable(),

                TextColumn::make('competitors')
                    ->label('Competitors')
                    ->getStateUsing(fn (EnrolmentCart $r) => $r->enrolments
                        ->whereNotIn('status', ['draft'])
                        ->map(fn ($e) => $e->competitor?->full_name)
                        ->filter()
                        ->join(', '))
                    ->wrap(),

                TextColumn::make('payment_method')
                    ->label('Method')
                    ->formatStateUsing(fn ($state) => $state ? ucfirst($state) : '—')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('total_amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state) => $state !== null ? tenant_money($state) : '—')
                    ->sortable(),

                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->getStateUsing(function (EnrolmentCart $r) {
                        $active = $r->enrolments->whereNotIn('status', ['withdrawn', 'draft']);
                        if ($active->isEmpty()) return 'n/a';
                        return $r->payment_status === 'received' ? 'paid' : 'outstanding';
                    })
                    ->formatStateUsing(fn (string $state) => match($state) {
                        'paid'        => 'Paid',
                        'partial'     => 'Partial',
                        'outstanding' => 'Outstanding',
                        'n/a'         => 'N/A',
                        default       => ucfirst($state),
                    })
                    ->color(fn (string $state) => match($state) {
                        'paid'    => 'success',
                        'partial' => 'warning',
                        default   => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('competition_id')
                    ->label('Competition')
                    ->options(fn () => Competition::where('organisation_id', app('tenant')?->id)
                        ->orderByDesc('competition_date')
                        ->pluck('name', 'id'))
                    ->searchable(),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('detail')
                        ->label('View detail')
                        ->icon('heroicon-o-document-text')
                        ->slideOver()
                        ->modalHeading(fn (EnrolmentCart $r) => 'Transaction — ' . $r->competition?->name)
                        ->modalContent(fn (EnrolmentCart $r) => view('filament.org-admin.pages.transaction-detail', [
                            'cart' => $r->load([
                                'enrolments.competitor',
                                'enrolments.activeEvents.competitionEvent',
                                'enrolments.activeEvents.division',
                                'enrolments.enrolmentEvents.competitionEvent',
                                'enrolments.enrolmentEvents.division',
                                'refunds.enrolment.competitor',
                                'refunds.issuedBy',
                            ]),
                        ]))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close'),

                    Action::make('markAllPaid')
                        ->label('Mark all paid')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->form(fn (EnrolmentCart $record) => [
                            Select::make('payment_method')
                                ->label('Payment method')
                                ->options(collect(app('tenant')?->supported_payment_methods ?? ['cash'])
                                    ->mapWithKeys(fn ($m) => [$m => ucfirst($m)]))
                                ->default($record->payment_method ?? 'cash')
                                ->required(),
                            TextInput::make('transaction_reference')
                                ->label('Transaction reference')
                                ->placeholder('Bank ref, receipt number…')
                                ->default($record->transaction_reference),
                        ])
                        ->action(function (EnrolmentCart $record, array $data) {
                            $platformFee = (float) ($record->platform_fee_rate ?? app('tenant')?->platform_fee ?? 0);
                            $record->forceFill([
                                'payment_status'        => 'received',
                                'payment_amount'        => $record->outstandingAmount($platformFee),
                                'payment_received_at'   => now(),
                                'payment_method'        => $data['payment_method'],
                                'transaction_reference' => $data['transaction_reference'] ?? null,
                            ])->save();
                            Notification::make()->title('Payment recorded.')->success()->send();
                        })
                        ->visible(fn (EnrolmentCart $r) => ! $r->isPaid() && $r->enrolments->whereNotIn('status', ['withdrawn', 'draft'])->isNotEmpty()),

                    Action::make('markOutstanding')
                        ->label('Mark outstanding')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (EnrolmentCart $record) {
                            $record->forceFill([
                                'payment_status'      => 'outstanding',
                                'payment_amount'      => null,
                                'payment_received_at' => null,
                            ])->save();
                            Notification::make()->title('Payment marked outstanding.')->warning()->send();
                        })
                        ->visible(fn (EnrolmentCart $r) => $r->isPaid()),

                    Action::make('manualRefund')
                        ->label('Manual refund')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('danger')
                        ->form(function (EnrolmentCart $record) {
                            $record->loadMissing([
                                'enrolments.competitor',
                                'enrolments.enrolmentEvents.competitionEvent',
                                'enrolments.enrolmentEvents.division',
                            ]);

                            return [
                                Select::make('enrolment_id')
                                    ->label('Competitor')
                                    ->options($record->enrolments
                                        ->whereNotIn('status', ['draft'])
                                        ->mapWithKeys(fn ($e) => [$e->id => $e->competitor?->full_name ?? 'Unknown'])
                                    )
                                    ->required()
                                    ->live(),

                                Placeholder::make('fees_summary')
                                    ->label('Fees charged')
                                    ->content(function (callable $get) use ($record) {
                                        $enrolmentId = $get('enrolment_id');
                                        if (! $enrolmentId) {
                                            return new HtmlString('<p class="text-xs text-gray-400">Select a competitor to see their fees.</p>');
                                        }
                                        $enrolment = $record->enrolments->firstWhere('id', (int) $enrolmentId);
                                        if (! $enrolment) {
                                            return new HtmlString('<p class="text-xs text-gray-400">—</p>');
                                        }

                                        $platformFee = (float) ($record->platform_fee_rate ?? 0);
                                        $isOfficial  = $enrolment->is_official_discount;
                                        $firstRate   = ($isOfficial && $record->fee_official_first_rate !== null)
                                            ? (float) $record->fee_official_first_rate
                                            : (float) $record->fee_first_rate;
                                        $addRate     = ($isOfficial && $record->fee_official_additional_rate !== null)
                                            ? (float) $record->fee_official_additional_rate
                                            : (float) $record->fee_additional_rate;

                                        $lateSurcharge   = $enrolment->is_late ? (float) ($record->late_surcharge_rate ?? 0) : 0.0;
                                        $refundableTotal = max(0, (float) $enrolment->fee_calculated - $lateSurcharge);
                                        $allEvents       = $enrolment->enrolmentEvents->sortBy('id');
                                        $activeIdx       = 0;

                                        $html = '<div class="rounded-lg border border-gray-200 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-800 text-xs">';
                                        foreach ($allEvents as $ee) {
                                            $isRemoved = (bool) $ee->removed;
                                            $fee       = $activeIdx === 0 ? $firstRate : $addRate;
                                            if (! $isRemoved) $activeIdx++;

                                            $statusBadge = '';
                                            if ($isRemoved) {
                                                $label = ($ee->removal_type ?? '') === 'user_withdrawn' ? 'Withdrawn' : 'Cancelled';
                                                $color = ($ee->removal_type ?? '') === 'user_withdrawn'
                                                    ? 'background:#fef3c7;color:#92400e;'
                                                    : 'background:#fee2e2;color:#991b1b;';
                                                $statusBadge = ' <span style="' . $color . 'border-radius:4px;padding:1px 5px;font-size:10px;font-weight:600;">' . $label . '</span>';
                                            } else {
                                                $statusBadge = ' <span style="background:#d1fae5;color:#065f46;border-radius:4px;padding:1px 5px;font-size:10px;font-weight:600;">Active</span>';
                                            }

                                            $rowStyle = $isRemoved ? 'color:#9ca3af;text-decoration:line-through;' : 'color:#4b5563;';
                                            $html .= '<div class="flex justify-between items-center px-3 py-2" style="' . $rowStyle . '">'
                                                . '<span>' . e($ee->competitionEvent?->name ?? '?')
                                                . ($ee->division ? ' · ' . e($ee->division->label) : '')
                                                . ($isOfficial && ! $isRemoved && $activeIdx === 1 ? ' (official rate)' : '')
                                                . $statusBadge
                                                . '</span>'
                                                . '<span class="tabular-nums">' . tenant_money($fee) . '</span>'
                                                . '</div>';
                                        }
                                        if ($lateSurcharge > 0) {
                                            $html .= '<div class="flex justify-between px-3 py-2 text-gray-400 line-through">'
                                                . '<span>Late surcharge (non-refundable)</span>'
                                                . '<span class="tabular-nums">' . tenant_money($lateSurcharge) . '</span>'
                                                . '</div>';
                                        }
                                        if ($platformFee > 0) {
                                            $html .= '<div class="flex justify-between px-3 py-2 text-gray-400 line-through">'
                                                . '<span>Platform fee (non-refundable)</span>'
                                                . '<span class="tabular-nums">' . tenant_money($platformFee) . '</span>'
                                                . '</div>';
                                        }
                                        $html .= '<div class="flex justify-between px-3 py-2 font-semibold text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-800">'
                                            . '<span>Max refundable</span>'
                                            . '<span class="tabular-nums">' . tenant_money($refundableTotal) . '</span>'
                                            . '</div>';
                                        $html .= '</div>';

                                        return new HtmlString($html);
                                    }),

                                TextInput::make('amount')
                                    ->label('Refund amount')
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->required()
                                    ->prefix(tenant_currency()),

                                Textarea::make('reason')
                                    ->label('Reason')
                                    ->required()
                                    ->rows(2),

                                Select::make('payment_method')
                                    ->label('Payment method')
                                    ->options(collect(app('tenant')?->supported_payment_methods ?? ['cash'])
                                        ->mapWithKeys(fn ($m) => [$m => ucfirst($m)]))
                                    ->default($record->payment_method ?? 'cash')
                                    ->required(),
                            ];
                        })
                        ->action(function (EnrolmentCart $record, array $data) {
                            Refund::create([
                                'organisation_id'    => app('tenant')?->id,
                                'cart_id'            => $record->id,
                                'enrolment_id'       => $data['enrolment_id'],
                                'type'               => 'manual',
                                'amount'             => $data['amount'],
                                'reason'             => $data['reason'],
                                'payment_method'     => $data['payment_method'],
                                'status'             => 'pending',
                                'issued_by_user_id'  => auth()->id(),
                            ]);
                            Notification::make()->title('Manual refund created.')->success()->send();
                        }),

                    Action::make('issueRefunds')
                        ->label('Confirm refunds issued')
                        ->icon('heroicon-o-banknotes')
                        ->color('info')
                        ->modalHeading('Confirm refunds issued')
                        ->modalSubmitActionLabel('Mark as issued & notify')
                        ->form(function (EnrolmentCart $record) {
                            $record->loadMissing(['refunds.enrolment.competitor']);
                            $pending = $record->refunds->where('status', 'pending');

                            $html = '<div class="divide-y divide-gray-100 dark:divide-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">';
                            foreach ($pending as $refund) {
                                $name   = e($refund->enrolment?->competitor?->full_name ?? 'Unknown');
                                $method = ucfirst($refund->payment_method ?? 'cash');
                                $html .= '<div class="flex items-start justify-between gap-4 px-4 py-3">'
                                    . '<div class="min-w-0">'
                                    . '<p class="text-sm font-semibold text-gray-900 dark:text-white">' . $name . '</p>'
                                    . '<p class="text-xs text-gray-500 mt-0.5">' . e($refund->reason) . '</p>'
                                    . '<p class="text-xs text-gray-400 mt-0.5">via ' . e($method) . '</p>'
                                    . '</div>'
                                    . '<span class="text-sm font-semibold text-danger-600 dark:text-danger-400 tabular-nums flex-shrink-0">'
                                    . '&minus;' . tenant_money($refund->amount)
                                    . '</span>'
                                    . '</div>';
                            }
                            $html .= '</div>';

                            $total = $pending->sum('amount');
                            $html .= '<div class="flex justify-between text-sm font-semibold pt-3 border-t border-gray-200 dark:border-gray-700 mt-3">'
                                . '<span class="text-gray-700 dark:text-gray-300">Total</span>'
                                . '<span class="tabular-nums text-danger-600 dark:text-danger-400">&minus;' . tenant_money($total) . '</span>'
                                . '</div>';
                            $html .= '<p class="text-xs text-gray-400 mt-3">Confirming will mark these as issued and email the competitor.</p>';

                            return [
                                Placeholder::make('refund_summary')
                                    ->label('Pending refunds')
                                    ->content(new HtmlString($html)),
                            ];
                        })
                        ->action(function (EnrolmentCart $record) {
                            $pending = $record->refunds()->where('status', 'pending')->get();
                            $pending->each->update([
                                'status'            => 'issued',
                                'issued_at'         => now(),
                                'issued_by_user_id' => auth()->id(),
                            ]);

                            if ($pending->isNotEmpty() && $record->user) {
                                $record->user->notify(new RefundIssuedNotification($record, $pending->fresh()->load('enrolment.competitor')));
                            }

                            Notification::make()->title('Refunds marked as issued.')->success()->send();
                        })
                        ->visible(fn (EnrolmentCart $r) => $r->refunds->where('status', 'pending')->isNotEmpty()),
                ])->dropdownPlacement('bottom-start'),
            ])
            ->emptyStateHeading('No registrations found')
            ->emptyStateDescription('Registrations will appear here once competitors have enrolled.')
            ->defaultPaginationPageOption(25);
    }

    public function getTitle(): string
    {
        return 'Transactions';
    }
}
