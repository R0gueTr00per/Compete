<x-mail::message>
# Support Request — {{ $fromName }}

**Organisation:** {{ $organisationName }} ({{ $organisationSlug }}.kompetic.com)

**From:** {{ $fromName }} &lt;{{ $fromEmail }}&gt;

**Area of concern:** {{ $area }}

---

## Description

{{ $notes }}

@include('emails.partials.email-footer')
</x-mail::message>
