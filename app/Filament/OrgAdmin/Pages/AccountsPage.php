<?php

namespace App\Filament\OrgAdmin\Pages;

use App\Models\EnrolmentCart;
use App\Models\Refund;
use App\Models\User;
use App\Notifications\AccountStatementNotification;
use App\Notifications\Notification;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\RefundIssuedNotification;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class AccountsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | \BackedEnum | null $navigationIcon  = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Accounts';
    protected static string | \UnitEnum | null $navigationGroup = 'Finance';
    protected static ?int    $navigationSort  = 5;
    protected string $view            = 'filament.org-admin.pages.accounts';

    public static function canAccess(): bool
    {
        $tenant = app('tenant');
        if (! $tenant) return false;
        $user = auth()->user();
        if ($user?->isOrgAdmin($tenant)) return true;
        return $user?->getActiveOfficialRoleFor($tenant)?->can_access_accounts ?? false;
    }

    // ── Org-wide summary totals ──────────────────────────────────────────────

    public function getTotals(): array
    {
        $tenantId = app('tenant')?->id;
        $orgFee   = (float) (app('tenant')?->platform_fee ?? 0);

        $stats = \DB::table('enrolment_carts as ec')
            ->join('competitions as c', 'c.id', '=', 'ec.competition_id')
            ->where('c.organisation_id', $tenantId)
            ->whereNotIn('ec.status', ['draft'])
            ->selectRaw("
                SUM(CASE WHEN ec.payment_status = 'received'
                    THEN ec.total_amount ELSE 0 END) AS total_paid,
                SUM(CASE WHEN ec.payment_status != 'received'
                    THEN COALESCE(ec.total_amount, 0) ELSE 0 END) AS outstanding
            ")
            ->first();

        $pendingRefunds = \DB::table('refunds as r')
            ->join('enrolment_carts as ec', 'ec.id', '=', 'r.cart_id')
            ->join('competitions as c', 'c.id', '=', 'ec.competition_id')
            ->where('c.organisation_id', $tenantId)
            ->where('r.status', 'pending')
            ->sum('r.amount');

        return [
            'totalPaid'      => (float) ($stats->total_paid ?? 0),
            'outstanding'    => (float) ($stats->outstanding ?? 0),
            'pendingRefunds' => (float) $pendingRefunds,
        ];
    }

    // ── Per-user helpers ─────────────────────────────────────────────────────

    private array $userCartsCache = [];
    private array $detailedCartsCache = [];

    // Lightweight — only what the table financial columns need (no events/competitors/divisions).
    private function userCarts(?User $user): \Illuminate\Support\Collection
    {
        if (! $user) return collect();

        if (array_key_exists($user->id, $this->userCartsCache)) {
            return $this->userCartsCache[$user->id];
        }

        if (! $user->relationLoaded('enrolmentCarts')) {
            $tenantId = app('tenant')?->id;
            $user->load([
                'enrolmentCarts' => fn ($q) =>
                    $q->whereNotIn('status', ['draft'])
                      ->whereHas('competition', fn ($q2) => $q2->where('organisation_id', $tenantId))
                      ->with([
                          'enrolments' => fn ($q2) => $q2
                              ->withTrashed()
                              ->whereNotIn('status', ['draft']),
                          'refunds',
                      ]),
            ]);
        }

        return $this->userCartsCache[$user->id] = $user->enrolmentCarts
            ->sortByDesc(fn ($c) => $c->competition?->competition_date);
    }

    // Full load — used when the slide-over opens for one user.
    private function userCartsDetailed(User $user): \Illuminate\Support\Collection
    {
        if (array_key_exists($user->id, $this->detailedCartsCache)) {
            return $this->detailedCartsCache[$user->id];
        }

        $tenantId = app('tenant')?->id;
        $user->load([
            'enrolmentCarts' => fn ($q) =>
                $q->whereNotIn('status', ['draft'])
                  ->whereHas('competition', fn ($q2) => $q2->where('organisation_id', $tenantId))
                  ->with([
                      'competition',
                      'enrolments' => fn ($q2) => $q2
                          ->withTrashed()
                          ->whereNotIn('status', ['draft'])
                          ->with(['competitor', 'activeEvents.competitionEvent', 'activeEvents.division',
                                  'enrolmentEvents.competitionEvent', 'enrolmentEvents.division']),
                      'acceptedBy',
                      'refunds.enrolment' => fn ($q2) => $q2->withTrashed()->with('competitor'),
                      'refunds.issuedBy',
                  ]),
        ]);

        $sorted = $user->enrolmentCarts->sortByDesc(fn ($c) => $c->competition?->competition_date);
        $this->userCartsCache[$user->id] = $sorted;
        return $this->detailedCartsCache[$user->id] = $sorted;
    }

    private function outstandingForUser(User $user): float
    {
        $orgFee = (float) (app('tenant')?->platform_fee ?? 0);
        return (float) $this->userCarts($user)
            ->filter(fn ($c) => ! $c->isPaid())
            ->sum(fn ($c) => $c->outstandingAmount((float) ($c->platform_fee_rate ?? $orgFee)));
    }

    private function pendingRefundsForUser(User $user): float
    {
        return (float) $this->userCarts($user)
            ->flatMap(fn ($c) => $c->refunds)
            ->where('status', 'pending')
            ->sum('amount');
    }

    // ── Table ────────────────────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        $tenantId = app('tenant')?->id;

        return $table
            ->query(
                User::query()
                    ->whereNotNull('email')
                    ->where('email', '!=', '')
                    ->whereHas('enrolmentCarts', fn (Builder $q) =>
                        $q->whereNotIn('status', ['draft'])
                          ->whereHas('competition', fn (Builder $q2) => $q2->where('organisation_id', $tenantId))
                    )
                    ->with([
                        'selfProfile',
                        'enrolmentCarts' => fn ($q) =>
                            $q->whereNotIn('status', ['draft'])
                              ->whereHas('competition', fn ($q2) => $q2->where('organisation_id', $tenantId))
                              ->with([
                                  'enrolments' => fn ($q2) => $q2
                                      ->withTrashed()
                                      ->whereNotIn('status', ['draft']),
                                  'refunds',
                              ]),
                    ])
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Account')
                    ->getStateUsing(fn (User $r) => $r->selfProfile?->full_name ?: ($r->email ?: '(Unknown)'))
                    ->description(fn (User $r) => trim(($r->email ?: '') . '  ·  ID: ' . $r->id))
                    ->searchable(query: fn (Builder $query, string $search) =>
                        $query->where('email', 'like', "%{$search}%")
                              ->orWhere('id', 'like', "%{$search}%")
                              ->orWhereHas('selfProfile', fn ($q2) =>
                                  $q2->where(fn ($q3) =>
                                      $q3->where('first_name', 'like', "%{$search}%")
                                         ->orWhere('surname', 'like', "%{$search}%")
                                  )
                              )
                    )
                    ->sortable(query: fn (Builder $query, string $direction) =>
                        $query->reorder()->orderByRaw("COALESCE(NULLIF((SELECT CONCAT(first_name, ' ', surname) FROM competitor_profiles WHERE owner_user_id=users.id AND profile_type='self' LIMIT 1), ' '), email) {$direction}")
                    ),

                TextColumn::make('outstanding')
                    ->label('Outstanding')
                    ->getStateUsing(fn (User $r) => $this->outstandingForUser($r))
                    ->formatStateUsing(fn ($state) => $state > 0 ? tenant_money($state) : '—')
                    ->color(fn (User $r) => $this->outstandingForUser($r) > 0 ? 'warning' : 'gray')
                    ->badge()
                    ->alignEnd()
                    ->visibleFrom('md'),

                TextColumn::make('refund_due')
                    ->label('Refund due')
                    ->getStateUsing(fn (User $r) => $this->pendingRefundsForUser($r))
                    ->formatStateUsing(fn ($state) => $state > 0 ? tenant_money($state) : '—')
                    ->color(fn (User $r) => $this->pendingRefundsForUser($r) > 0 ? 'danger' : 'gray')
                    ->badge()
                    ->alignEnd()
                    ->visibleFrom('md'),

                TextColumn::make('balance')
                    ->label('Balance')
                    ->getStateUsing(function (User $r) {
                        $owed    = $this->outstandingForUser($r);
                        $refund  = $this->pendingRefundsForUser($r);
                        $net     = $owed - $refund;
                        if (abs($net) < 0.01) {
                            $paid = $this->userCarts($r)->filter(fn ($c) => $c->isPaid())->sum(fn ($c) => (float) $c->total_amount);
                            return 'Paid ' . tenant_money($paid);
                        }
                        return ($net > 0 ? 'Owes ' : 'Due ') . tenant_money(abs($net));
                    })
                    ->color(function (User $r) {
                        $net = $this->outstandingForUser($r) - $this->pendingRefundsForUser($r);
                        if (abs($net) < 0.01) return 'success';
                        return $net > 0 ? 'warning' : 'danger';
                    })
                    ->badge()
                    ->alignEnd(),
            ])
            ->filters([
                Filter::make('has_balance')
                    ->label('Non-zero balance only')
                    ->query(fn (Builder $query) =>
                        $query->where(fn (Builder $inner) =>
                            $inner
                                ->whereHas('enrolmentCarts', fn (Builder $q2) =>
                                    $q2->whereNotIn('status', ['draft'])
                                       ->whereHas('competition', fn (Builder $q3) => $q3->where('organisation_id', $tenantId))
                                       ->where('payment_status', '!=', 'received')
                                )
                                ->orWhereHas('enrolmentCarts', fn (Builder $q2) =>
                                    $q2->whereNotIn('status', ['draft'])
                                       ->whereHas('competition', fn (Builder $q3) => $q3->where('organisation_id', $tenantId))
                                       ->whereHas('refunds', fn (Builder $q3) => $q3->where('status', 'pending'))
                                )
                        )
                    ),
            ])
            ->actions([
                Action::make('viewAccount')
                    ->label('View')
                    ->icon('heroicon-o-document-text')
                    ->slideOver()
                    ->modalHeading(fn (User $r) => $r->selfProfile?->full_name ?: ($r->email ?: 'Unknown'))
                    ->modalContent(fn (User $r) => view('filament.org-admin.pages.account-detail', [
                        'user'  => $r,
                        'carts' => $this->userCartsDetailed($r),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->extraModalFooterActions(function (User $record): array {
                        $this->userCartsDetailed($record);
                        $outstanding    = $this->outstandingForUser($record);
                        $pendingRefunds = $this->pendingRefundsForUser($record);
                        $allRefunds     = $this->userCarts($record)->flatMap(fn ($c) => $c->refunds);

                        return [
                            Action::make('acceptPayment')
                                ->label('Accept payment')
                                ->icon('heroicon-o-banknotes')
                                ->color('success')
                                ->form(function () use ($record, $outstanding, $pendingRefunds) {
                                    $net    = $outstanding - $pendingRefunds;
                                    $orgFee = (float) (app('tenant')?->platform_fee ?? 0);

                                    $lines = '<div class="divide-y divide-gray-100 dark:divide-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden text-sm mb-3">';
                                    foreach ($this->userCarts($record) as $cart) {
                                        if ($cart->isPaid()) continue;
                                        $fee    = (float) ($cart->platform_fee_rate ?? $orgFee);
                                        $active = $cart->enrolments->filter(fn ($e) => ! $e->trashed())->whereNotIn('status', ['draft', 'withdrawn']);
                                        foreach ($active as $e) {
                                            $lines .= '<div class="flex justify-between px-4 py-2">'
                                                . '<span class="text-gray-700 dark:text-gray-300">'
                                                . e($e->competitor?->full_name ?? '?') . ' &mdash; ' . e($e->competition?->name ?? '?')
                                                . '</span>'
                                                . '<span class="tabular-nums font-medium">' . tenant_money($e->fee_calculated + $fee) . '</span>'
                                                . '</div>';
                                        }
                                    }
                                    if ($pendingRefunds > 0.01) {
                                        $lines .= '<div class="flex justify-between px-4 py-2 text-danger-600 dark:text-danger-400">'
                                            . '<span>Pending refund (offset)</span>'
                                            . '<span class="tabular-nums">&minus;' . tenant_money($pendingRefunds) . '</span>'
                                            . '</div>';
                                    }
                                    $lines .= '<div class="flex justify-between px-4 py-2 font-semibold bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white">'
                                        . '<span>Net to collect</span>'
                                        . '<span class="tabular-nums">' . tenant_money(max(0, $net)) . '</span>'
                                        . '</div>';
                                    $lines .= '</div>';

                                    return [
                                        Placeholder::make('summary')
                                            ->label('Outstanding')
                                            ->content(new HtmlString($lines)),

                                        Select::make('payment_method')
                                            ->label('Payment method')
                                            ->options(collect(app('tenant')?->supported_payment_methods ?? ['cash'])
                                                ->mapWithKeys(fn ($m) => [$m => ucfirst($m)]))
                                            ->default('cash')
                                            ->required(),
                                    ];
                                })
                                ->action(function (array $data) use ($record) {
                                    $orgFee = (float) (app('tenant')?->platform_fee ?? 0);
                                    $carts  = $this->userCarts($record);
                                    $paid   = collect();

                                    foreach ($carts as $cart) {
                                        if ($cart->isPaid()) continue;
                                        $fee    = (float) ($cart->platform_fee_rate ?? $orgFee);
                                        $amount = $cart->outstandingAmount($fee);
                                        $cart->forceFill([
                                            'payment_status'      => 'received',
                                            'payment_amount'      => $amount,
                                            'payment_received_at' => now(),
                                            'payment_method'      => $data['payment_method'],
                                        ])->save();
                                        $paid->push($cart);
                                    }

                                    if ($paid->isNotEmpty() && $record->email) {
                                        $record->notify(new PaymentReceivedNotification($paid, $data['payment_method']));
                                    }

                                    Notification::make()->title('Payment recorded.')->success()->send();
                                })
                                ->visible($outstanding > 0.01),

                            Action::make('resolveRefund')
                                ->label($pendingRefunds > 0.01 ? 'Resolve refund' : 'Refund history')
                                ->icon('heroicon-o-arrow-uturn-left')
                                ->color($pendingRefunds > 0.01 ? 'danger' : 'gray')
                                ->modalHeading('Refunds')
                                ->modalSubmitActionLabel('Mark as issued & notify')
                                ->modalSubmitAction(fn (\Filament\Actions\StaticAction $action) =>
                                    $pendingRefunds > 0.01 ? $action : $action->hidden()
                                )
                                ->form(function () use ($record) {
                                    $allRefunds = $this->userCarts($record)->flatMap(fn ($c) => $c->refunds);
                                    $pending    = $allRefunds->where('status', 'pending');
                                    $issued     = $allRefunds->where('status', 'issued');

                                    $html = '<div class="space-y-4">';

                                    if ($pending->isNotEmpty()) {
                                        $html .= '<div>'
                                            . '<p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Pending</p>'
                                            . '<div class="divide-y divide-gray-100 dark:divide-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">';
                                        foreach ($pending as $refund) {
                                            $name   = e($refund->enrolment?->competitor?->full_name ?? 'Unknown');
                                            $method = ucfirst($refund->payment_method ?? 'cash');
                                            $comp   = e($refund->cart?->competition?->name ?? '?');
                                            $html .= '<div class="flex items-start justify-between gap-4 px-4 py-3">'
                                                . '<div class="min-w-0">'
                                                . '<p class="text-sm font-semibold text-gray-900 dark:text-white">' . $name . '</p>'
                                                . '<p class="text-xs text-gray-400 mt-0.5">' . $comp . ' &mdash; ' . e($refund->reason) . '</p>'
                                                . '<p class="text-xs text-gray-400 mt-0.5">via ' . e($method) . '</p>'
                                                . '</div>'
                                                . '<span class="text-sm font-semibold text-danger-600 dark:text-danger-400 tabular-nums flex-shrink-0">'
                                                . '&minus;' . tenant_money($refund->amount) . '</span>'
                                                . '</div>';
                                        }
                                        $total = $pending->sum('amount');
                                        $html .= '</div>'
                                            . '<div class="flex justify-between text-sm font-semibold pt-2 mt-2 border-t border-gray-200 dark:border-gray-700">'
                                            . '<span class="text-gray-700 dark:text-gray-300">Total pending</span>'
                                            . '<span class="tabular-nums text-danger-600 dark:text-danger-400">&minus;' . tenant_money($total) . '</span>'
                                            . '</div>'
                                            . '</div>';
                                    }

                                    if ($issued->isNotEmpty()) {
                                        $html .= '<div>'
                                            . '<p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Completed</p>'
                                            . '<div class="divide-y divide-gray-100 dark:divide-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">';
                                        foreach ($issued as $refund) {
                                            $name     = e($refund->enrolment?->competitor?->full_name ?? 'Unknown');
                                            $comp     = e($refund->cart?->competition?->name ?? '?');
                                            $issuedAt = $refund->issued_at ? tenant_date($refund->issued_at) : '—';
                                            $issuedBy = $refund->issuedBy?->name ? ' by ' . e($refund->issuedBy->name) : '';
                                            $html .= '<div class="flex items-start justify-between gap-4 px-4 py-3 opacity-70">'
                                                . '<div class="min-w-0">'
                                                . '<p class="text-sm font-medium text-gray-700 dark:text-gray-300">' . $name . '</p>'
                                                . '<p class="text-xs text-gray-400 mt-0.5">' . $comp . ' &mdash; ' . e($refund->reason) . '</p>'
                                                . '<p class="text-xs text-gray-400 mt-0.5">Issued ' . $issuedAt . $issuedBy . '</p>'
                                                . '</div>'
                                                . '<span class="text-sm font-medium text-success-600 dark:text-success-400 tabular-nums flex-shrink-0">'
                                                . '&minus;' . tenant_money($refund->amount) . '</span>'
                                                . '</div>';
                                        }
                                        $html .= '</div></div>';
                                    }

                                    if ($allRefunds->isEmpty()) {
                                        $html .= '<p class="text-sm text-gray-400 text-center py-4">No refunds on record.</p>';
                                    }

                                    $html .= '</div>';

                                    return [
                                        Placeholder::make('refund_summary')
                                            ->label('')
                                            ->content(new HtmlString($html)),
                                    ];
                                })
                                ->action(function () use ($record) {
                                    $carts = $this->userCarts($record);
                                    foreach ($carts as $cart) {
                                        $pending = $cart->refunds->where('status', 'pending');
                                        if ($pending->isEmpty()) continue;

                                        $ids = $pending->pluck('id');
                                        Refund::whereIn('id', $ids)->update([
                                            'status'            => 'issued',
                                            'issued_at'         => now(),
                                            'issued_by_user_id' => auth()->id(),
                                        ]);

                                        if ($cart->user) {
                                            $cart->user->notify(
                                                new RefundIssuedNotification($cart, $pending->fresh()->load('enrolment.competitor'))
                                            );
                                        }
                                    }
                                    Notification::make()->title('Refunds issued.')->success()->send();
                                })
                                ->visible($allRefunds->isNotEmpty()),

                            Action::make('sendStatement')
                                ->label('Send account statement')
                                ->icon('heroicon-o-envelope')
                                ->color('gray')
                                ->requiresConfirmation()
                                ->modalHeading('Send account statement')
                                ->modalDescription('Email a full account summary to ' . ($record->selfProfile?->full_name ?? $record->email) . '.')
                                ->action(function () use ($record) {
                                    $carts       = $this->userCartsDetailed($record);
                                    $outstanding = $this->outstandingForUser($record);
                                    $refundDue   = $this->pendingRefundsForUser($record);
                                    $record->notify(new AccountStatementNotification($carts, $outstanding, $refundDue));
                                    Notification::make()->title('Statement sent.')->success()->send();
                                }),
                        ];
                    }),
            ])
            ->defaultSort('name', 'asc')
            ->emptyStateHeading('No accounts found')
            ->emptyStateDescription('Accounts appear here once competitors have registered.')
            ->defaultPaginationPageOption(25);
    }

    public function getTitle(): string
    {
        return 'Accounts';
    }
}
