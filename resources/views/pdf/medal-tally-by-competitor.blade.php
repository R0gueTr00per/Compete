<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: sans-serif; font-size: 11px; color: #111; margin: 0; padding: 0; }
  h1 { font-size: 20px; margin-bottom: 4px; }
  h2 { font-size: 13px; color: #374151; margin-bottom: 10px; }
  .meta { color: #666; font-size: 10px; margin-bottom: 16px; }

  table { width: 100%; border-collapse: collapse; border: 1px solid #d1d5db; border-radius: 4px; overflow: hidden; }
  thead tr { background: #374151; color: #fff; }
  thead th { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 5px 10px; text-align: left; }
  thead th.center { text-align: center; }
  tbody tr { border-top: 1px solid #e5e7eb; }
  tbody tr:nth-child(even) { background: #f9fafb; }
  td { padding: 4px 10px; font-size: 10px; }
  td.rank { width: 30px; font-weight: 700; color: #6b7280; }
  td.name { font-weight: 600; color: #111827; }
  td.dojo { color: #6b7280; }
  td.center { text-align: center; }
  td.gold { color: #b45309; font-weight: 700; text-align: center; }
  td.silver { color: #6b7280; font-weight: 700; text-align: center; }
  td.bronze { color: #92400e; font-weight: 700; text-align: center; }
  td.total { text-align: center; font-weight: 600; }
</style>
</head>
<body>

<h1>{{ $competition->name }}</h1>
<p class="meta">
  {{ tenant_date($competition->competition_date) }}
  @if ($competition->location_name) | {{ $competition->location_name }} @endif
</p>

<h2>Medal Tally — By Competitor</h2>

<table>
  <thead>
    <tr>
      <th>#</th>
      <th>Competitor</th>
      <th>Dojo</th>
      <th class="center">Gold</th>
      <th class="center">Silver</th>
      <th class="center">Bronze</th>
      <th class="center">Total</th>
    </tr>
  </thead>
  <tbody>
    @foreach ($tally as $row)
      <tr>
        <td class="rank">{{ $row['rank'] }}</td>
        <td class="name">{{ $row['name'] }}</td>
        <td class="dojo">{{ $row['dojo'] ?? '' }}</td>
        <td class="gold">{{ $row['gold'] ?: '—' }}</td>
        <td class="silver">{{ $row['silver'] ?: '—' }}</td>
        <td class="bronze">{{ $row['bronze'] ?: '—' }}</td>
        <td class="total">{{ $row['gold'] + $row['silver'] + $row['bronze'] }}</td>
      </tr>
    @endforeach
  </tbody>
</table>

<p style="margin-top:30px; font-size:9px; color:#999; text-align:right;">
  Generated {{ tenant_datetime(now()) }} — Kompetic
</p>
</body>
</html>
