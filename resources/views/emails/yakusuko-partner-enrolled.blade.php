<x-mail::message>
Hi {{ $recipientName }},

# Yakusuko partner confirmed — {{ $competition->name }}

Your Yakusuko partner **{{ $partner->getFilamentName() }}** has registered for the same event.

---

**Competition:** {{ $competition->name }}

**Date:** {{ $competition->competition_date->format('l, j F Y') }}

**Event:** {{ $event->event_code }} — {{ $event->name }}

Both of you are now confirmed as Yakusuko partners for this event.

<x-mail::button :url="$portalUrl">
View my registrations
</x-mail::button>

See you at the competition!

@include('emails.partials.email-footer')
</x-mail::message>
