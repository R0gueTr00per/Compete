<x-mail::message>

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:24px;">
@if (abs($net) < 0.01)
<tr><td bgcolor="#15803d" align="center" style="padding:22px 20px;background-color:#15803d;">
<p style="font-size:28px;margin:0;line-height:1;color:#bbf7d0;">✓</p>
<p style="color:#ffffff;font-size:19px;font-weight:700;margin:8px 0 4px 0;line-height:1.2;">Account Settled</p>
@elseif ($net > 0)
<tr><td bgcolor="#c2410c" align="center" style="padding:22px 20px;background-color:#c2410c;">
<p style="font-size:28px;margin:0;line-height:1;color:#fed7aa;">!</p>
<p style="color:#ffffff;font-size:19px;font-weight:700;margin:8px 0 4px 0;line-height:1.2;">{{ $currency }} {{ number_format($net, 2) }} Outstanding</p>
@else
<tr><td bgcolor="#0f766e" align="center" style="padding:22px 20px;background-color:#0f766e;">
<p style="font-size:28px;margin:0;line-height:1;color:#99f6e4;">↩</p>
<p style="color:#ffffff;font-size:19px;font-weight:700;margin:8px 0 4px 0;line-height:1.2;">{{ $currency }} {{ number_format(abs($net), 2) }} Refund Due</p>
@endif
<p style="color:rgba(255,255,255,0.75);font-size:14px;margin:0;">{{ $org?->name }} — Account Statement</p>
</td></tr>
</table>

Hi {{ $recipientName }},

Here is your current account summary with {{ $org?->name }}.

@foreach ($carts as $cart)
@php
    $comp = $cart->competition;
    $enrolments = $cart->enrolments->filter(fn ($e) => !$e->trashed())->whereNotIn('status', ['draft']);
    $refunds = $cart->refunds ?? collect();
    $isPaid = $cart->isPaid();
@endphp
@if ($enrolments->isNotEmpty() || $refunds->isNotEmpty())
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:20px 0;border:1px solid #dde5f0;">
<tr><td bgcolor="#2563a8" style="padding:9px 16px;background-color:#2563a8;">
<p style="color:#ffffff;font-size:11px;font-weight:700;margin:0;text-transform:uppercase;letter-spacing:0.08em;">{{ $comp?->name ?? 'Competition' }}{{ $comp ? ' — ' . tenant_date($comp->competition_date) : '' }}</p>
</td></tr>
@foreach ($enrolments as $i => $enrolment)
@php
    $status = match(true) {
        $enrolment->status === 'withdrawn' => ['label' => 'Withdrawn', 'color' => '#64748b'],
        $isPaid => ['label' => 'Paid ✓', 'color' => '#15803d'],
        default => ['label' => 'Outstanding ' . $currency . ' ' . number_format((float) $enrolment->fee_calculated, 2), 'color' => '#c2410c'],
    };
@endphp
<tr><td style="padding:10px 16px;{{ $i > 0 || $refunds->isNotEmpty() ? 'border-top:1px solid #f1f5f9;' : '' }}">
<table width="100%" cellpadding="0" cellspacing="0"><tr>
<td style="font-size:14px;color:#1a3564;">{{ $enrolment->competitor?->full_name ?? 'Competitor' }}</td>
<td style="font-size:13px;color:{{ $status['color'] }};text-align:right;font-weight:600;">{{ $status['label'] }}</td>
</tr></table>
</td></tr>
@endforeach
@foreach ($refunds as $refund)
<tr><td style="padding:10px 16px;border-top:1px solid #f1f5f9;">
<table width="100%" cellpadding="0" cellspacing="0"><tr>
<td style="font-size:14px;color:#374151;">{{ $refund->enrolment?->competitor?->full_name ?? 'Refund' }}</td>
<td style="font-size:13px;color:#0f766e;text-align:right;font-weight:600;">{{ $refund->status === 'issued' ? 'Refunded' : 'Refund pending' }}: {{ $currency }} {{ number_format((float) $refund->amount, 2) }}</td>
</tr></table>
</td></tr>
@endforeach
</table>
@endif
@endforeach

If you have any questions please contact the organisation directly.

<x-mail::button :url="$portalUrl . '/account'">
View my account
</x-mail::button>

@include('emails.partials.email-footer')
</x-mail::message>
