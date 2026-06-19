<x-filament-panels::page>
    @php $divisionList = $this->divisionList; @endphp
    @php $selectedComp = $this->competition_id ? \App\Models\Competition::find($this->competition_id) : null; @endphp
    @php $incompleteCount = $divisionList->where('type', 'division')->filter(fn ($item) => $item->division->status !== 'complete')->count(); @endphp

    {{-- Top bar: competition + location --}}
    <div
        class="mb-2 rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 dark:border-primary-800 dark:bg-primary-950/30"
        x-data="{}"
        x-on:livewire:navigated.window="$wire.$refresh()"
    >
        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-primary-700 dark:text-primary-400">Competition</p>
        <div class="flex flex-wrap gap-3 items-center">
            <x-filament::input.wrapper class="flex-1 min-w-48">
                <select wire:model.live="competition_id"
                    class="w-full block border-0 bg-white dark:bg-gray-800 py-1.5 text-sm text-gray-900 dark:text-white focus:ring-0">
                    <option value="">— Select competition —</option>
                    @foreach ($this->getCompetitions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </x-filament::input.wrapper>

            @php $locations = $this->getLocations(); @endphp
            @if (! empty($locations))
                <x-filament::input.wrapper class="min-w-40">
                    <select wire:model.live="filter_location"
                        class="w-full block border-0 bg-white dark:bg-gray-800 py-1.5 text-sm text-gray-900 dark:text-white focus:ring-0">
                        <option value="">— All locations —</option>
                        @foreach ($locations as $loc)
                            <option value="{{ $loc }}">{{ $loc }}</option>
                        @endforeach
                    </select>
                </x-filament::input.wrapper>
            @endif

            @if ($this->competition_id)
                <div class="relative flex items-center">
                    <x-heroicon-m-magnifying-glass class="absolute left-2 w-3.5 h-3.5 text-gray-400 pointer-events-none" />
                    <input
                        wire:model.live="search_code"
                        type="text"
                        inputmode="text"
                        autocomplete="off"
                        autocorrect="off"
                        autocapitalize="characters"
                        spellcheck="false"
                        placeholder="Code..."
                        class="pl-7 pr-6 py-1.5 w-28 text-sm rounded-md border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                    />
                    @if ($this->search_code)
                        <button wire:click="$set('search_code', null)" class="absolute right-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                            <x-heroicon-m-x-mark class="w-3.5 h-3.5" />
                        </button>
                    @endif
                </div>
            @endif

            @if ($this->competition_id && $selectedComp?->status === 'running' && ! $divisionList->isEmpty() && $incompleteCount > 0)
                <button wire:click="jumpToNextIncomplete"
                    class="inline-flex items-center gap-1 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1 text-xs text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    <x-heroicon-m-arrow-down-circle class="w-3.5 h-3.5" />
                    Next incomplete ({{ $incompleteCount }})
                </button>
            @endif
        </div>
    </div>

    {{-- Day tabs (multi-day competitions only) --}}
    @php $scoringDays = $this->getDays(); @endphp
    @if (count($scoringDays) > 1)
        <div x-data="{}" class="mb-2 flex gap-1 flex-wrap">
            @foreach ($scoringDays as $dayId => $dayLabel)
                <button
                    type="button"
                    x-on:click="$wire.set('competition_day_id', {{ $dayId }})"
                    :class="$wire.competition_day_id == {{ $dayId }}
                        ? 'bg-primary-600 text-white border-primary-600'
                        : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:border-primary-400'"
                    class="rounded-lg border px-4 py-1.5 text-sm font-medium transition-all duration-150 active:scale-95"
                >
                    {{ $dayLabel }}
                </button>
            @endforeach
        </div>
    @endif

    @if (! $this->competition_id)
        <p class="text-center text-gray-400 py-12">Select a competition to begin scoring.</p>
    @elseif ($selectedComp?->status !== 'running')
        <p class="text-center text-gray-400 py-12">Competition is not running yet. Start the competition to begin scoring.</p>
    @elseif ($divisionList->isEmpty())
        <p class="text-center text-gray-400 py-12">
            @if ($this->search_code)
                No divisions match code "{{ $this->search_code }}".
            @elseif ($this->filter_location)
                No divisions assigned to {{ $this->filter_location }}.
            @else
                No divisions found.
            @endif
        </p>
    @else
        <style>
            @keyframes scoring-row-pulse {
                0%   { box-shadow: 0 0 0 0 rgba(99,102,241,.6); }
                35%  { box-shadow: 0 0 0 10px rgba(99,102,241,.2); }
                100% { box-shadow: 0 0 0 16px rgba(99,102,241,0); }
            }
            .scoring-row-pulse {
                animation: scoring-row-pulse .8s ease-out forwards;
            }
            @keyframes scoring-row-return {
                0%   { box-shadow: 0 0 0 3px rgba(99,102,241,.9); background-image: linear-gradient(rgba(99,102,241,.18), rgba(99,102,241,.18)); }
                40%  { box-shadow: 0 0 0 5px rgba(99,102,241,.3); background-image: linear-gradient(rgba(99,102,241,.12), rgba(99,102,241,.12)); }
                100% { box-shadow: 0 0 0 2px rgba(99,102,241,.5); background-image: linear-gradient(rgba(99,102,241,0),  rgba(99,102,241,0)); }
            }
            .scoring-row-return {
                animation: scoring-row-return 2.5s ease-out forwards;
            }
            input[type=number]::-webkit-outer-spin-button,
            input[type=number]::-webkit-inner-spin-button {
                -webkit-appearance: none;
                margin: 0;
            }
            input[type=number] {
                -moz-appearance: textfield;
            }
            button { touch-action: manipulation; }
        </style>

        {{-- Overall progress --}}
        @php
            $divisionItems  = $divisionList->where('type', 'division');
            $totalDivisions = $divisionItems->count();
            $doneDivisions  = $divisionItems->filter(fn ($item) => $item->division->status === 'complete')->count();
            $progressPct    = $totalDivisions > 0 ? round($doneDivisions / $totalDivisions * 100) : 0;
        @endphp
        <div class="flex items-center gap-3 mb-2 px-1">
            <div class="flex-1 rounded-full bg-gray-200 dark:bg-gray-700" style="height:5px">
                <div class="rounded-full bg-green-500 transition-all" style="height:5px;width:{{ $progressPct }}%"></div>
            </div>
            <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $doneDivisions }}&thinsp;/&thinsp;{{ $totalDivisions }} complete</span>
        </div>

        {{-- Division list --}}
        <div class="space-y-1 mb-4" wire:key="division-list-{{ $this->filter_location ?? 'all' }}-{{ $this->search_code ?? '' }}">
            @foreach ($divisionList as $item)
                @if ($item->type === 'break')
                    @php $b = $item->break; @endphp
                    <div wire:key="break-{{ $b->id }}" class="flex items-center gap-3 py-1 px-1">
                        <div class="flex-1 border-t border-dashed border-gray-300 dark:border-gray-600"></div>
                        <span class="flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500 shrink-0">
                            <x-heroicon-m-pause-circle class="w-3.5 h-3.5 shrink-0" />
                            <span class="font-medium">{{ $b->name }}</span>
                            <span>{{ \Carbon\Carbon::parse('1970-01-01 ' . $b->start_time)->format('g:ia') }}–{{ \Carbon\Carbon::parse('1970-01-01 ' . $b->start_time)->addMinutes($b->duration_minutes)->format('g:ia') }}</span>
                            <span class="text-gray-300 dark:text-gray-600">({{ $b->duration_minutes }}min)</span>
                        </span>
                        <div class="flex-1 border-t border-dashed border-gray-300 dark:border-gray-600"></div>
                    </div>
                    @continue
                @endif
                @php
                    $div        = $item->division;
                    $inProgress = $item->scoring_started && $div->status !== 'complete';
                    $rowAccent  = $div->status === 'complete' ? 'accent-green' : ($inProgress ? 'accent-amber' : 'accent-gray');
                    $rowClass   = $div->status === 'complete'
                        ? 'bg-green-50 border-green-300 dark:bg-green-900/20 dark:border-green-700'
                        : ($inProgress
                            ? 'bg-amber-50 border-amber-300 dark:bg-amber-900/20 dark:border-amber-700'
                            : 'bg-white border-gray-200 shadow-sm dark:bg-gray-900 dark:border-gray-700');
                    $textClass  = $div->status === 'complete'
                        ? 'text-green-800 dark:text-green-300'
                        : ($inProgress
                            ? 'text-amber-800 dark:text-amber-300'
                            : 'text-gray-900 dark:text-white');
                @endphp
                <div
                    wire:key="division-{{ $div->id }}"
                    wire:click="navigateToDivision({{ $div->id }})"
                    x-data="{ tapped: false }"
                    @mousedown="tapped = true"
                    :class="tapped ? 'ring-2 ring-primary-400 dark:ring-primary-500 opacity-75' : ''"
                    class="relative flex items-center justify-between gap-3 rounded-lg border border-l-4 px-4 py-3 cursor-pointer
                        {{ $rowClass }} {{ $rowAccent }}
                        hover:border-primary-300 dark:hover:border-primary-600
                        {{ $this->highlight_division === $div->id ? 'scoring-row-return' : '' }}"
                    @if ($this->highlight_division === $div->id)
                        x-init="$nextTick(() => { $el.scrollIntoView({ behavior: 'instant', block: 'center' }); setTimeout(() => { const u = new URL(location.href); u.searchParams.delete('highlight_division'); history.replaceState({}, '', u); }, 2500); })"
                    @endif
                >
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="font-mono text-sm font-bold shrink-0 {{ $textClass }}">{{ $div->code }}</span>
                        <div class="min-w-0">
                            <p class="text-sm font-medium {{ $textClass }} truncate">
                                {{ $div->competitionEvent->name }}
                                @if ($div->location_label)
                                    <span class="font-normal text-gray-500 dark:text-gray-400">— {{ $div->location_label }}</span>
                                @endif
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $div->label }}</p>
                            @php
                                $fmtDuration = function (?int $secs): ?string {
                                    if ($secs === null) return null;
                                    $m = (int) floor(abs($secs) / 60);
                                    $s = abs($secs) % 60;
                                    if ($m === 0) return "{$s}s";
                                    return $s > 0 ? "{$m}m {$s}s" : "{$m}m";
                                };
                            @endphp
                            @if ($div->actual_start_at && $div->actual_end_at && $item->actual_seconds <= 86400)
                                @php
                                    $startDiff    = $div->planned_start_at ? (int) round($div->planned_start_at->diffInSeconds($div->actual_start_at, false) / 60) : null;
                                    $durationDiff = ($item->planned_seconds && $item->actual_seconds !== null) ? (int) round(($item->actual_seconds - $item->planned_seconds) / 60) : null;
                                    $showStartDiff    = $startDiff !== null && $startDiff !== 0 && abs($startDiff) <= 1440;
                                    $showDurationDiff = $durationDiff !== null && $durationDiff !== 0 && abs($durationDiff) <= 1440;
                                    $actualDurStr     = $fmtDuration($item->actual_seconds);
                                @endphp
                                <p class="text-xs text-gray-400 dark:text-gray-500 flex items-center gap-1">
                                    <span>{{ $div->actual_start_at->format('g:ia') }}</span>
                                    @if ($showStartDiff)
                                        <span class="{{ $startDiff > 0 ? 'text-amber-500 dark:text-amber-400' : 'text-green-600 dark:text-green-400' }}">
                                            ({{ $startDiff > 0 ? '+' : '' }}{{ $startDiff }})
                                        </span>
                                    @endif
                                    @if ($actualDurStr)
                                        <span class="ml-1">{{ $actualDurStr }}</span>
                                        @if ($showDurationDiff)
                                            <span class="{{ $durationDiff > 0 ? 'text-amber-500 dark:text-amber-400' : 'text-green-600 dark:text-green-400' }}">
                                                ({{ $durationDiff > 0 ? '+' : '' }}{{ $durationDiff }})
                                            </span>
                                        @endif
                                    @endif
                                </p>
                            @elseif ($div->planned_start_at)
                                <p class="text-xs text-gray-400 dark:text-gray-500 flex items-center gap-1">
                                    <span>{{ $div->planned_start_at->format('g:ia') }}</span>
                                    @if ($item->planned_seconds)
                                        <span class="ml-1">{{ $fmtDuration($item->planned_seconds) }}</span>
                                    @endif
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center gap-3 shrink-0">
                        @if ($div->status !== 'complete')
                            <span class="text-xs text-gray-500 flex flex-col sm:flex-row sm:gap-1 items-end sm:items-center">
                                <span>{{ $item->checked_in_count }} checked in</span>
                                @if ($item->scoring_started || $item->competitors_count !== $item->checked_in_count)
                                    <span><span class="hidden sm:inline">&middot; </span>{{ $item->competitors_count }} competing</span>
                                @endif
                                @if ($inProgress && $item->division->scoring_count > 0)
                                    <span class="text-amber-600 dark:text-amber-400"><span class="hidden sm:inline">&middot; </span>{{ $item->division->scoring_count }}/{{ $item->competitors_count }} scored</span>
                                @endif
                            </span>
                        @endif

                        @if ($div->status === 'complete')
                            @if ($item->top_results->isNotEmpty())
                                <div class="flex flex-col gap-0.5 text-right">
                                    @foreach ($item->top_results as $r)
                                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-[140px]">
                                            @switch($r->placement)
                                                @case(1) 🥇 @break
                                                @case(2) 🥈 @break
                                                @case(3) 🥉 @break
                                            @endswitch
                                            {{ $r->name }}
                                        </p>
                                    @endforeach
                                </div>
                            @endif
                            <x-heroicon-m-check-circle class="w-5 h-5 text-success-500" />
                        @elseif ($item->locked_by_other)
                            <x-heroicon-m-lock-closed class="w-4 h-4 text-amber-500 dark:text-amber-400" title="{{ $item->locked_by_other }} is scoring" />
                        @elseif ($inProgress)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">In progress</span>
                        @else
                            <x-heroicon-m-chevron-right wire:loading.remove wire:target="navigateToDivision({{ $div->id }})" class="w-4 h-4 text-gray-400" />
                            <svg wire:loading wire:target="navigateToDivision({{ $div->id }})" class="animate-spin w-4 h-4 text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                            </svg>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
