<x-mail::message>

{{-- Welcome banner --}}
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:24px;">
<tr>
<td bgcolor="#1a3564" align="center" style="padding:22px 20px;background-color:#1a3564;">
<p style="font-size:28px;margin:0;line-height:1;color:#7eb8f7;">👋</p>
<p style="color:#ffffff;font-size:19px;font-weight:700;margin:8px 0 4px 0;line-height:1.2;">Welcome to {{ $org->name }}</p>
<p style="color:rgba(255,255,255,0.75);font-size:14px;margin:0;">Your account has been created</p>
</td>
</tr>
</table>

Hi {{ $recipientName }},

@if ($childName)
An account has been set up for you as the parent / guardian of **{{ $childName }}**, who has been registered for the following competition.
@else
An account has been created for you on **{{ $org->name }}** and you have been registered for the following competition.
@endif

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
<td style="padding:8px 0;color:#1a3564;font-size:14px;font-weight:600;border-top:1px solid #f1f5f9;vertical-align:top;">{{ $competition->location_name }}{{ $competition->location_address ? ', ' . $competition->location_address : '' }}</td>
</tr>
@endif
@if ($competition->start_time)
<tr>
<td style="padding:8px 12px 8px 0;color:#64748b;font-size:13px;border-top:1px solid #f1f5f9;vertical-align:top;">Start time</td>
<td style="padding:8px 0;color:#1a3564;font-size:14px;font-weight:600;border-top:1px solid #f1f5f9;vertical-align:top;">{{ tenant_time($competition->start_time) }}</td>
</tr>
@endif
</table>
</td></tr>
</table>

{{-- Events --}}
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:20px 0;border:1px solid #dde5f0;">
<tr><td bgcolor="#2563a8" style="padding:9px 16px;background-color:#2563a8;">
<p style="color:#ffffff;font-size:11px;font-weight:700;margin:0;text-transform:uppercase;letter-spacing:0.08em;">{{ $childName ? $childName . "'s Registered Events" : 'Registered Events' }}</p>
</td></tr>
@foreach ($events as $i => $event)
<tr><td style="padding:10px 16px;{{ $i > 0 ? 'border-top:1px solid #f1f5f9;' : '' }}">
<p style="margin:0;font-size:14px;color:#1a3564;"><strong>{{ $event->competitionEvent->event_code }} — {{ $event->competitionEvent->name }}</strong></p>
<p style="margin:3px 0 0;font-size:13px;color:#64748b;">{{ $event->division?->label ?? '—' }}</p>
</td></tr>
@endforeach
</table>

{{-- Set password CTA --}}
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:20px 0;border:1px solid #dde5f0;">
<tr><td bgcolor="#0f766e" style="padding:9px 16px;background-color:#0f766e;">
<p style="color:#ffffff;font-size:11px;font-weight:700;margin:0;text-transform:uppercase;letter-spacing:0.08em;">Next Step</p>
</td></tr>
<tr><td style="padding:16px;">
<p style="margin:0;font-size:14px;color:#374151;">Set your password to access the competitor portal, view your registrations, and get your check-in QR code.</p>
</td></tr>
</table>

<x-mail::button :url="$resetUrl">
Set your password
</x-mail::button>

@include('emails.partials.email-footer')
</x-mail::message>
