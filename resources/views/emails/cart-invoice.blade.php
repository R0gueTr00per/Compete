<x-mail::message>

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:24px;">
<tr>
<td bgcolor="#1a3564" align="center" style="padding:22px 20px;background-color:#1a3564;">
<p style="font-size:28px;margin:0;line-height:1;color:#7eb8f7;">🧾</p>
<p style="color:#ffffff;font-size:19px;font-weight:700;margin:8px 0 4px 0;line-height:1.2;">Registration Invoice</p>
<p style="color:rgba(255,255,255,0.75);font-size:14px;margin:0;">{{ $org?->name }}</p>
</td>
</tr>
</table>

Hi {{ $recipientName }},

Your registration has been submitted. Here is your invoice summary.

@foreach ($invoice['items'] as $item)
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:20px 0;border:1px solid #dde5f0;">
<tr><td bgcolor="#2563a8" style="padding:9px 16px;background-color:#2563a8;">
<p style="color:#ffffff;font-size:11px;font-weight:700;margin:0;text-transform:uppercase;letter-spacing:0.08em;">{{ $item['profile_name'] }}</p>
</td></tr>
<tr><td style="padding:4px 16px 8px;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
@if ($item['is_official'])
<tr><td colspan="2" style="padding:8px 0;color:#92400e;font-size:13px;font-style:italic;">Official rate applied</td></tr>
@endif
@foreach ($item['events'] as $event)
<tr>
<td style="padding:8px 12px 8px 0;color:#374151;font-size:13px;border-top:1px solid #f1f5f9;vertical-align:top;">
<strong>{{ $event['event_name'] }}</strong>{{ $event['division_label'] ? ' / ' . $event['division_label'] : '' }}
</td>
<td style="padding:8px 0;color:#1a3564;font-size:14px;font-weight:600;border-top:1px solid #f1f5f9;text-align:right;vertical-align:top;white-space:nowrap;">${{ number_format($event['fee'], 2) }}</td>
</tr>
@endforeach
@if ($item['late_surcharge'] !== null)
<tr>
<td style="padding:8px 12px 8px 0;color:#92400e;font-size:13px;border-top:1px solid #f1f5f9;">Late surcharge</td>
<td style="padding:8px 0;color:#92400e;font-size:13px;border-top:1px solid #f1f5f9;text-align:right;white-space:nowrap;">${{ number_format($item['late_surcharge'], 2) }}</td>
</tr>
@endif
@if ($item['platform_fee'] > 0)
<tr>
<td style="padding:8px 12px 8px 0;color:#64748b;font-size:13px;border-top:1px solid #f1f5f9;">Service fee</td>
<td style="padding:8px 0;color:#64748b;font-size:13px;border-top:1px solid #f1f5f9;text-align:right;white-space:nowrap;">${{ number_format($item['platform_fee'], 2) }}</td>
</tr>
@endif
@if (($item['gst_amount'] ?? 0) > 0)
<tr>
<td style="padding:8px 12px 8px 0;color:#64748b;font-size:13px;border-top:1px solid #f1f5f9;">GST</td>
<td style="padding:8px 0;color:#64748b;font-size:13px;border-top:1px solid #f1f5f9;text-align:right;white-space:nowrap;">${{ number_format($item['gst_amount'], 2) }}</td>
</tr>
@endif
<tr>
<td style="padding:10px 12px 10px 0;color:#1a3564;font-size:14px;font-weight:700;border-top:2px solid #dde5f0;">Subtotal</td>
<td style="padding:10px 0;color:#1a3564;font-size:14px;font-weight:700;border-top:2px solid #dde5f0;text-align:right;white-space:nowrap;">${{ number_format($item['subtotal'], 2) }}</td>
</tr>
</table>
</td></tr>
</table>
@endforeach

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:20px 0;border:1px solid #dde5f0;">
<tr><td bgcolor="#0f766e" style="padding:9px 16px;background-color:#0f766e;">
<p style="color:#ffffff;font-size:11px;font-weight:700;margin:0;text-transform:uppercase;letter-spacing:0.08em;">Total Payable</p>
</td></tr>
<tr><td style="padding:14px 16px;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td style="color:#1a3564;font-size:22px;font-weight:700;">${{ number_format($invoice['grand_total'], 2) }}</td>
</tr>
@if ($totalGst > 0)
<tr><td style="color:#64748b;font-size:13px;padding-top:4px;">Includes GST of ${{ number_format($totalGst, 2) }}</td></tr>
@endif
<tr><td style="color:#64748b;font-size:13px;padding-top:8px;">Payment is collected at the competition check-in desk.</td></tr>
</table>
</td></tr>
</table>

<x-mail::button :url="$portalUrl">
View portal
</x-mail::button>

@include('emails.partials.email-footer')
</x-mail::message>
