<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; background: #fff; }
    .header { padding: 18px 24px 12px; border-bottom: 2px solid #f97316; }
    .header h1 { font-size: 16px; font-weight: 700; color: #111827; }
    .header .meta { margin-top: 4px; color: #6b7280; font-size: 10px; }
    .filters { padding: 8px 24px; background: #f9fafb; border-bottom: 1px solid #e5e7eb; font-size: 10px; color: #6b7280; }
    table { width: 100%; border-collapse: collapse; }
    thead tr { background: #f3f4f6; }
    th { padding: 8px 12px; text-align: left; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; border-bottom: 1px solid #e5e7eb; }
    th.right, td.right { text-align: right; }
    td { padding: 7px 12px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 700; }
    .badge-invoice { background: #dbeafe; color: #1d4ed8; }
    .badge-payment { background: #d1fae5; color: #065f46; }
    .badge-refund  { background: #fee2e2; color: #991b1b; }
    .amount-pos  { color: #111827; }
    .amount-neg  { color: #dc2626; }
    .bal-pos  { color: #d97706; }
    .bal-neg  { color: #dc2626; }
    .bal-zero { color: #059669; }
    .footer { padding: 12px 24px; border-top: 2px solid #e5e7eb; margin-top: 8px; }
    .footer-grid { display: table; width: 100%; }
    .footer-cell { display: table-cell; width: 25%; }
    .footer-label { font-size: 9px; color: #6b7280; margin-bottom: 2px; }
    .footer-value { font-size: 13px; font-weight: 700; }
    .text-warning { color: #d97706; }
    .text-danger  { color: #dc2626; }
    .text-success { color: #059669; }
    .text-gray    { color: #374151; }
    .desc { color: #6b7280; font-size: 10px; }
    .ref  { color: #374151; font-size: 10px; }
</style>
</head>
<body>

<div class="header">
    <h1>{{ $tenant?->name ?? 'Organisation' }} — Transaction Statement</h1>
    <div class="meta">Generated {{ now()->format('d M Y H:i') }}</div>
</div>

@if ($filters['competition'] || $filters['date_from'] || $filters['date_to'])
<div class="filters">
    Filters:
    @if ($filters['competition']) Competition: {{ $filters['competition'] }}@endif
    @if ($filters['date_from']) &nbsp;|&nbsp; From: {{ \Carbon\Carbon::parse($filters['date_from'])->format('d M Y') }}@endif
    @if ($filters['date_to']) &nbsp;|&nbsp; To: {{ \Carbon\Carbon::parse($filters['date_to'])->format('d M Y') }}@endif
</div>
@endif

<table>
    <thead>
        <tr>
            <th style="width:90px">Date</th>
            <th style="width:65px">Type</th>
            <th style="width:160px">Reference</th>
            <th>Description</th>
            <th class="right" style="width:90px">Amount</th>
            <th class="right" style="width:90px">Balance</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($entries->sortBy('sort_key')->values() as $entry)
            @php
                $amtPos  = $entry['amount'] >= 0;
                $balCls  = $entry['balance'] > 0.009 ? 'bal-pos' : ($entry['balance'] < -0.009 ? 'bal-neg' : 'bal-zero');
            @endphp
            <tr>
                <td>{{ tenant_date($entry['date']) }}</td>
                <td>
                    <span class="badge badge-{{ $entry['type'] }}">{{ ucfirst($entry['type']) }}</span>
                </td>
                <td class="ref">{{ $entry['reference'] }}</td>
                <td class="desc">{{ $entry['description'] }}</td>
                <td class="right {{ $amtPos ? 'amount-pos' : 'amount-neg' }}">
                    {{ $amtPos ? '' : '−' }}{{ tenant_money(abs($entry['amount'])) }}
                </td>
                <td class="right {{ $balCls }}">
                    {{ $entry['balance'] < 0 ? '−' : '' }}{{ tenant_money(abs($entry['balance'])) }}
                </td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align:center;padding:24px;color:#9ca3af;">No transactions found.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    <div class="footer-grid">
        <div class="footer-cell">
            <div class="footer-label">Total Invoiced</div>
            <div class="footer-value text-gray">{{ tenant_money($totals['invoiced']) }}</div>
        </div>
        <div class="footer-cell">
            <div class="footer-label">Payments Received</div>
            <div class="footer-value text-success">{{ tenant_money($totals['payments']) }}</div>
        </div>
        <div class="footer-cell">
            <div class="footer-label">Refunds Issued</div>
            <div class="footer-value {{ $totals['refunds'] > 0 ? 'text-danger' : 'text-gray' }}">{{ tenant_money($totals['refunds']) }}</div>
        </div>
        <div class="footer-cell">
            <div class="footer-label">Net Balance</div>
            @php
                $netCls = $totals['net'] > 0.009 ? 'text-warning' : ($totals['net'] < -0.009 ? 'text-danger' : 'text-success');
            @endphp
            <div class="footer-value {{ $netCls }}">
                {{ $totals['net'] < 0 ? '−' : '' }}{{ tenant_money(abs($totals['net'])) }}
                <span style="font-size:9px;font-weight:400;color:#6b7280;">
                    @if ($totals['net'] > 0.009) outstanding
                    @elseif ($totals['net'] < -0.009) credit
                    @else settled
                    @endif
                </span>
            </div>
        </div>
    </div>
</div>

</body>
</html>
