<x-mail::message>
# AI Insights — {{ $competition->name }}

**Date:** {{ tenant_date($competition->competition_date) }}
**Status:** {{ match($competition->status) {
    'enrolments_closed' => 'Registrations Closed',
    default             => ucfirst($competition->status),
} }}
**Generated:** {{ tenant_datetime($insight->generated_at) }}

---

@foreach ($sections as $section)
## {{ $section['heading'] }}

{!! $section['body'] !!}

---

@endforeach

<x-mail::button :url="url('/manage/competitions/' . $competition->id . '/insights')">
View Full Insights
</x-mail::button>

*Powered by {{ $insight->model_used }} via {{ config('app.name') }}*

@include('emails.partials.email-footer')
</x-mail::message>
