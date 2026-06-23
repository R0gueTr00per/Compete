<x-mail::message>

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:24px;">
<tr>
<td bgcolor="#c2410c" align="center" style="padding:22px 20px;background-color:#c2410c;">
<p style="font-size:28px;margin:0;line-height:1;color:#fed7aa;">↩</p>
<p style="color:#ffffff;font-size:19px;font-weight:700;margin:8px 0 4px 0;line-height:1.2;">Refund Issued</p>
<p style="color:rgba(255,255,255,0.75);font-size:14px;margin:0;">{{ $comp?->name ?? 'Competition' }}</p>
</td>
</tr>
</table>

Hi {{ $recipientName }},

A refund has been issued for your registration{{ $comp ? ' at **' . $comp->name . '**' : '' }}{{ $comp?->competition_date ? ' (' . tenant_date($comp->competition_date) . ')' : '' }}.

@foreach ($refunds as $i => $refund)
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:20px 0;border:1px solid #dde5f0;">
<tr><td bgcolor="#2563a8" style="padding:9px 16px;background-color:#2563a8;">
<p style="color:#ffffff;font-size:11px;font-weight:700;margin:0;text-transform:uppercase;letter-spacing:0.08em;">{{ $refund->enrolment?->competitor?->full_name ?? 'Refund' }}</p>
</td></tr>
<tr><td style="padding:4px 16px 8px;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td style="padding:8px 12px 8px 0;color:#64748b;font-size:13px;width:38%;vertical-align:top;">Type</td>
<td style="padding:8px 0;color:#1a3564;font-size:14px;font-weight:600;vertical-align:top;">{{ $refund->typeLabel() }}</td>
</tr>
<tr>
<td style="padding:8px 12px 8px 0;color:#64748b;font-size:13px;border-top:1px solid #f1f5f9;vertical-align:top;">Amount</td>
<td style="padding:8px 0;color:#1a3564;font-size:16px;font-weight:700;border-top:1px solid #f1f5f9;vertical-align:top;">{{ $currency }} {{ number_format((float) $refund->amount, 2) }}</td>
</tr>
<tr>
<td style="padding:8px 12px 8px 0;color:#64748b;font-size:13px;border-top:1px solid #f1f5f9;vertical-align:top;">Method</td>
<td style="padding:8px 0;color:#1a3564;font-size:14px;font-weight:600;border-top:1px solid #f1f5f9;vertical-align:top;">{{ ucfirst($refund->payment_method ?? 'cash') }}</td>
</tr>
@if ($refund->reason)
<tr>
<td style="padding:8px 12px 8px 0;color:#64748b;font-size:13px;border-top:1px solid #f1f5f9;vertical-align:top;">Reason</td>
<td style="padding:8px 0;color:#374151;font-size:14px;border-top:1px solid #f1f5f9;vertical-align:top;">{{ $refund->reason }}</td>
</tr>
@endif
</table>
</td></tr>
</table>
@endforeach

@if ($refunds->count() > 1)
**Total refunded: {{ $currency }} {{ number_format((float) $refunds->sum('amount'), 2) }}**

@endif

If you have any questions please contact the organisation directly.

<x-mail::button :url="$portalUrl . '/account'">
View my registrations
</x-mail::button>

@include('emails.partials.email-footer')
</x-mail::message>
