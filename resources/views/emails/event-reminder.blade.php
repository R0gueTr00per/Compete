<x-mail::message>

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:24px;">
<tr>
<td bgcolor="#7c3aed" align="center" style="padding:22px 20px;background-color:#7c3aed;">
<p style="font-size:28px;margin:0;line-height:1;">📅</p>
<p style="color:#ffffff;font-size:19px;font-weight:700;margin:8px 0 4px 0;line-height:1.2;">Competition in 7 Days</p>
<p style="color:rgba(255,255,255,0.75);font-size:14px;margin:0;">{{ $competition->name }}</p>
</td>
</tr>
</table>

Hi {{ $recipientName }},

This is a reminder that **{{ $competition->name }}** is coming up in 7 days. Here are your details:

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:20px 0;border:1px solid #dde5f0;">
<tr><td bgcolor="#2563a8" style="padding:9px 16px;background-color:#2563a8;">
<p style="color:#ffffff;font-size:11px;font-weight:700;margin:0;text-transform:uppercase;letter-spacing:0.08em;">Competition Details</p>
</td></tr>
<tr><td style="padding:4px 16px 8px;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td style="padding:8px 12px 8px 0;color:#64748b;font-size:13px;width:38%;vertical-align:top;">Date</td>
<td style="padding:8px 0;color:#1a3564;font-size:14px;font-weight:600;vertical-align:top;">{{ $competition->competition_date->format('l, j F Y') }}</td>
</tr>
@if ($competition->location_name)
<tr>
<td style="padding:8px 12px 8px 0;color:#64748b;font-size:13px;border-top:1px solid #f1f5f9;vertical-align:top;">Venue</td>
<td style="padding:8px 0;color:#1a3564;font-size:14px;font-weight:600;border-top:1px solid #f1f5f9;vertical-align:top;">{{ $competition->location_name }}</td>
</tr>
@endif
@if ($competition->start_time)
<tr>
<td style="padding:8px 12px 8px 0;color:#64748b;font-size:13px;border-top:1px solid #f1f5f9;vertical-align:top;">Start time</td>
<td style="padding:8px 0;color:#1a3564;font-size:14px;font-weight:600;border-top:1px solid #f1f5f9;vertical-align:top;">{{ tenant_time($competition->start_time) }}</td>
</tr>
@endif
@if ($checkinTime)
<tr>
<td style="padding:8px 12px 8px 0;color:#64748b;font-size:13px;border-top:1px solid #f1f5f9;vertical-align:top;">Check-in from</td>
<td style="padding:8px 0;color:#1a3564;font-size:14px;font-weight:600;border-top:1px solid #f1f5f9;vertical-align:top;">{{ \Carbon\Carbon::parse($checkinTime)->format('g:i a') }}</td>
</tr>
@endif
</table>
</td></tr>
</table>

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:20px 0;border:1px solid #dde5f0;">
<tr><td bgcolor="#2563a8" style="padding:9px 16px;background-color:#2563a8;">
<p style="color:#ffffff;font-size:11px;font-weight:700;margin:0;text-transform:uppercase;letter-spacing:0.08em;">Your Registered Events</p>
</td></tr>
@foreach ($events as $i => $event)
<tr><td style="padding:10px 16px;{{ $i > 0 ? 'border-top:1px solid #f1f5f9;' : '' }}">
<p style="margin:0;font-size:14px;color:#1a3564;"><strong>{{ $event->competitionEvent->event_code }} — {{ $event->competitionEvent->name }}</strong></p>
<p style="margin:3px 0 0;font-size:13px;color:#64748b;">{{ $event->division?->label ?? '—' }}</p>
</td></tr>
@endforeach
</table>

Your check-in QR code is available on the competitor portal. Have it ready at the check-in desk for a fast scan.

<x-mail::button :url="$portalUrl . '/account'">
View my registrations & QR code
</x-mail::button>

@include('emails.partials.email-footer')
</x-mail::message>
