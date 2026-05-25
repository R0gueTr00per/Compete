<x-mail::message>
# Support Request — {{ $fromName }}

**Organisation:** {{ $organisationName }} ({{ $organisationSlug }}.kompetic.com)

**From:** {{ $fromName }} &lt;{{ $fromEmail }}&gt;

**Area of concern:** {{ $area }}

---

## Description

{{ $notes }}

---

*Sent via the Kompetic support form*
</x-mail::message>
