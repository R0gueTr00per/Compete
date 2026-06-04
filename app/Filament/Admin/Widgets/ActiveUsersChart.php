<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Organisation;
use App\Models\OrganisationMembership;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ActiveUsersChart extends ChartWidget
{
    protected static ?string $heading = 'Registered users over time (last 12 weeks)';
    protected static ?string $maxHeight = '150px';
    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $weeks = collect();
        for ($i = 11; $i >= 0; $i--) {
            $weeks->push(now()->subWeeks($i)->startOfWeek()->startOfDay());
        }

        $labels = $weeks->map(fn ($w) => $w->format('d M'))->toArray();

        // Single query: count memberships per org per day for the period
        $rows = OrganisationMembership::select(
                'organisation_id',
                DB::raw('DATE(created_at) as day'),
                DB::raw('COUNT(*) as cnt')
            )
            ->where('created_at', '>=', $weeks->first())
            ->groupBy('organisation_id', DB::raw('DATE(created_at)'))
            ->get()
            ->groupBy('organisation_id');

        // Pre-count total memberships before the window to start cumulative sums correctly
        $priorCounts = OrganisationMembership::select(
                'organisation_id',
                DB::raw('COUNT(*) as cnt')
            )
            ->where('created_at', '<', $weeks->first())
            ->groupBy('organisation_id')
            ->pluck('cnt', 'organisation_id');

        $orgIds = $rows->keys()->merge($priorCounts->keys())->unique();
        $orgs   = Organisation::whereIn('id', $orgIds)->get()->keyBy('id');

        $colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#84cc16'];

        $datasets = [];
        foreach ($orgs as $index => $org) {
            $orgRows = $rows->get($org->id, collect())->keyBy('day');
            $total   = $priorCounts->get($org->id, 0);
            $data    = [];

            foreach ($weeks as $week) {
                // Sum all days within this week
                for ($d = 0; $d < 7; $d++) {
                    $day = $week->copy()->addDays($d)->format('Y-m-d');
                    $total += (int) ($orgRows->get($day)?->cnt ?? 0);
                }
                $data[] = $total;
            }

            $color      = $colors[$index % count($colors)];
            $datasets[] = [
                'label'           => $org->name,
                'data'            => $data,
                'borderColor'     => $color,
                'backgroundColor' => $color . '33',
                'tension'         => 0.3,
                'fill'            => false,
                'pointRadius'     => 3,
            ];
        }

        return [
            'labels'   => $labels,
            'datasets' => $datasets,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
