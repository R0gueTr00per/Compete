<x-filament-panels::page>
    @php
        $competition    = $this->getCompetition();
        $locations      = $this->getLocations();
        $divisions      = $this->getDivisions();
        $myDivisionIds  = $this->getMyDivisionIds();
        $shareUrl       = $competition?->isPublicScheduleAvailable()
            ? config('app.scheme') . '://' . app('tenant')->slug . '.' . config('app.domain') . '/schedule/' . $competition->id
            : null;
        $compDays       = $competition?->competitionDays->sortBy('date') ?? collect();
        $todayDayId     = $compDays->firstWhere('date', now()->toDateString())?->id;
        $initialDay     = $compDays->isNotEmpty()
            ? (string) ($todayDayId ?? $compDays->first()?->id ?? 'all')
            : 'all';
        $compDateStr    = $competition ? tenant_date($competition->competition_date) : '';
        $compStartStr   = $competition?->start_time ? tenant_time($competition->start_time) : null;
        $compEndStr     = $competition?->end_time   ? tenant_time($competition->end_time)   : null;
        $isMultiDay     = $compDays->isNotEmpty();
        $dayInfoJs      = $compDays->mapWithKeys(fn ($d) => [
            (string) $d->id => [
                'date'  => tenant_date($d->date),
                'start' => $d->start_time ? tenant_time($d->start_time) : null,
                'end'   => $d->end_time   ? tenant_time($d->end_time)   : null,
            ]
        ])->all();
    @endphp

    @if (! $competition)
        <x-filament::section>
            <p class="text-center text-gray-500 py-8">No active competition found.</p>
        </x-filament::section>
    @else
        <div
            x-data="{
                shareOpen: false,
                copied: false,
                selected: null,
                day: '{{ $initialDay }}',
                dayInfo: {{ json_encode($dayInfoJs) }},
                async copyQr() {
                    const svg = this.$refs.qrcode.querySelector('svg');
                    const svgData = new XMLSerializer().serializeToString(svg);
                    const blob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
                    const url = URL.createObjectURL(blob);
                    const img = new Image();
                    await new Promise(resolve => { img.onload = resolve; img.src = url; });
                    const canvas = document.createElement('canvas');
                    canvas.width = img.naturalWidth;
                    canvas.height = img.naturalHeight;
                    canvas.getContext('2d').drawImage(img, 0, 0);
                    URL.revokeObjectURL(url);
                    canvas.toBlob(async png => {
                        await navigator.clipboard.write([new ClipboardItem({ 'image/png': png })]);
                        this.copied = true;
                        setTimeout(() => this.copied = false, 2000);
                    }, 'image/png');
                }
            }"
            @sched-panel-close.window="selected = null"
        >

        {{-- Competition header --}}
        <x-filament::section>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-500">
                @if ($isMultiDay)
                    <span x-text="(day !== 'all' && dayInfo[day]?.date) || {{ json_encode($compDateStr) }}"></span>
                @else
                    <span>{{ $compDateStr }}</span>
                @endif
                @if ($competition->location_name)
                    @if ($competition->location_url)
                        <span>&middot; <a href="{{ $competition->location_url }}" target="_blank" rel="noopener noreferrer" class="hover:underline">{{ $competition->location_name }}</a></span>
                    @else
                        <span>&middot; {{ $competition->location_name }}</span>
                    @endif
                @endif
                @if ($isMultiDay)
                    <span x-show="(day !== 'all' && dayInfo[day]?.start) || {{ json_encode(!!$compStartStr) }}"
                          x-cloak
                          x-text="'· Starts ' + ((day !== 'all' && dayInfo[day]?.start) || {{ json_encode($compStartStr ?? '') }})"></span>
                    <span x-show="(day !== 'all' && dayInfo[day]?.end) || {{ json_encode(!!$compEndStr) }}"
                          x-cloak
                          x-text="'· Ends ' + ((day !== 'all' && dayInfo[day]?.end) || {{ json_encode($compEndStr ?? '') }})"></span>
                @else
                    @if ($competition->start_time)
                        <span>&middot; Starts {{ tenant_time($competition->start_time) }}</span>
                    @endif
                    @if ($competition->end_time)
                        <span>&middot; Ends {{ tenant_time($competition->end_time) }}</span>
                    @endif
                @endif
                <span class="sm:ml-auto flex items-center gap-2 text-xs text-gray-400">
                    Updated {{ tenant_time(now()) }}
                    <x-filament::button size="xs" color="gray" wire:click="$refresh">
                        Refresh
                    </x-filament::button>
                    @if ($shareUrl)
                        <button
                            type="button"
                            x-on:click="shareOpen = true"
                            title="Share schedule"
                            class="inline-flex items-center gap-1 px-2 py-1 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 transition text-xs"
                        >
                            <x-heroicon-o-arrow-up-on-square class="w-3.5 h-3.5" />
                            Share
                        </button>
                    @endif
                </span>
            </div>

        </x-filament::section>

        {{-- Share modal --}}
        @if ($shareUrl)
            <div
                x-show="shareOpen"
                x-on:click.self="shareOpen = false"
                x-on:keydown.escape.window="shareOpen = false"
                x-transition
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                style="display: none;"
            >
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl p-6 max-w-sm w-full space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Share Schedule &amp; Results</h3>
                        <button type="button" x-on:click="shareOpen = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 -mr-1 p-1">
                            <x-heroicon-o-x-mark class="w-5 h-5" />
                        </button>
                    </div>
                    <div x-ref="qrcode" class="flex justify-center">
                        <x-qr-code :value="$shareUrl" :size="220" />
                    </div>
                    <div class="rounded-lg bg-gray-100 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 px-3 py-2 text-center">
                        <a href="{{ $shareUrl }}" target="_blank" style="color: #2563eb; font-size: 0.875rem; word-break: break-all;" class="hover:underline">
                            {{ $shareUrl }}
                        </a>
                    </div>
                    <div class="flex justify-center">
                        <button
                            type="button"
                            x-on:click="copyQr()"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 transition"
                        >
                            <svg x-show="!copied" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-4 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                            <svg x-show="copied" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            <span x-text="copied ? 'Copied!' : 'Copy QR code'"></span>
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Schedule --}}
        @if ($divisions->isEmpty())
            <x-filament::section>
                <p class="text-center text-gray-400 py-12">No divisions scheduled yet.</p>
            </x-filament::section>
        @else
            @php
                $activeLocations = collect($locations)->filter(fn ($l) => $divisions->has($l))->values();
                $allDivisions    = $divisions->flatten(1);
                $placementLabels = ['1st', '2nd', '3rd'];
                $placementColors = [
                    1 => 'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-800 dark:text-yellow-300 border border-yellow-300 dark:border-yellow-700',
                    2 => 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600',
                    3 => 'bg-orange-100 dark:bg-orange-900/40 text-orange-800 dark:text-orange-300 border border-orange-300 dark:border-orange-700',
                ];
                $competitionBreaks = $competition->breaks;
                $compDate    = \Carbon\Carbon::parse($competition->competition_date)->format('Y-m-d');
                // org-admin approach: each day gets its own timeline with breaks computed for that day's date
                $buildDayTimeline = function ($dayDivisions, $dayDate, $dayId = null) use ($competitionBreaks) {
                    $dayBreaks = $competitionBreaks
                        ->filter(fn ($b) => (string) $b->competition_day_id === (string) $dayId)
                        ->map(fn ($b) => [
                            'name'      => $b->name,
                            'start_str' => substr($b->start_time, 0, 5),
                            'end_str'   => $b->endTime(),
                            'ts'        => \Carbon\Carbon::parse($dayDate . ' ' . $b->start_time)->timestamp,
                        ])->sortBy('ts')->values();
                    $timeline = [];
                    $bIdx = 0;
                    foreach ($dayDivisions->sortBy('running_order') as $div) {
                        if ($div->planned_start_at) {
                            while ($bIdx < $dayBreaks->count() && $dayBreaks[$bIdx]['ts'] <= $div->planned_start_at->timestamp) {
                                $timeline[] = array_merge(['type' => 'break'], $dayBreaks[$bIdx]);
                                $bIdx++;
                            }
                        }
                        $timeline[] = ['type' => 'div', 'div' => $div];
                    }
                    while ($bIdx < $dayBreaks->count()) {
                        $timeline[] = array_merge(['type' => 'break'], $dayBreaks[$bIdx]);
                        $bIdx++;
                    }
                    return $timeline;
                };
                // Fallback colSortedBreaks for single-day competitions
                $colSortedBreaks = $competitionBreaks->map(fn ($b) => [
                    'name'      => $b->name,
                    'start_str' => substr($b->start_time, 0, 5),
                    'end_str'   => $b->endTime(),
                    'ts'        => \Carbon\Carbon::parse($compDate . ' ' . $b->start_time)->timestamp,
                ])->sortBy('ts')->values();
            @endphp

            {{-- Day filter --}}
            @if ($compDays->isNotEmpty())
                <div class="mb-3 flex items-center gap-2 flex-wrap">
                    @foreach ($compDays as $cday)
                        <button type="button"
                            x-on:click="day = '{{ $cday->id }}'"
                            :class="day === '{{ $cday->id }}' ? 'bg-primary-500 text-white border-primary-500' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-600 hover:border-primary-400'"
                            class="px-3 py-1 rounded-full text-xs font-medium border transition-colors">
                            {{ tenant_date($cday->date) }}@if($cday->label) &mdash; {{ $cday->label }}@endif
                        </button>
                    @endforeach
                </div>
            @endif

            {{-- Legend --}}
            <div class="mb-3 flex flex-wrap gap-4 text-xs text-gray-600 dark:text-gray-400">
                <span class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-sm inline-block bg-green-100 dark:bg-green-900/40 border border-green-300 dark:border-green-700"></span> Complete
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-sm inline-block bg-indigo-100 dark:bg-indigo-900/40 border border-indigo-300 dark:border-indigo-700"></span> Scheduled
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-sm inline-block bg-white dark:bg-slate-800 border border-gray-200 dark:border-gray-600 ring-2 ring-gray-800 dark:ring-white"></span> My division
                </span>
            </div>

            {{-- ── Mobile: compact all-mats grid ── --}}
            <div class="sm:hidden" :class="selected !== null ? 'pb-56' : 'pb-2'">
                <div class="flex gap-1.5">
                    @foreach ($activeLocations as $location)
                        @php
                            if ($compDays->isNotEmpty()) {
                                $mobilePerDay = [];
                                foreach ($compDays as $cday) {
                                    $mobilePerDay[$cday->id] = $buildDayTimeline(
                                        $divisions[$location]->where('competition_day_id', $cday->id),
                                        \Carbon\Carbon::parse($cday->date)->format('Y-m-d'),
                                        $cday->id
                                    );
                                }
                            } else {
                                $mobileTimeline = [];
                                $mBIdx = 0;
                                foreach ($divisions[$location] as $div) {
                                    if ($div->planned_start_at) {
                                        while ($mBIdx < $colSortedBreaks->count()
                                            && $colSortedBreaks[$mBIdx]['ts'] <= $div->planned_start_at->timestamp) {
                                            $mobileTimeline[] = array_merge(['type' => 'break'], $colSortedBreaks[$mBIdx]);
                                            $mBIdx++;
                                        }
                                    }
                                    $mobileTimeline[] = ['type' => 'div', 'div' => $div];
                                }
                                while ($mBIdx < $colSortedBreaks->count()) {
                                    $mobileTimeline[] = array_merge(['type' => 'break'], $colSortedBreaks[$mBIdx]);
                                    $mBIdx++;
                                }
                            }
                        @endphp
                        <div class="flex-1 min-w-0">
                            <div class="text-center text-xs font-bold text-gray-500 dark:text-gray-400 truncate mb-2 pb-1.5 border-b border-gray-200 dark:border-gray-700">
                                {{ $location }}
                            </div>
                            <div class="space-y-1">
                                @if ($compDays->isNotEmpty())
                                    @foreach ($compDays as $cday)
                                        <div x-show="day === '{{ $cday->id }}'">
                                            @foreach ($mobilePerDay[$cday->id] as $row)
                                                @if ($row['type'] === 'break')
                                                    <div class="w-full rounded bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 px-1 py-1.5 text-center mb-1">
                                                        <span class="text-xs font-semibold text-amber-700 dark:text-amber-400 leading-none">Break</span>
                                                    </div>
                                                @else
                                                    @php
                                                        $div = $row['div'];
                                                        $isMyDiv = in_array($div->id, $myDivisionIds);
                                                        $cardBg  = $div->status === 'complete'
                                                            ? 'bg-green-100 dark:bg-green-900/40 border-green-300 dark:border-green-700'
                                                            : 'bg-indigo-100 dark:bg-indigo-900/40 border-indigo-200 dark:border-indigo-700';
                                                        if ($isMyDiv) $cardBg .= ' ring-2 ring-gray-800 dark:ring-white';
                                                    @endphp
                                                    <button
                                                        type="button"
                                                        @click="selected === {{ $div->id }} ? (selected = null, $dispatch('sched-panel-close')) : (selected = {{ $div->id }}, $dispatch('sched-panel-open', { id: {{ $div->id }} }))"
                                                        :class="selected === {{ $div->id }} ? 'ring-2 ring-offset-1 ring-blue-500' : ''"
                                                        class="w-full rounded border {{ $cardBg }} px-1.5 py-1.5 text-left transition-shadow mb-1"
                                                    >
                                                        <div class="flex items-center justify-between gap-1">
                                                            <span class="font-mono text-xs font-bold leading-none text-gray-800 dark:text-white">{{ $div->code }}</span>
                                                            @if ($div->status === 'complete')
                                                                <svg class="flex-none h-2.5 w-2.5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                                </svg>
                                                            @elseif ($div->planned_start_at)
                                                                <span class="text-gray-400 leading-none tabular-nums" style="font-size:9px">{{ tenant_time($div->planned_start_at) }}</span>
                                                            @endif
                                                        </div>
                                                        <div class="text-xs text-gray-500 dark:text-gray-400 leading-tight mt-0.5 truncate">{{ $div->competitionEvent->name }}</div>
                                                        <div class="text-gray-600 dark:text-gray-300 leading-tight truncate" style="font-size:10px">{{ $div->label }}</div>
                                                    </button>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endforeach
                                @else
                                    @foreach ($mobileTimeline as $row)
                                        @if ($row['type'] === 'break')
                                            <div class="w-full rounded bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 px-1 py-1.5 text-center">
                                                <span class="text-xs font-semibold text-amber-700 dark:text-amber-400 leading-none">Break</span>
                                            </div>
                                        @else
                                            @php
                                                $div = $row['div'];
                                                $isMyDiv = in_array($div->id, $myDivisionIds);
                                                $cardBg  = $div->status === 'complete'
                                                    ? 'bg-green-100 dark:bg-green-900/40 border-green-300 dark:border-green-700'
                                                    : 'bg-indigo-100 dark:bg-indigo-900/40 border-indigo-200 dark:border-indigo-700';
                                                if ($isMyDiv) $cardBg .= ' ring-2 ring-gray-800 dark:ring-white';
                                            @endphp
                                            <button
                                                type="button"
                                                @click="selected === {{ $div->id }} ? (selected = null, $dispatch('sched-panel-close')) : (selected = {{ $div->id }}, $dispatch('sched-panel-open', { id: {{ $div->id }} }))"
                                                :class="selected === {{ $div->id }} ? 'ring-2 ring-offset-1 ring-blue-500' : ''"
                                                class="w-full rounded border {{ $cardBg }} px-1.5 py-1.5 text-left transition-shadow"
                                            >
                                                <div class="flex items-center justify-between gap-1">
                                                    <span class="font-mono text-xs font-bold leading-none text-gray-800 dark:text-white">{{ $div->code }}</span>
                                                    @if ($div->status === 'complete')
                                                        <svg class="flex-none h-2.5 w-2.5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                        </svg>
                                                    @elseif ($div->planned_start_at)
                                                        <span class="text-gray-400 leading-none tabular-nums" style="font-size:9px">{{ tenant_time($div->planned_start_at) }}</span>
                                                    @endif
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400 leading-tight mt-0.5 truncate">{{ $div->competitionEvent->name }}</div>
                                                <div class="text-gray-600 dark:text-gray-300 leading-tight truncate" style="font-size:10px">{{ $div->label }}</div>
                                            </button>
                                        @endif
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- ── Mobile: slide-up detail panel (teleported to escape Filament stacking context) ── --}}
            <template x-teleport="body">
            <div
                x-data="{ selected: null }"
                @sched-panel-open.window="selected = $event.detail.id"
                @sched-panel-close.window="selected = null"
                x-show="selected !== null"
                style="display:none;"
                class="sm:hidden fixed inset-x-0 bottom-0 z-[200] bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 shadow-xl rounded-t-xl"
            >
                <div class="flex items-center justify-between px-4 pt-3 pb-2 border-b border-gray-100 dark:border-gray-700">
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Division Details</span>
                    <button type="button" @click="selected = null; $dispatch('sched-panel-close')" class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="px-4 py-4 overflow-y-auto max-h-48">
                    @foreach ($allDivisions as $div)
                        <div x-show="selected === {{ $div->id }}" style="display:none;">
                            <div class="flex items-start gap-3">
                                <div class="font-mono text-xl font-bold text-gray-900 dark:text-white leading-none pt-0.5">{{ $div->code }}</div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $div->competitionEvent->name }}</div>
                                    <div class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $div->label }}</div>
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
                                                    if ($driftMin < 0)      $driftCls = 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300';
                                                    elseif ($driftMin === 0) $driftCls = 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300';
                                                    elseif ($driftMin <= 5)  $driftCls = 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300';
                                                    elseif ($driftMin <= 15) $driftCls = 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300';
                                                    else                    $driftCls = 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300';
                                                    $driftLabel = $driftMin < 0 ? abs($driftMin) . 'm early' : ($driftMin === 0 ? 'On time' : '+' . $driftMin . 'm');
                                                @endphp
                                                <span class="inline-block rounded px-1 py-0.5 text-xs font-medium {{ $driftCls }}">{{ $driftLabel }}</span>
                                            @endif
                                        </div>
                                    @endif
                                    @if (in_array($div->id, $myDivisionIds))
                                        <span class="inline-block mt-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-800 text-white dark:bg-white dark:text-gray-900">Registered</span>
                                    @endif
                                </div>
                            </div>

                            @if ($div->status === 'complete')
                                @php
                                    $placements = $div->activeEnrolmentEvents
                                        ->filter(fn ($ee) => $ee->result?->placement)
                                        ->sortBy(fn ($ee) => $ee->result->placement)
                                        ->take(3);
                                @endphp
                                @if ($placements->isNotEmpty())
                                    <div class="mt-3 space-y-1.5 border-t border-gray-100 dark:border-gray-700 pt-3">
                                        @foreach ($placements as $ee)
                                            @php $pName = $ee->enrolment->competitor?->full_name ?? '—'; @endphp
                                            <div class="flex items-center gap-2 text-sm">
                                                <span class="flex-none inline-block px-2 py-0.5 rounded text-xs font-bold {{ $placementColors[$ee->result->placement] ?? 'bg-gray-100 text-gray-600' }}">
                                                    {{ $placementLabels[$ee->result->placement - 1] ?? $ee->result->placement . 'th' }}
                                                </span>
                                                <span class="text-gray-700 dark:text-gray-300 {{ $ee->result->placement === 1 ? 'font-bold' : '' }}">
                                                    {{ $pName }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            </template>

            {{-- ── Desktop: full-detail horizontal scroll ── --}}
            <div class="hidden sm:block w-full overflow-x-auto px-1 pt-1 pb-4">
                <div class="flex gap-4 items-start" style="min-width: max-content;">
                    @foreach ($activeLocations as $location)
                        @php
                            if ($compDays->isNotEmpty()) {
                                $desktopPerDay = [];
                                foreach ($compDays as $cday) {
                                    $desktopPerDay[$cday->id] = $buildDayTimeline(
                                        $divisions[$location]->where('competition_day_id', $cday->id),
                                        \Carbon\Carbon::parse($cday->date)->format('Y-m-d'),
                                        $cday->id
                                    );
                                }
                            } else {
                                $desktopTimeline = [];
                                $dBIdx = 0;
                                foreach ($divisions[$location] as $div) {
                                    if ($div->planned_start_at) {
                                        while ($dBIdx < $colSortedBreaks->count()
                                            && $colSortedBreaks[$dBIdx]['ts'] <= $div->planned_start_at->timestamp) {
                                            $desktopTimeline[] = array_merge(['type' => 'break'], $colSortedBreaks[$dBIdx]);
                                            $dBIdx++;
                                        }
                                    }
                                    $desktopTimeline[] = ['type' => 'div', 'div' => $div];
                                }
                                while ($dBIdx < $colSortedBreaks->count()) {
                                    $desktopTimeline[] = array_merge(['type' => 'break'], $colSortedBreaks[$dBIdx]);
                                    $dBIdx++;
                                }
                            }
                        @endphp
                        <div class="flex-none w-64">
                            <h2 class="text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-3 pb-2 border-b-2 border-gray-200 dark:border-gray-700">
                                {{ $location }}
                            </h2>

                            <div class="space-y-2">
                                @if ($compDays->isNotEmpty())
                                    @foreach ($compDays as $cday)
                                        <div x-show="day === '{{ $cday->id }}'">
                                            @foreach ($desktopPerDay[$cday->id] as $row)
                                                @if ($row['type'] === 'break')
                                                    <div class="px-3 py-2 my-1 rounded bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700">
                                                        <div class="flex items-center gap-2">
                                                            <svg class="h-3.5 w-3.5 shrink-0 text-amber-600 dark:text-amber-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                                <path d="M5.75 3a.75.75 0 0 0-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 0 0 .75-.75V3.75A.75.75 0 0 0 7.25 3h-1.5ZM12.75 3a.75.75 0 0 0-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 0 0 .75-.75V3.75a.75.75 0 0 0-.75-.75h-1.5Z"/>
                                                            </svg>
                                                            <span class="text-xs font-semibold text-amber-700 dark:text-amber-400 truncate">{{ $row['name'] }}</span>
                                                        </div>
                                                        <div class="text-xs text-amber-600 dark:text-amber-500 mt-0.5 pl-5">{{ $row['start_str'] }}–{{ $row['end_str'] }}</div>
                                                    </div>
                                                @else
                                                    @php
                                                        $div = $row['div'];
                                                        $isMyDiv   = in_array($div->id, $myDivisionIds);
                                                        $cardClass = $div->status === 'complete'
                                                            ? 'bg-green-100 dark:bg-green-900/40 border-green-300 dark:border-green-700'
                                                            : 'bg-indigo-100 dark:bg-indigo-900/40 border-indigo-200 dark:border-indigo-700';
                                                        if ($isMyDiv) $cardClass .= ' ring-2 ring-gray-800 dark:ring-white';
                                                    @endphp
                                                    <div class="rounded-md border px-3 py-2 shadow-sm {{ $cardClass }}">
                                                        <div class="flex items-center justify-between gap-2">
                                                            <span class="font-mono text-xs font-bold text-gray-900 dark:text-white">{{ $div->code }}</span>
                                                            @if ($div->status === 'complete')
                                                                <x-heroicon-m-check-circle class="h-4 w-4 text-green-600 dark:text-green-400" />
                                                            @elseif ($div->planned_start_at)
                                                                @php
                                                                    $driftMin = $div->actual_start_at
                                                                        ? (int) round($div->planned_start_at->diffInMinutes($div->actual_start_at, false))
                                                                        : null;
                                                                @endphp
                                                                <span class="flex items-center gap-1 shrink-0">
                                                                    <span class="text-xs tabular-nums text-gray-400 dark:text-gray-500">{{ tenant_time($div->planned_start_at) }}</span>
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
                                                        <div class="text-xs text-gray-600 dark:text-gray-300 mt-0.5">{{ $div->competitionEvent->name }}</div>
                                                        <div class="text-xs font-medium text-gray-800 dark:text-gray-200 mt-0.5">{{ $div->label }}</div>
                                                        @if ($div->status === 'complete')
                                                            @php
                                                                $placements = $div->activeEnrolmentEvents
                                                                    ->filter(fn ($ee) => $ee->result?->placement)
                                                                    ->sortBy(fn ($ee) => $ee->result->placement)
                                                                    ->take(3);
                                                            @endphp
                                                            @foreach ($placements as $ee)
                                                                @php
                                                                    $pName = $ee->enrolment->competitor?->full_name ?? '—';
                                                                    $medal = match($ee->result->placement) { 1 => '🥇', 2 => '🥈', 3 => '🥉', default => $ee->result->placement . '.' };
                                                                @endphp
                                                                <div class="text-xs text-gray-700 dark:text-gray-300 mt-0.5">{{ $medal }} {{ $pName }}</div>
                                                            @endforeach
                                                        @endif
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endforeach
                                @else
                                    @foreach ($desktopTimeline as $row)
                                        @if ($row['type'] === 'break')
                                            <div class="px-3 py-2 my-1 rounded bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700">
                                                <div class="flex items-center gap-2">
                                                    <svg class="h-3.5 w-3.5 shrink-0 text-amber-600 dark:text-amber-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                        <path d="M5.75 3a.75.75 0 0 0-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 0 0 .75-.75V3.75A.75.75 0 0 0 7.25 3h-1.5ZM12.75 3a.75.75 0 0 0-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 0 0 .75-.75V3.75a.75.75 0 0 0-.75-.75h-1.5Z"/>
                                                    </svg>
                                                    <span class="text-xs font-semibold text-amber-700 dark:text-amber-400 truncate">{{ $row['name'] }}</span>
                                                </div>
                                                <div class="text-xs text-amber-600 dark:text-amber-500 mt-0.5 pl-5">{{ $row['start_str'] }}–{{ $row['end_str'] }}</div>
                                            </div>
                                        @else
                                            @php
                                                $div = $row['div'];
                                                $isMyDiv   = in_array($div->id, $myDivisionIds);
                                                $cardClass = $div->status === 'complete'
                                                    ? 'bg-green-100 dark:bg-green-900/40 border-green-300 dark:border-green-700'
                                                    : 'bg-indigo-100 dark:bg-indigo-900/40 border-indigo-200 dark:border-indigo-700';
                                                if ($isMyDiv) $cardClass .= ' ring-2 ring-gray-800 dark:ring-white';
                                            @endphp
                                            <div class="rounded-md border px-3 py-2 shadow-sm {{ $cardClass }}">
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="font-mono text-xs font-bold text-gray-900 dark:text-white">{{ $div->code }}</span>
                                                    @if ($div->status === 'complete')
                                                        <x-heroicon-m-check-circle class="h-4 w-4 text-green-600 dark:text-green-400" />
                                                    @elseif ($div->planned_start_at)
                                                        @php
                                                            $driftMin = $div->actual_start_at
                                                                ? (int) round($div->planned_start_at->diffInMinutes($div->actual_start_at, false))
                                                                : null;
                                                        @endphp
                                                        <span class="flex items-center gap-1 shrink-0">
                                                            <span class="text-xs tabular-nums text-gray-400 dark:text-gray-500">{{ tenant_time($div->planned_start_at) }}</span>
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
                                                <div class="text-xs text-gray-600 dark:text-gray-300 mt-0.5">{{ $div->competitionEvent->name }}</div>
                                                <div class="text-xs font-medium text-gray-800 dark:text-gray-200 mt-0.5">{{ $div->label }}</div>
                                                @if ($div->status === 'complete')
                                                    @php
                                                        $placements = $div->activeEnrolmentEvents
                                                            ->filter(fn ($ee) => $ee->result?->placement)
                                                            ->sortBy(fn ($ee) => $ee->result->placement)
                                                            ->take(3);
                                                    @endphp
                                                    @foreach ($placements as $ee)
                                                        @php
                                                            $pName = $ee->enrolment->competitor?->full_name ?? '—';
                                                            $medal = match($ee->result->placement) { 1 => '🥇', 2 => '🥈', 3 => '🥉', default => $ee->result->placement . '.' };
                                                        @endphp
                                                        <div class="text-xs text-gray-700 dark:text-gray-300 mt-0.5">{{ $medal }} {{ $pName }}</div>
                                                    @endforeach
                                                @endif
                                            </div>
                                        @endif
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

        @endif
        </div>{{-- /x-data --}}
    @endif
</x-filament-panels::page>
