<x-mail::message>
# AI Insights — {{ $competition->name }}

**Date:** {{ $competition->competition_date->format('d M Y') }}
**Status:** {{ ucfirst($competition->status) }}
**Generated:** {{ $insight->generated_at->format('d M Y, g:ia') }}

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
</x-mail::message>
