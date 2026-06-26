<?php

namespace App\Filament\OrgAdmin\Pages;

use App\Models\Competition;
use App\Models\EnrolmentCart;
use App\Models\Refund;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionStatementPage extends Page
{
    protected static string | \BackedEnum | null $navigationIcon  = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationLabel = 'Statement';
    protected static string | \UnitEnum | null $navigationGroup = 'Finance';
    protected static ?int    $navigationSort  = 6;
    protected string $view            = 'filament.org-admin.pages.transaction-statement';

    public ?int    $competitionId = null;
    public ?string $dateFrom      = null;
    public ?string $dateTo        = null;
    public array   $types         = ['invoice', 'payment', 'refund'];
    public ?string $search        = null;

    private ?Collection $cachedEntries = null;

    public static function canAccess(): bool
    {
        $tenant = app('tenant');
        return $tenant && (auth()->user()?->isOrgAdmin($tenant) ?? false);
    }

    public function getTitle(): string
    {
        return 'Transaction Statement';
    }

    public function getCompetitions(): array
    {
        return Competition::where('organisation_id', app('tenant')?->id)
            ->where('is_template', false)
            ->orderByDesc('competition_date')
            ->pluck('name', 'id')
            ->toArray();
    }

    public function buildEntries(): Collection
    {
        if ($this->cachedEntries !== null) {
            return $this->cachedEntries;
        }

        $tenantId = app('tenant')?->id;

        $cartQuery = EnrolmentCart::query()
            ->whereHas('enrolments', fn (Builder $q) => $q->whereNotIn('status', ['draft']))
            ->whereHas('competition', fn (Builder $q) => $q->where('organisation_id', $tenantId))
            ->with(['competition', 'user.selfProfile', 'enrolments.competitor']);

        if ($this->competitionId) {
            $cartQuery->where('competition_id', $this->competitionId);
        }

        $carts   = $cartQuery->get();
        $entries = collect();

        foreach ($carts as $cart) {
            $competitorNames = $cart->enrolments
                ->whereNotIn('status', ['draft'])
                ->map(fn ($e) => $e->competitor?->full_name)
                ->filter()
                ->values();
            $competitors = $competitorNames->join(', ');

            $userRef      = $cart->user?->selfProfile?->full_name ?? $cart->user?->email ?? '—';
            $cartRef      = ($cart->competition?->name ?? '?') . ' #' . $cart->id . ' · User #' . $cart->user_id;
            $userInList   = $competitorNames->contains($userRef);

            // Invoice event (negative — creates obligation)
            if ($cart->submitted_at && in_array('invoice', $this->types)) {
                if ($this->inDateRange($cart->submitted_at)) {
                    $desc = $userInList
                        ? ($competitors ?: '—')
                        : $userRef . ($competitors ? ' · ' . $competitors : '');
                    $entries->push([
                        'date'        => $cart->submitted_at,
                        'type'        => 'invoice',
                        'reference'   => $cartRef,
                        'description' => $desc,
                        'amount'      => -(float) $cart->total_amount,
                        'sort_key'    => $cart->submitted_at->timestamp . '_a_' . $cart->id,
                    ]);
                }
            }

            // Payment event (positive — cash received)
            if ($cart->isPaid() && $cart->payment_received_at && in_array('payment', $this->types)) {
                if ($this->inDateRange($cart->payment_received_at)) {
                    $prefix = $userInList ? '' : $userRef . ' · ';
                    $desc   = $prefix . ucfirst($cart->payment_method ?? 'cash');
                    if ($cart->transaction_reference) {
                        $desc .= ' · ref: ' . $cart->transaction_reference;
                    }
                    $entries->push([
                        'date'        => $cart->payment_received_at,
                        'type'        => 'payment',
                        'reference'   => $cartRef,
                        'description' => $desc,
                        'amount'      => (float) ($cart->payment_amount ?? $cart->total_amount),
                        'sort_key'    => $cart->payment_received_at->timestamp . '_b_' . $cart->id,
                    ]);
                }
            }
        }

        // Refund events
        if (in_array('refund', $this->types)) {
            $refundQuery = Refund::query()
                ->where('organisation_id', $tenantId)
                ->where('status', 'issued')
                ->whereNotNull('issued_at')
                ->with(['cart.competition', 'cart.user.selfProfile', 'enrolment.competitor']);

            if ($this->competitionId) {
                $refundQuery->whereHas('cart', fn (Builder $q) => $q->where('competition_id', $this->competitionId));
            }

            foreach ($refundQuery->get() as $refund) {
                if ($this->inDateRange($refund->issued_at)) {
                    $refundUserRef  = $refund->cart?->user?->selfProfile?->full_name ?? $refund->cart?->user?->email ?? '—';
                    $refundCartRef  = ($refund->cart?->competition?->name ?? '?') . ' #' . $refund->cart_id . ' · User #' . $refund->cart?->user_id;
                    $competitorName = $refund->enrolment?->competitor?->full_name;
                    $desc           = $refundUserRef
                        . ($competitorName ? ' · ' . $competitorName : '')
                        . ' · ' . ($refund->reason ?? '—');
                    $entries->push([
                        'date'        => $refund->issued_at,
                        'type'        => 'refund',
                        'reference'   => $refundCartRef,
                        'description' => $desc,
                        'amount'      => -(float) $refund->amount,
                        'sort_key'    => $refund->issued_at->timestamp . '_c_' . $refund->id,
                    ]);
                }
            }
        }

        // Sort asc, accumulate running balance, then reverse for display (desc)
        $sorted  = $entries->sortBy('sort_key')->values();
        $balance = 0.0;
        $result  = $sorted->map(function (array $entry) use (&$balance): array {
            $balance          += $entry['amount'];
            $entry['balance']  = $balance;
            return $entry;
        });

        $sorted = $result->sortByDesc('sort_key')->values();

        if ($this->search) {
            $term   = mb_strtolower($this->search);
            $sorted = $sorted->filter(fn (array $e) =>
                str_contains(mb_strtolower($e['description']), $term) ||
                str_contains(mb_strtolower($e['reference']), $term)
            )->values();
        }

        return $this->cachedEntries = $sorted;
    }

    public function computeTotals(Collection $entries): array
    {
        $invoiced = abs($entries->where('type', 'invoice')->sum('amount'));
        $payments = $entries->where('type', 'payment')->sum('amount');
        $refunds  = abs($entries->where('type', 'refund')->sum('amount'));
        // Net: positive = cash surplus (over-collected), negative = still outstanding
        $net      = $payments - $refunds - $invoiced;

        return compact('invoiced', 'payments', 'refunds', 'net');
    }

    public function downloadCsv(): StreamedResponse
    {
        $this->cachedEntries = null;
        $entries = $this->buildEntries()->sortBy('sort_key')->values();
        $org     = app('tenant')?->name ?? 'Organisation';

        return response()->streamDownload(function () use ($entries, $org) {
            $f = fopen('php://output', 'w');
            fputcsv($f, [$org . ' — Transaction Statement', '', '', '', '', '']);
            fputcsv($f, ['Generated', now()->format('d M Y H:i'), '', '', '', '']);
            fputcsv($f, []);
            fputcsv($f, ['Date', 'Type', 'Reference', 'Description', 'Amount', 'Balance']);
            foreach ($entries as $entry) {
                fputcsv($f, [
                    tenant_date($entry['date']),
                    ucfirst($entry['type']),
                    $entry['reference'],
                    $entry['description'],
                    ($entry['amount'] >= 0 ? '' : '-') . number_format(abs($entry['amount']), 2),
                    number_format($entry['balance'], 2),
                ]);
            }
            fclose($f);
        }, 'transaction-statement-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function downloadPdf(): StreamedResponse
    {
        $this->cachedEntries = null;
        $entries = $this->buildEntries();
        $totals  = $this->computeTotals($entries);
        $tenant  = app('tenant');
        $filters = [
            'competition' => $this->competitionId
                ? Competition::find($this->competitionId)?->name
                : null,
            'date_from' => $this->dateFrom,
            'date_to'   => $this->dateTo,
        ];

        $pdf = Pdf::loadView('filament.org-admin.pages.transaction-statement-pdf', compact('entries', 'totals', 'tenant', 'filters'))
            ->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'transaction-statement-' . now()->format('Y-m-d') . '.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    private function inDateRange(mixed $date): bool
    {
        if (! $date) {
            return false;
        }

        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

        if ($this->dateFrom && $carbon->lt(Carbon::parse($this->dateFrom)->startOfDay())) {
            return false;
        }
        if ($this->dateTo && $carbon->gt(Carbon::parse($this->dateTo)->endOfDay())) {
            return false;
        }

        return true;
    }
}
