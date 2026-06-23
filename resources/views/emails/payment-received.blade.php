<x-mail::message>

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:24px;">
<tr>
<td bgcolor="#15803d" align="center" style="padding:22px 20px;background-color:#15803d;">
<p style="font-size:28px;margin:0;line-height:1;color:#bbf7d0;">✓</p>
<p style="color:#ffffff;font-size:19px;font-weight:700;margin:8px 0 4px 0;line-height:1.2;">Payment Received</p>
<p style="color:rgba(255,255,255,0.75);font-size:14px;margin:0;">{{ $org?->name }}</p>
</td>
</tr>
</table>

Hi {{ $recipientName }},

Your payment has been recorded. Thank you!

@foreach ($carts as $cart)
@php $active = $cart->enrolments->filter(fn ($e) => !$e->trashed())->whereNotIn('status', ['draft', 'withdrawn']); @endphp
@if ($active->isNotEmpty())
@php $comp = $active->first()?->competition; @endphp
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:20px 0;border:1px solid #dde5f0;">
<tr><td bgcolor="#2563a8" style="padding:9px 16px;background-color:#2563a8;">
<p style="color:#ffffff;font-size:11px;font-weight:700;margin:0;text-transform:uppercase;letter-spacing:0.08em;">{{ $comp?->name ?? 'Competition' }}</p>
</td></tr>
@foreach ($active as $i => $enrolment)
<tr><td style="padding:10px 16px;{{ $i > 0 ? 'border-top:1px solid #f1f5f9;' : '' }}font-size:14px;color:#1a3564;">
{{ $enrolment->competitor?->full_name ?? 'Competitor' }}
</td></tr>
@endforeach
</table>
@endif
@endforeach

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:20px 0;border:1px solid #dde5f0;">
<tr><td bgcolor="#0f766e" style="padding:9px 16px;background-color:#0f766e;">
<p style="color:#ffffff;font-size:11px;font-weight:700;margin:0;text-transform:uppercase;letter-spacing:0.08em;">Payment Summary</p>
</td></tr>
<tr><td style="padding:4px 16px 8px;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td style="padding:8px 12px 8px 0;color:#64748b;font-size:13px;width:38%;">Total received</td>
<td style="padding:8px 0;color:#1a3564;font-size:16px;font-weight:700;">{{ $currency }} {{ number_format($total, 2) }}</td>
</tr>
<tr>
<td style="padding:8px 12px 8px 0;color:#64748b;font-size:13px;border-top:1px solid #f1f5f9;">Method</td>
<td style="padding:8px 0;color:#1a3564;font-size:14px;font-weight:600;border-top:1px solid #f1f5f9;">{{ ucfirst($method) }}</td>
</tr>
</table>
</td></tr>
</table>

<x-mail::button :url="$portalUrl . '/account'">
View my account
</x-mail::button>

@include('emails.partials.email-footer')
</x-mail::message>
