<x-mail::message>
Hi {{ $recipientName }},

# Registration confirmed — {{ $competition->name }}

Registration for **{{ $profileName }}** has been received and confirmed.

<x-mail::panel>
**Competition:** {{ $competition->name }}

**Date:** {{ $competition->competition_date->format('l, j F Y') }}

@if ($competition->location_name)
**Venue:** {{ $competition->location_name }}

@endif
@if ($competition->start_time)
**Start time:** {{ tenant_time($competition->start_time) }}

@endif
**Fee:** ${{ number_format($enrolment->fee_calculated, 2) }}
@if ($enrolment->is_late)
_(late surcharge applied)_
@endif
</x-mail::panel>

## Registered Events

@foreach ($events as $event)
**{{ $event->competitionEvent->event_code }} — {{ $event->competitionEvent->name }}** / {{ $event->division?->label ?? '—' }}

@endforeach

@if ($enrolment->checkin_code)
<x-mail::panel>
**Check-in code:** `{{ $enrolment->checkin_code }}`

Your QR code is available on the competitor portal — show it at the check-in desk for a fast scan.
</x-mail::panel>
@endif

<x-mail::button :url="$portalUrl">
View registrations & QR code
</x-mail::button>

@include('emails.partials.email-footer')
</x-mail::message>
