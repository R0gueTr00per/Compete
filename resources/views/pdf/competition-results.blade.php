<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: sans-serif; font-size: 11px; color: #111; margin: 0; padding: 0; }
  h1 { font-size: 20px; margin-bottom: 4px; }
  .meta { color: #666; font-size: 10px; margin-bottom: 16px; }

  .event-block { width: 100%; border: 1px solid #d1d5db; border-radius: 4px; margin-bottom: 7px; overflow: hidden; box-sizing: border-box; }
  .event-header { background: #374151; color: #fff; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 3px 8px; }

  .divisions-table { width: 100%; border-collapse: collapse; }
  .divisions-table tr + tr td { border-top: 1px solid #e5e7eb; }
  .div-label-cell { width: 130px; vertical-align: top; padding: 3px 8px; background: #f9fafb; border-right: 1px solid #e5e7eb; font-size: 10px; font-weight: 600; color: #374151; }
  .div-places-cell { vertical-align: top; padding: 2px 4px; font-size: 0; }

  .place-cell { display: inline-block; vertical-align: top; font-size: 10px; margin: 2px; border: 1px solid #e5e7eb; border-radius: 3px; padding: 2px 6px; background: #ffffff; }
  .place-cell.top3 { background: #f3f4f6; border-color: #e5e7eb; }
  .place-cell.dq { opacity: 0.5; }
  .place-ord { display: inline; font-size: 9px; font-weight: 700; color: #6b7280; margin-right: 3px; }
  .place-ord.medal { color: #374151; }
  .place-name { display: inline; font-size: 10px; font-weight: 600; color: #111827; }
  .place-dojo { font-size: 8px; color: #9ca3af; margin-top: 0; }
</style>
</head>
<body>
@php
  $ordinal = fn(int $n): string => $n . match(true) {
      $n % 100 >= 11 && $n % 100 <= 13 => 'th',
      $n % 10 === 1 => 'st',
      $n % 10 === 2 => 'nd',
      $n % 10 === 3 => 'rd',
      default => 'th',
  };
@endphp

<h1>{{ $competition->name }} Results</h1>
<p class="meta">
  {{ \Carbon\Carbon::parse($competition->competition_date)->format('l, d F Y') }}
  @if ($competition->location_name) | {{ $competition->location_name }} @endif
  @if ($competition->location_address) — {{ $competition->location_address }} @endif
</p>

@foreach ($events as $compEvent)
  @if ($compEvent->divisions->isEmpty()) @continue @endif
  <div class="event-block">
    <div class="event-header">{{ $compEvent->name }}</div>
    <table class="divisions-table">
      @foreach ($compEvent->divisions as $division)
        @php
          $entries = $division->enrolmentEvents;
          if ($entries->isEmpty()) continue;
        @endphp
        <tr>
          <td class="div-label-cell">
            @if ($division->code)
              <div style="font-size:11px; font-weight:700; color:#111827;">{{ $division->code }}</div>
              <div style="font-size:9px; font-weight:400; color:#6b7280; margin-top:2px;">{{ $division->label }}</div>
            @else
              {{ $division->label }}
            @endif
          </td>
          <td class="div-places-cell">
            @foreach ($entries as $ee)
              @php
                $result  = $ee->result;
                $profile = $ee->enrolment->competitor?->competitorProfile;
                $name    = $profile ? "{$profile->first_name} {$profile->surname}" : ($ee->enrolment->competitor?->name ?? '—');
                $dojo    = $ee->enrolment->dojo_type === 'guest'
                    ? ($ee->enrolment->guest_style ?? 'Guest')
                    : ($ee->enrolment->dojo_name ?? '—');
                $isTop3  = $result?->placement && $result->placement <= 3;
              @endphp
              <div class="place-cell {{ $isTop3 ? 'top3' : '' }} {{ $result?->disqualified ? 'dq' : '' }}">
                <span class="place-ord {{ $isTop3 ? 'medal' : '' }}">{{ $result?->placement ? $ordinal($result->placement) : '—' }}{{ $result?->placement_overridden ? ' *' : '' }}</span><span class="place-name">{{ $name }}{{ $result?->disqualified ? ' (DQ)' : '' }}</span>
                <div class="place-dojo">{{ $dojo }}</div>
              </div>
            @endforeach
          </td>
        </tr>
      @endforeach
    </table>
  </div>
@endforeach

<p style="margin-top:30px; font-size:9px; color:#999; text-align:right;">
  Generated {{ now()->format('d M Y H:i') }} — Compete
</p>
</body>
</html>
