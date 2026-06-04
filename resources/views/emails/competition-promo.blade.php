<x-mail::message>
# Registrations are open — {{ $competition->name }}

**{{ $org->name }}** has opened registrations for an upcoming competition.

---

**Competition:** {{ $competition->name }}

**Date:** {{ $competition->competition_date->format('l, j F Y') }}

@if ($competition->location_name)
**Venue:** {{ $competition->location_name }}

@endif
@if ($competition->start_time)
**Start time:** {{ tenant_time($competition->start_time) }}

@endif
@if ($competition->end_time)
**End time:** {{ tenant_time($competition->end_time) }}

@endif
@if ($competition->enrolment_due_date)
**Registration closes:** {{ $competition->enrolment_due_date->format('j F Y') }}

@endif

<x-mail::button :url="$portalUrl">
Register now
</x-mail::button>

Log in to your competitor portal to view competition details and register your profile(s).

---

@if ($org->contact_phone || $org->contact_email || $org->website)
---

**Contact {{ $org->name }}:**
@if ($org->contact_phone)
Phone: {{ $org->contact_phone }}
@endif
@if ($org->contact_email)
Email: {{ $org->contact_email }}
@endif
@if ($org->website)
Website: [{{ $org->website }}]({{ $org->website }})
@endif
@endif

*You are receiving this email because you are a member of {{ $org->name }} on [Kompetic]({{ config('app.scheme') . '://' . config('app.domain', 'kompetic.com') }}). To stop receiving these emails, update your preferences in your [competitor portal]({{ $portalUrl . '/preferences' }}).*
</x-mail::message>
