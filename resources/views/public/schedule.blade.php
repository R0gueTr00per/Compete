<x-layouts.public>
    <x-slot name="title">Schedule — {{ $competition->name }}</x-slot>

    <x-slot name="head">
        @if ($competition->status !== 'complete')
            <meta http-equiv="refresh" content="60">
        @endif
        <style>[x-cloak]{display:none!important}</style>
    </x-slot>

    {{-- Page header --}}
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
            <h1 class="text-xl font-bold text-gray-900">{{ $competition->name }}</h1>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1 text-sm text-gray-500">
                <span>{{ tenant_date($competition->competition_date) }}</span>
                @if ($competition->location_name)
                    <span>&middot; {{ $competition->location_name }}</span>
                @endif
                @if ($competition->start_time)
                    <span>&middot; Starts {{ tenant_time($competition->start_time) }}</span>
                @endif
                @if ($competition->end_time)
                    <span>&middot; Ends {{ tenant_time($competition->end_time) }}</span>
                @endif
                @if ($competition->status !== 'complete')
                    <span class="sm:ml-auto text-xs text-gray-400">Auto-refreshes every 60 s &middot; Updated {{ tenant_time(now()) }}</span>
                @else
                    <span class="sm:ml-auto text-xs text-gray-400">Final results</span>
                @endif
            </div>
        </div>
    </div>

    @if ($divisions->isEmpty())
        <p class="text-center text-gray-400 py-16">No divisions scheduled yet.</p>
    @else
        @php
            $placementLabels = ['1st', '2nd', '3rd'];
            $placementColors = [
                1 => 'bg-yellow-100 text-yellow-800 border border-yellow-300',
                2 => 'bg-gray-100 text-gray-700 border border-gray-300',
                3 => 'bg-orange-100 text-orange-800 border border-orange-300',
            ];
            $allDivisions = $divisions->flatten(1);
            $compDate = \Carbon\Carbon::parse($competition->competition_date)->format('Y-m-d');
            $colSortedBreaks = $breaks->map(fn ($b) => [
                'name'      => $b->name,
                'start_str' => substr($b->start_time, 0, 5),
                'end_str'   => $b->endTime(),
                'ts'        => \Carbon\Carbon::parse($compDate . ' ' . $b->start_time)->timestamp,
            ])->sortBy('ts')->values();
        @endphp

        <div x-data="{ selected: null }" @sched-panel-close.window="selected = null">

            {{-- ── Mobile: compact all-mats grid ── --}}
            <div class="sm:hidden px-3 pt-4" :class="selected !== null ? 'pb-56' : 'pb-6'">
                <div class="flex gap-1.5">
                    @foreach ($divisions as $location => $locationDivisions)
                        @php
                            $mobileTimeline = [];
                            $mBIdx = 0;
                            foreach ($locationDivisions as $div) {
                                if ($div->planned_start_at) {
                                    while ($mBIdx < $colSortedBreaks->count()
                                        && $colSortedBreaks[$mBIdx]['ts'] <= $div->planned_start_at->timestamp) {
                                        $mobileTimeline[] = array_merge(['type' => 'break'], $colSortedBreaks[$mBIdx]);
                                        $mBIdx++;
                                    }
                                }
                                $mobileTimeline[] = ['type' => 'div', 'div' => $div];
                            }
                        @endphp
                        <div class="flex-1 min-w-0">
                            <div class="text-center text-xs font-bold text-gray-500 truncate mb-2 pb-1.5 border-b border-gray-200">
                                {{ $location }}
                            </div>
                            <div class="space-y-1">
                                @foreach ($mobileTimeline as $row)
                                    @if ($row['type'] === 'break')
                                        <div class="w-full rounded bg-amber-50 border border-amber-200 px-1 py-1.5 text-center">
                                            <span class="text-xs font-semibold text-amber-700 leading-none">Break</span>
                                        </div>
                                    @else
                                        @php
                                            $div = $row['div'];
                                            $cardBg = match ($div->status) {
                                                'complete'           => 'bg-green-100 border-green-300',
                                                'assigned','running' => 'bg-blue-100 border-blue-300',
                                                'cancelled'          => 'bg-red-100 border-red-300 opacity-60',
                                                default              => 'bg-white border-gray-200',
                                            };
                                        @endphp
                                        <button
                                            type="button"
                                            @click="selected === {{ $div->id }} ? (selected = null, $dispatch('sched-panel-close')) : (selected = {{ $div->id }}, $dispatch('sched-panel-open', { id: {{ $div->id }} }))"
                                            :class="selected === {{ $div->id }} ? 'ring-2 ring-offset-1 ring-gray-800' : ''"
                                            class="w-full rounded border {{ $cardBg }} px-1.5 py-1.5 text-left transition-shadow"
                                        >
                                            <div class="flex items-center justify-between gap-1">
                                                <span class="font-mono text-xs font-bold leading-none text-gray-800">{{ $div->code }}</span>
                                                @if ($div->status === 'complete')
                                                    <svg class="flex-none h-2.5 w-2.5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                    </svg>
                                                @elseif ($div->planned_start_at)
                                                    <span class="text-gray-400 leading-none tabular-nums" style="font-size:9px">{{ tenant_time($div->planned_start_at) }}</span>
                                                @endif
                                            </div>
                                            <div class="text-xs text-gray-500 leading-tight mt-0.5 truncate">{{ $div->competitionEvent->name }}</div>
                                            <div class="text-gray-600 leading-tight truncate" style="font-size:10px">{{ $div->label }}</div>
                                        </button>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- ── Mobile: slide-up detail panel ── --}}
            <template x-teleport="body">
            <div
                x-data="{ selected: null }"
                @sched-panel-open.window="selected = $event.detail.id"
                @sched-panel-close.window="selected = null"
                x-show="selected !== null"
                style="display:none;"
                class="sm:hidden fixed bottom-0 inset-x-0 z-[200] bg-white border-t border-gray-200 shadow-xl rounded-t-xl"
            >
                <div class="flex items-center justify-between px-4 pt-3 pb-2 border-b border-gray-100">
                    <span class="text-sm font-semibold text-gray-700">Division Details</span>
                    <button type="button" @click="selected = null; $dispatch('sched-panel-close')" class="p-1 text-gray-400 hover:text-gray-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="px-4 py-4 overflow-y-auto max-h-48">
                    @foreach ($allDivisions as $div)
                        <div x-show="selected === {{ $div->id }}" style="display:none;">
                            <div class="flex items-start gap-3">
                                <div class="font-mono text-xl font-bold text-gray-900 leading-none pt-0.5">{{ $div->code }}</div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-xs text-gray-500">{{ $div->competitionEvent->name }}</div>
                                    <div class="text-sm font-medium text-gray-800">{{ $div->label }}</div>
                                    @if ($div->planned_start_at)
                                        @php
                                            $driftMin = $div->actual_start_at
                                                ? (int) round($div->planned_start_at->diffInMinutes($div->actual_start_at, false))
                                                : null;
                                        @endphp
                                        <div class="flex items-center gap-1.5 mt-1">
                                            <span class="text-xs text-gray-400 tabular-nums">{{ tenant_time($div->planned_start_at) }}</span>
                                            @if ($driftMin !== null && abs($driftMin) < 1440)
                                                @php
                                                    if ($driftMin < 0)      $driftCls = 'bg-blue-100 text-blue-700';
                                                    elseif ($driftMin === 0) $driftCls = 'bg-green-100 text-green-700';
                                                    elseif ($driftMin <= 5)  $driftCls = 'bg-green-100 text-green-700';
                                                    elseif ($driftMin <= 15) $driftCls = 'bg-amber-100 text-amber-700';
                                                    else                    $driftCls = 'bg-red-100 text-red-700';
                                                    $driftLabel = $driftMin < 0 ? abs($driftMin) . 'm early' : ($driftMin === 0 ? 'On time' : '+' . $driftMin . 'm');
                                                @endphp
                                                <span class="inline-block rounded px-1 py-0.5 text-xs font-medium {{ $driftCls }}">{{ $driftLabel }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>

                            @if ($div->status === 'complete' && $div->results->isNotEmpty())
                                <div class="mt-3 space-y-1.5 border-t border-gray-100 pt-3">
                                    @foreach ($div->results->whereNotNull('placement')->where('disqualified', false)->sortBy('placement')->take(3) as $result)
                                        @php $competitor = $result->enrolmentEvent?->competitor; @endphp
                                        <div class="flex items-center gap-2 text-sm">
                                            <span class="flex-none inline-block px-2 py-0.5 rounded text-xs font-bold {{ $placementColors[$result->placement] ?? 'bg-gray-100 text-gray-600' }}">
                                                {{ $placementLabels[$result->placement - 1] ?? $result->placement . 'th' }}
                                            </span>
                                            <span class="text-gray-700 {{ $result->placement === 1 ? 'font-bold' : '' }}">
                                                @if ($result->disqualified)
                                                    <span class="text-red-600">DQ</span>
                                                @elseif ($competitor)
                                                    {{ $competitor->first_name }} {{ $competitor->surname }}
                                                @else
                                                    &mdash;
                                                @endif
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            </template>

            {{-- ── Desktop: full-detail horizontal scroll ── --}}
            <div class="hidden sm:block max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div class="flex gap-4 overflow-x-auto pb-4 items-start">
                    @foreach ($divisions as $location => $locationDivisions)
                        @php
                            $desktopTimeline = [];
                            $dBIdx = 0;
                            foreach ($locationDivisions as $div) {
                                if ($div->planned_start_at) {
                                    while ($dBIdx < $colSortedBreaks->count()
                                        && $colSortedBreaks[$dBIdx]['ts'] <= $div->planned_start_at->timestamp) {
                                        $desktopTimeline[] = array_merge(['type' => 'break'], $colSortedBreaks[$dBIdx]);
                                        $dBIdx++;
                                    }
                                }
                                $desktopTimeline[] = ['type' => 'div', 'div' => $div];
                            }
                        @endphp
                        <div class="flex-none w-64">
                            <h2 class="text-xs font-bold uppercase tracking-widest text-gray-500 mb-3 pb-2 border-b-2 border-gray-200">
                                {{ $location }}
                            </h2>
                            <div class="space-y-2">
                                @foreach ($desktopTimeline as $row)
                                    @if ($row['type'] === 'break')
                                        <div class="px-3 py-2 my-1 rounded bg-amber-50 border border-amber-200">
                                            <div class="flex items-center gap-2">
                                                <svg class="h-3.5 w-3.5 shrink-0 text-amber-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path d="M5.75 3a.75.75 0 0 0-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 0 0 .75-.75V3.75A.75.75 0 0 0 7.25 3h-1.5ZM12.75 3a.75.75 0 0 0-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 0 0 .75-.75V3.75a.75.75 0 0 0-.75-.75h-1.5Z"/>
                                                </svg>
                                                <span class="text-xs font-semibold text-amber-700 truncate">{{ $row['name'] }}</span>
                                            </div>
                                            <div class="text-xs text-amber-600 mt-0.5 pl-5">{{ $row['start_str'] }}–{{ $row['end_str'] }}</div>
                                        </div>
                                    @else
                                        @php
                                            $div = $row['div'];
                                            $cardClass = match ($div->status) {
                                                'complete'           => 'bg-green-50 border-green-300',
                                                'assigned','running' => 'bg-blue-50 border-blue-300',
                                                'cancelled'          => 'bg-red-50 border-red-300 opacity-60',
                                                default              => 'bg-white border-gray-200',
                                            };
                                            $badgeClass = match ($div->status) {
                                                'complete'  => 'bg-green-100 text-green-800',
                                                'cancelled' => 'bg-red-100 text-red-800',
                                                default     => null,
                                            };
                                        @endphp
                                        <div class="rounded-lg border {{ $cardClass }} px-3 py-2.5">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="font-mono text-xs font-bold text-gray-700">{{ $div->code }}</span>
                                                @if ($div->planned_start_at)
                                                    @php
                                                        $driftMin = $div->actual_start_at
                                                            ? (int) round($div->planned_start_at->diffInMinutes($div->actual_start_at, false))
                                                            : null;
                                                    @endphp
                                                    <span class="flex items-center gap-1 shrink-0">
                                                        <span class="text-xs tabular-nums text-gray-400">{{ tenant_time($div->planned_start_at) }}</span>
                                                        @if ($driftMin !== null && abs($driftMin) < 1440)
                                                            @php
                                                                if ($driftMin < 0)      $driftCls = 'bg-blue-100 text-blue-700';
                                                                elseif ($driftMin === 0) $driftCls = 'bg-green-100 text-green-700';
                                                                elseif ($driftMin <= 5)  $driftCls = 'bg-green-100 text-green-700';
                                                                elseif ($driftMin <= 15) $driftCls = 'bg-amber-100 text-amber-700';
                                                                else                    $driftCls = 'bg-red-100 text-red-700';
                                                                $driftLabel = $driftMin < 0 ? abs($driftMin) . 'm early' : ($driftMin === 0 ? 'On time' : '+' . $driftMin . 'm');
                                                            @endphp
                                                            <span class="inline-block rounded px-1 py-0.5 text-xs font-medium {{ $driftCls }}">{{ $driftLabel }}</span>
                                                        @endif
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="text-xs text-gray-500 mt-0.5">{{ $div->competitionEvent->name }}</div>
                                            <div class="text-sm text-gray-800 mt-0.5">{{ $div->label }}</div>
                                            @if ($badgeClass)
                                                <span class="inline-block mt-1.5 px-2 py-0.5 rounded-full text-xs font-semibold {{ $badgeClass }}">
                                                    {{ ucfirst($div->status) }}
                                                </span>
                                            @endif
                                            @if ($div->status === 'complete' && $div->results->isNotEmpty())
                                                <div class="mt-2 space-y-1 border-t border-green-200 pt-2">
                                                    @foreach ($div->results->whereNotNull('placement')->where('disqualified', false)->sortBy('placement')->take(3) as $result)
                                                        @php $competitor = $result->enrolmentEvent?->competitor; @endphp
                                                        <div class="flex items-center gap-1.5 text-xs">
                                                            <span class="flex-none inline-block px-1.5 py-0.5 rounded text-xs font-bold {{ $placementColors[$result->placement] ?? 'bg-gray-100 text-gray-600' }}">
                                                                {{ $placementLabels[$result->placement - 1] ?? $result->placement . 'th' }}
                                                            </span>
                                                            <span class="truncate text-gray-700 {{ $result->placement === 1 ? 'font-bold' : '' }}">
                                                                @if ($result->disqualified)
                                                                    <span class="text-red-600">DQ</span>
                                                                @elseif ($competitor)
                                                                    {{ $competitor->first_name }} {{ $competitor->surname }}
                                                                @else
                                                                    &mdash;
                                                                @endif
                                                            </span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>
    @endif

</x-layouts.public>
