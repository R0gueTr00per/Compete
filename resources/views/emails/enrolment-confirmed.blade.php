<x-mail::message>

{{-- Success banner --}}
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:24px;">
<tr>
<td bgcolor="#1a3564" align="center" style="padding:22px 20px;background-color:#1a3564;">
<p style="font-size:32px;margin:0;line-height:1;color:#7eb8f7;">✓</p>
<p style="color:#ffffff;font-size:19px;font-weight:700;margin:8px 0 4px 0;line-height:1.2;">Registration Confirmed</p>
<p style="color:rgba(255,255,255,0.75);font-size:14px;margin:0;">{{ $competition->name }}</p>
</td>
</tr>
</table>

Hi {{ $recipientName }},

Registration for **{{ $profileName }}** has been confirmed.

{{-- Competition details --}}
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:20px 0;border:1px solid #dde5f0;">
<tr><td bgcolor="#2563a8" style="padding:9px 16px;background-color:#2563a8;">
<p style="color:#ffffff;font-size:11px;font-weight:700;margin:0;text-transform:uppercase;letter-spacing:0.08em;">Competition Details</p>
</td></tr>
<tr><td style="padding:4px 16px 8px;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td style="padding:8px 12px 8px 0;color:#64748b;font-size:13px;width:38%;vertical-align:top;">Competition</td>
<td style="padding:8px 0;color:#1a3564;font-size:14px;font-weight:600;vertical-align:top;">{{ $competition->name }}</td>
</tr>
<tr>
<td style="padding:8px 12px 8px 0;color:#64748b;font-size:13px;border-top:1px solid #f1f5f9;vertical-align:top;">Date</td>
<td style="padding:8px 0;color:#1a3564;font-size:14px;font-weight:600;border-top:1px solid #f1f5f9;vertical-align:top;">{{ $competition->competition_date->format('l, j F Y') }}</td>
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
<tr>
<td style="padding:8px 12px 8px 0;color:#64748b;font-size:13px;border-top:1px solid #f1f5f9;vertical-align:top;">Fee</td>
<td style="padding:8px 0;color:#1a3564;font-size:14px;font-weight:600;border-top:1px solid #f1f5f9;vertical-align:top;">${{ number_format($enrolment->fee_calculated, 2) }}@if ($enrolment->is_late) <span style="color:#92400e;font-size:12px;font-weight:400;"> (late surcharge applied)</span>@endif</td>
</tr>
</table>
</td></tr>
</table>

{{-- Events --}}
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:20px 0;border:1px solid #dde5f0;">
<tr><td bgcolor="#2563a8" style="padding:9px 16px;background-color:#2563a8;">
<p style="color:#ffffff;font-size:11px;font-weight:700;margin:0;text-transform:uppercase;letter-spacing:0.08em;">Registered Events</p>
</td></tr>
@foreach ($events as $i => $event)
<tr><td style="padding:10px 16px;border-top:{{ $i === 0 ? 'none' : '1px solid #f1f5f9' }};">
<p style="margin:0;font-size:14px;color:#1a3564;"><strong>{{ $event->competitionEvent->event_code }} — {{ $event->competitionEvent->name }}</strong></p>
<p style="margin:3px 0 0;font-size:13px;color:#64748b;">{{ $event->division?->label ?? '—' }}</p>
</td></tr>
@endforeach
</table>

@if ($enrolment->checkin_code)
{{-- Check-in --}}
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:20px 0;border:1px solid #dde5f0;">
<tr><td bgcolor="#0f766e" style="padding:9px 16px;background-color:#0f766e;">
<p style="color:#ffffff;font-size:11px;font-weight:700;margin:0;text-transform:uppercase;letter-spacing:0.08em;">Check-in</p>
</td></tr>
<tr><td align="center" style="padding:16px;">
@if ($qrImageUrl)
<p style="margin:0 0 10px;font-size:13px;color:#64748b;">Show this QR code at the check-in desk</p>
<img src="{{ $qrImageUrl }}" width="180" height="180" alt="Check-in QR code" style="width:180px;height:180px;max-width:100%;display:block;margin:0 auto 12px;">
@endif
<p style="margin:0 0 4px;font-size:12px;color:#64748b;">Check-in code</p>
<p style="margin:0;font-size:22px;font-weight:700;letter-spacing:0.15em;color:#1a3564;font-family:monospace;">{{ $enrolment->checkin_code }}</p>
</td></tr>
</table>
@endif

<x-mail::button :url="$portalUrl">
View registrations & QR code
</x-mail::button>

@include('emails.partials.email-footer')
</x-mail::message>
