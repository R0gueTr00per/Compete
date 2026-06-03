<x-mail::message>
# Reminder — registrations are still open

**{{ $org->name }}** wants to remind you that registrations for **{{ $competition->name }}** are still open and you haven't registered yet.

---

**Competition:** {{ $competition->name }}
**Date:** {{ $competition->competition_date->format('l, j F Y') }}
@if ($competition->location_name)
**Venue:** {{ $competition->location_name }}
@endif
@if ($competition->enrolment_due_date)
**Registration closes:** {{ $competition->enrolment_due_date->format('j F Y') }}
@endif

<x-mail::button :url="$portalUrl">
Register now
</x-mail::button>

Log in to your competitor portal to view competition details and register your profile(s).

---

*You are receiving this email because you are a member of {{ $org->name }} on [Kompetic]({{ config('app.scheme') . '://' . config('app.domain', 'kompetic.com') }}). To stop receiving these emails, update your preferences in your [competitor portal]({{ $portalUrl . '/preferences' }}).*
</x-mail::message>
