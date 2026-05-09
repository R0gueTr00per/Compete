<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: sans-serif; font-size: 11px; color: #111; margin: 0; padding: 20px; }
  h1 { font-size: 20px; margin-bottom: 4px; }
  h2 { font-size: 14px; margin-top: 20px; margin-bottom: 4px; border-bottom: 2px solid #333; padding-bottom: 2px; }
  h3 { font-size: 12px; margin-top: 12px; margin-bottom: 2px; color: #555; }
  table { width: 100%; border-collapse: collapse; margin-top: 4px; }
  th { background: #333; color: #fff; padding: 4px 6px; text-align: left; font-size: 10px; }
  td { border-bottom: 1px solid #ddd; padding: 3px 6px; }
  .meta { color: #666; font-size: 10px; margin-bottom: 16px; }
  .medal-1:before { content: '🥇 '; }
  .medal-2:before { content: '🥈 '; }
  .medal-3:before { content: '🥉 '; }
  .dq { color: #c00; }
</style>
</head>
<body>
<h1>{{ $competition->name }}</h1>
<p class="meta">
  {{ \Carbon\Carbon::parse($competition->competition_date)->format('l, d F Y') }} |
  {{ $competition->location_name }}
  @if ($competition->location_address) — {{ $competition->location_address }} @endif
</p>

@foreach ($competition->competitionEvents->sortBy('running_order') as $compEvent)
  @if ($compEvent->divisions->isEmpty()) @continue @endif
  <h2>{{ $compEvent->event_code }} — {{ $compEvent->name }}
    @if ($compEvent->location_label) ({{ $compEvent->location_label }}) @endif
  </h2>

  @foreach ($compEvent->divisions as $division)
    @php
      $entries = $division->enrolmentEvents->filter(fn($ee) => ! $ee->removed);
      if ($entries->isEmpty()) continue;
    @endphp
    <h3>{{ $division->full_label }}</h3>
    <table>
      <thead>
        <tr>
          <th style="width:8%">Place</th>
          <th>Competitor</th>
          <th>Dojo</th>
          @if (in_array($compEvent->effectiveScoringMethod(), ['judges_total', 'judges_average']))
            <th style="width:18%">Score</th>
          @elseif ($compEvent->effectiveScoringMethod() === 'first_to_n')
            <th style="width:12%">Points</th>
          @else
            <th style="width:12%">Result</th>
          @endif
        </tr>
      </thead>
      <tbody>
        @foreach ($entries->sortBy(fn($ee) => $ee->result?->placement ?? 999) as $ee)
          @php
            $result  = $ee->result;
            $profile = $ee->enrolment->competitor?->competitorProfile;
            $enrol   = $ee->enrolment;
            $name    = $profile ? "{$profile->first_name} {$profile->surname}" : ($enrol->competitor?->name ?? '?');
            $dojo    = $enrol->dojo_type === 'guest'
              ? ($enrol->guest_style ?? 'Guest')
              : ($enrol->dojo_name ?? '—');
            $placement = $result?->placement;
            $medalClass = match($placement) { 1 => 'medal-1', 2 => 'medal-2', 3 => 'medal-3', default => '' };
          @endphp
          <tr @if($result?->disqualified) class="dq" @endif>
            <td class="{{ $medalClass }}">
              {{ $placement ? "{$placement}" : '—' }}
              @if ($result?->placement_overridden) * @endif
            </td>
            <td>{{ $name }}@if($result?->disqualified) (DQ)@endif</td>
            <td>{{ $dojo }}</td>
            <td>
              @if (in_array($compEvent->effectiveScoringMethod(), ['judges_total', 'judges_average']))
                {{ $result?->total_score !== null ? number_format($result->total_score, 2) : '—' }}
              @elseif ($compEvent->effectiveScoringMethod() === 'first_to_n')
                {{ $result?->total_score !== null ? (int)$result->total_score : '—' }}
              @else
                {{ $result?->win_loss ? ucfirst($result->win_loss) : '—' }}
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endforeach
@endforeach

<p style="margin-top:30px; font-size:9px; color:#999; text-align:right;">
  Generated {{ now()->format('d M Y H:i') }} — Compete
</p>
</body>
</html>
