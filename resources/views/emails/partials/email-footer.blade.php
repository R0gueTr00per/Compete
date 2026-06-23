---

@if (isset($org))
**{{ $org->name }}**
@if ($org->contact_email || $org->contact_phone || $org->website)
@if ($org->contact_email)[{{ $org->contact_email }}](mailto:{{ $org->contact_email }})@endif
@if ($org->contact_phone){{ $org->contact_phone }}@endif
@if ($org->website)[{{ $org->website }}]({{ $org->website }})@endif
@endif

@if (isset($portalUrl))
[Competitor Portal]({{ $portalUrl }})

@endif
@endif
[Kompetic]({{ config('app.scheme') . '://' . config('app.domain', 'kompetic.com') }}) — Tournament Management · [support@kompetic.com](mailto:support@kompetic.com)

@if ($marketingEmail ?? false)
*You are receiving this email because you are a member of {{ $org->name }} on [Kompetic]({{ config('app.scheme') . '://' . config('app.domain', 'kompetic.com') }}). [Update email preferences]({{ $portalUrl }}/preferences)*
@endif
