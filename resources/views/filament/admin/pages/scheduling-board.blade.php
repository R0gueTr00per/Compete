<x-filament-panels::page>
    {{-- Warning: event types with no competitor target set --}}
    @if(!empty($missingTarget))
        <div class="mb-3 rounded-lg border border-warning-300 dark:border-warning-700 bg-warning-50 dark:bg-warning-900/20 px-4 py-3 text-sm text-warning-800 dark:text-warning-300">
            <span class="font-semibold">Schedule times cannot be calculated</span> for the following event
            {{ Str::plural('type', count($missingTarget)) }} — no competitor target is set:
            <span class="font-mono">{{ implode(', ', $missingTarget) }}</span>.
            Set a target in the <a href="{{ \App\Filament\OrgAdmin\Resources\CompetitionResource::getUrl('edit', ['record' => $this->getRecord()]) }}" class="underline font-medium">Event Types</a> tab.
        </div>
    @endif

    {{-- Breaks summary + planned end pill --}}
    @php $summaryRecord = $this->getRecord(); @endphp
    @if($breaks->isNotEmpty() || ($summaryRecord && $summaryRecord->end_time))
        <div class="mb-3 flex flex-wrap gap-2">
            @foreach($breaks as $break)
                <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 px-3 py-1 text-xs font-medium text-amber-800 dark:text-amber-300">
                    <x-heroicon-o-pause-circle class="h-3.5 w-3.5" />
                    {{ $break->name }}
                    · {{ \Carbon\Carbon::parse($break->start_time)->format('g:i a') }}
                    – {{ \Carbon\Carbon::parse($break->start_time)->addMinutes($break->duration_minutes)->format('g:i a') }}
                </span>
            @endforeach
            @if($summaryRecord && $summaryRecord->end_time)
                <span class="inline-flex items-center gap-1.5 rounded-full border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/60 px-3 py-1 text-xs font-medium text-gray-600 dark:text-gray-400">
                    <x-heroicon-o-flag class="h-3.5 w-3.5" />
                    Planned finish · {{ tenant_time($summaryRecord->end_time) }}
                </span>
            @endif
        </div>
    @endif

    @if(empty($columns))
        <div class="flex flex-col items-center justify-center py-16 text-center">
            <x-heroicon-o-map-pin class="h-12 w-12 text-gray-300 dark:text-gray-600 mb-4" />
            <p class="text-gray-500 dark:text-gray-400 font-medium">No locations defined</p>
            <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">
                Add locations to this competition in the
                <a href="{{ \App\Filament\Admin\Resources\CompetitionResource::getUrl('edit', ['record' => $this->getRecord()]) }}"
                   class="text-primary-600 underline">competition settings</a>
                to start scheduling.
            </p>
        </div>
    @else
        <div
            x-data="schedulingBoard(@this)"
            x-init="init()"
            @keydown.escape.window="clearSelection()"
            class="min-w-0 sched-board"
        >
            {{-- Desktop hint: shown when a location is armed --}}
            <div x-show="selectedLocation !== null" x-cloak
                 class="hidden sm:block mb-2 rounded-md bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-700 px-2 py-1.5 text-xs text-primary-700 dark:text-primary-300">
                <template x-if="selectedCount > 0">
                    <span><strong x-text="selectedCount"></strong> selected — drag any to move all, or double-click to assign to <strong x-text="selectedLocation"></strong>. Ctrl+click to add more. Esc to cancel.</span>
                </template>
                <template x-if="selectedCount === 0">
                    <span>Click a card to select it. Drag or double-click to assign to <strong x-text="selectedLocation"></strong>. Esc to cancel.</span>
                </template>
            </div>

            {{-- Mobile hint --}}
            <div class="sm:hidden mb-2 rounded-md bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-700 px-2 py-1.5 text-xs text-primary-700 dark:text-primary-300">
                <template x-if="selectedCount > 0">
                    <span>Card selected — drag to move, or tap a location header to assign.</span>
                </template>
                <template x-if="selectedCount === 0">
                    <span>Tap a card to select it, then drag or tap a location header to assign.</span>
                </template>
            </div>

            {{-- Merge bar --}}
            <div x-show="selectedCount >= 2" x-cloak
                 class="mb-2 flex items-center justify-between rounded-md border border-warning-200 dark:border-warning-700 bg-warning-50 dark:bg-warning-900/20 px-3 py-1.5">
                <span class="text-xs text-warning-700 dark:text-warning-300">
                    <strong x-text="selectedCount"></strong> divisions selected
                </span>
                <button @click="openMergeModal()" type="button"
                    class="rounded-md bg-warning-600 hover:bg-warning-700 px-3 py-1 text-xs font-medium text-white transition-colors">
                    Merge selected
                </button>
            </div>

            {{-- Mobile filter chips: full-width row above board --}}
            @if(count($eventTypes) > 1)
            <div class="sm:hidden mb-2 flex gap-1.5 overflow-x-auto pb-1">
                <button
                    type="button"
                    wire:click="$set('filterEventType', '')"
                    class="shrink-0 rounded-full border px-3 py-1 text-xs font-medium transition-colors whitespace-nowrap
                        {{ ! $filterEventType ? 'bg-primary-600 text-white border-primary-600' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-300 dark:border-gray-600' }}"
                >All</button>
                @foreach($eventTypes as $etId => $etName)
                    <button
                        type="button"
                        wire:click="$set('filterEventType', '{{ $etId }}')"
                        class="shrink-0 rounded-full border px-3 py-1 text-xs font-medium transition-colors whitespace-nowrap
                            {{ $filterEventType == $etId ? 'bg-primary-600 text-white border-primary-600' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-300 dark:border-gray-600' }}"
                    >{{ $etName }}</button>
                @endforeach
            </div>
            @endif

            @php
                $colCompDate = ($colComp = $this->getRecord()) && $colComp->competition_date
                    ? \Carbon\Carbon::parse($colComp->competition_date)->format('Y-m-d')
                    : null;
                $colSortedBreaks = $colCompDate
                    ? $breaks->map(fn ($b) => [
                        'name'      => $b->name,
                        'start_str' => substr($b->start_time, 0, 5),
                        'end_str'   => $b->endTime(),
                        'ts'        => \Carbon\Carbon::parse($colCompDate . ' ' . $b->start_time)->timestamp,
                    ])->sortBy('ts')->values()
                    : collect();
            @endphp
            <div
                class="flex gap-3 pb-6 px-1 py-1 overflow-x-auto"
                @mousedown.capture="onCardMousedown($event)"
                @touchstart.passive.capture="onTouchStart($event)"
                @touchend.capture="onTouchEnd($event)"
                @click.capture="onCardClick($event)"
            >
                {{-- Unassigned column --}}
                <div class="{{ $unassignedCollapsed ? 'w-28 shrink-0' : 'flex-1 min-w-[5rem]' }}">
                    <div class="mb-2 min-h-[2.5rem]">
                        <div class="flex items-center justify-between gap-1">
                            <span class="block text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 truncate">Unassigned</span>
                            <button
                                type="button"
                                wire:click="$toggle('unassignedCollapsed')"
                                class="shrink-0 rounded p-0.5 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                title="{{ $unassignedCollapsed ? 'Expand unassigned' : 'Collapse unassigned' }}"
                            >
                                @if($unassignedCollapsed)
                                    <x-heroicon-m-chevron-right class="h-4 w-4" />
                                @else
                                    <x-heroicon-m-chevron-left class="h-4 w-4" />
                                @endif
                            </button>
                        </div>
                        <span class="inline-block mt-0.5 rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs text-gray-500 dark:text-gray-400">
                            {{ count($divisionsByColumn['__unassigned__'] ?? []) }}
                        </span>
                    </div>

                    {{-- Desktop filter --}}
                    @if(count($eventTypes) > 1 && !$unassignedCollapsed)
                        <div class="hidden sm:block mb-2">
                            <select
                                wire:model.live="filterEventType"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-xs text-gray-700 dark:text-gray-300 py-1 px-2 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            >
                                <option value="">All event types</option>
                                @foreach($eventTypes as $etId => $etName)
                                    <option value="{{ $etId }}" {{ $filterEventType == $etId ? 'selected' : '' }}>{{ $etName }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div
                        class="sortable-col sched-col-bg rounded-lg p-2"
                        style="min-height: 300px; padding-bottom: 80px;"
                        data-location="__unassigned__"
                    >
                        @foreach($divisionsByColumn['__unassigned__'] ?? [] as $div)
                            @php
                                $hidden = $filterEventType && $div->competition_event_id != $filterEventType;
                            @endphp
                            <div
                                @if($hidden) style="display:none" @endif
                                @dblclick="assignToSelected({{ $div->id }})"
                            >
                                @if($unassignedCollapsed)
                                    @php
                                        $cColor = match(true) {
                                            $div->status === 'complete'              => 'sched-complete',
                                            $div->active_enrolment_events_count >= 2 => 'sched-full',
                                            $div->location_label !== null            => 'sched-assigned',
                                            default                                  => 'sched-pending',
                                        };
                                        $cDotStyle = match($cColor) {
                                            'sched-complete' => 'background-color:#16a34a',
                                            'sched-full'     => 'background-color:#4f46e5',
                                            'sched-assigned' => 'background-color:#d97706',
                                            default          => 'background-color:#6b7280',
                                        };
                                        $cEnrolled = $div->active_enrolment_events_count ?? 0;
                                        $cCap      = $div->max_competitors ?? null;
                                        $cDivData  = json_encode([
                                            'id'                   => $div->id,
                                            'code'                 => $div->code,
                                            'label'                => $div->label,
                                            'event'                => $div->competitionEvent->name,
                                            'competition_event_id' => $div->competition_event_id,
                                            'status'               => $div->status,
                                            'enrolled'             => $cEnrolled,
                                            'checkedIn'            => $div->checked_in_count ?? 0,
                                            'noneShowed'           => $cEnrolled > 0 && ($div->checked_in_count ?? 0) === 0 && $div->status !== 'complete',
                                            'maxCompetitors'       => $cCap,
                                        ]);
                                    @endphp
                                    <div
                                        data-id="{{ $div->id }}"
                                        data-division="{{ $cDivData }}"
                                        class="mb-1.5 rounded-md border shadow-sm {{ $cColor }} flex items-center gap-1.5 py-1 px-2 overflow-hidden"
                                    >
                                        <span class="w-2 h-2 rounded-full shrink-0" style="{{ $cDotStyle }}"></span>
                                        <span class="font-mono text-xs font-bold truncate text-gray-900 dark:text-white min-w-0">{{ $div->code ?: '—' }}</span>
                                        @if($cEnrolled > 0 || $cCap)
                                            <span class="text-xs tabular-nums shrink-0 ml-auto sched-text-meta">{{ $cEnrolled }}@if($cCap)<span class="opacity-60">/{{ $cCap }}</span>@endif</span>
                                        @endif
                                    </div>
                                @else
                                    @include('filament.admin.partials.scheduling-card', ['div' => $div])
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Location columns --}}
                @foreach($columns as $col)
                    <div class="flex-1 min-w-[5rem]">
                        <div class="mb-2 min-h-[2.5rem]">
                            <button
                                type="button"
                                @click="selectLocation('{{ addslashes($col) }}')"
                                :class="selectedLocation === '{{ addslashes($col) }}'
                                    ? 'text-primary-600 dark:text-primary-400 underline decoration-2 underline-offset-2'
                                    : 'text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400'"
                                class="block w-full truncate text-left text-xs font-semibold uppercase tracking-wider transition-colors"
                                title="{{ $col }}"
                            >{{ $col }}</button>
                            <span class="inline-block mt-0.5 rounded-full bg-primary-100 dark:bg-primary-900/40 px-2 py-0.5 text-xs text-primary-700 dark:text-primary-300">
                                {{ count($divisionsByColumn[$col] ?? []) }}
                            </span>
                        </div>
                        <div
                            class="sortable-col sched-col-bg rounded-lg p-2 transition-shadow"
                            :class="selectedLocation === '{{ addslashes($col) }}' ? 'ring-2 ring-primary-400 dark:ring-primary-600' : ''"
                            style="min-height: 300px; padding-bottom: 80px;"
                            data-location="{{ $col }}"
                        >
                            @php
                                // Build timeline: divisions in order with breaks and planned-finish inserted at the right position
                                $colTimeline = [];
                                $bIdx = 0;
                                $endInserted = false;
                                $colEndTs = ($colComp->end_time && $colCompDate)
                                    ? \Carbon\Carbon::parse($colCompDate . ' ' . $colComp->end_time)->timestamp
                                    : null;
                                foreach ($divisionsByColumn[$col] ?? [] as $div) {
                                    if ($div->planned_start_at) {
                                        while ($bIdx < $colSortedBreaks->count()
                                            && $colSortedBreaks[$bIdx]['ts'] <= $div->planned_start_at->timestamp) {
                                            $colTimeline[] = ['type' => 'break'] + $colSortedBreaks[$bIdx];
                                            $bIdx++;
                                        }
                                        if (! $endInserted && $colEndTs !== null && $div->planned_start_at->timestamp >= $colEndTs) {
                                            $colTimeline[] = ['type' => 'end', 'end_time' => $colComp->end_time];
                                            $endInserted = true;
                                        }
                                    }
                                    $colTimeline[] = ['type' => 'div', 'div' => $div];
                                }
                                if (! $endInserted && $colComp->end_time) {
                                    $colTimeline[] = ['type' => 'end', 'end_time' => $colComp->end_time];
                                }
                            @endphp
                            @foreach($colTimeline as $row)
                                @if($row['type'] === 'break')
                                    <div class="sched-break-row -mx-2 mt-2 mb-4 border-y border-amber-200 dark:border-amber-700 select-none"
                                         data-break-name="{{ $row['name'] }}"
                                         data-break-start="{{ $row['start_str'] }}"
                                         data-break-end="{{ $row['end_str'] }}">
                                        {{-- Mobile: compact tap target --}}
                                        <div class="sm:hidden px-1 py-1.5 text-center bg-amber-50 dark:bg-amber-900/20 cursor-pointer">
                                            <span class="text-xs font-semibold text-amber-700 dark:text-amber-400">Break</span>
                                        </div>
                                        {{-- Desktop: full display --}}
                                        <div class="hidden sm:block px-3 py-3 bg-amber-50 dark:bg-amber-900/20 cursor-default">
                                            <div class="flex items-center gap-2">
                                                <x-heroicon-m-pause class="h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
                                                <span class="text-sm font-semibold text-amber-700 dark:text-amber-400 truncate">{{ $row['name'] }}</span>
                                            </div>
                                            <div class="text-xs text-amber-600 dark:text-amber-500 mt-0.5 pl-6">{{ $row['start_str'] }}–{{ $row['end_str'] }}</div>
                                        </div>
                                    </div>
                                @elseif($row['type'] === 'end')
                                    <div class="-mx-2 mt-2 border-y border-gray-200 dark:border-gray-700 select-none">
                                        <div class="hidden sm:block px-3 py-3 bg-gray-50 dark:bg-gray-800/60 cursor-default">
                                            <div class="flex items-center gap-2">
                                                <x-heroicon-m-flag class="h-4 w-4 shrink-0 text-gray-500 dark:text-gray-400" />
                                                <span class="text-sm font-semibold text-gray-600 dark:text-gray-400">Planned finish</span>
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-500 mt-0.5 pl-6">{{ tenant_time($row['end_time']) }}</div>
                                        </div>
                                        <div class="sm:hidden px-1 py-1.5 text-center bg-gray-50 dark:bg-gray-800/60 cursor-default">
                                            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">End {{ tenant_time($row['end_time']) }}</span>
                                        </div>
                                    </div>
                                @else
                                    @include('filament.admin.partials.scheduling-card', ['div' => $row['div']])
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Merge confirmation modal --}}
            <div x-show="mergeModal.open" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center p-4"
                 @keydown.escape.window="closeMergeModal()">
                <div class="absolute inset-0 bg-black/50" @click="closeMergeModal()"></div>
                <div class="relative w-full max-w-md rounded-xl bg-white dark:bg-gray-800 shadow-xl p-6">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-1">Merge divisions</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        The following divisions will be merged into
                        <strong x-text="mergeModal.divisions[0]?.code"></strong>.
                        Others will be marked as Combined and removed from the schedule.
                    </p>

                    <ul class="mb-4 divide-y divide-gray-100 dark:divide-gray-700 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <template x-for="(div, i) in mergeModal.divisions" :key="div.id">
                            <li class="flex items-center justify-between px-3 py-2 text-sm"
                                :class="i === 0 ? 'bg-primary-50 dark:bg-primary-900/20' : 'bg-white dark:bg-gray-800'">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span x-show="i === 0" class="shrink-0 rounded-full bg-primary-600 px-1.5 py-0.5 text-xs font-medium text-white">Primary</span>
                                    <span class="font-mono font-bold text-gray-900 dark:text-white shrink-0" x-text="div.code"></span>
                                    <span class="truncate text-gray-500 dark:text-gray-400" x-text="div.label"></span>
                                </div>
                                <span class="ml-3 shrink-0 text-xs text-gray-400 dark:text-gray-500">
                                    <span x-text="div.enrolled"></span> registered
                                </span>
                            </li>
                        </template>
                    </ul>

                    <div x-show="!mergeModal.sameEventType"
                         class="mb-4 rounded-lg border border-danger-200 dark:border-danger-700 bg-danger-50 dark:bg-danger-900/20 px-3 py-2 text-sm text-danger-700 dark:text-danger-300">
                        All selected divisions must be the same event type to merge.
                    </div>

                    <div class="flex justify-end gap-3">
                        <button @click="closeMergeModal()" type="button"
                            class="rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            Cancel
                        </button>
                        <button @click="confirmMerge()" type="button"
                            :disabled="!mergeModal.sameEventType"
                            class="rounded-lg bg-warning-600 hover:bg-warning-700 disabled:opacity-50 disabled:cursor-not-allowed px-4 py-2 text-sm font-medium text-white transition-colors">
                            Confirm Merge
                        </button>
                    </div>
                </div>
            </div>

            {{-- Mobile detail panel — teleported to body to escape Filament stacking context --}}
            <template x-teleport="body">
    <div
        x-data="{ detailDivision: null, breakDetail: null, selectedLocation: null }"
        @sched-board-open.window="detailDivision = $event.detail.division; breakDetail = null"
        @sched-board-break.window="breakDetail = $event.detail; detailDivision = null"
        @sched-board-close.window="detailDivision = null; breakDetail = null"
        @sched-board-location.window="selectedLocation = $event.detail.location"
        x-show="detailDivision !== null || breakDetail !== null"
        style="display:none;"
        class="fixed inset-x-0 bottom-0 z-[200] sm:hidden"
    >
        <div class="relative bg-white dark:bg-gray-800 rounded-t-2xl shadow-xl px-3" style="padding-bottom: max(1rem, env(safe-area-inset-bottom, 0px));">
            <div @click="detailDivision = null; breakDetail = null; $dispatch('sched-board-close')" class="flex justify-center pt-2 pb-1 cursor-pointer" aria-label="Close">
                <div class="w-10 h-1 rounded-full bg-gray-300 dark:bg-gray-600"></div>
            </div>

            {{-- Break detail --}}
            <div x-show="breakDetail !== null" style="display:none;">
                <div class="flex items-center gap-2 mb-1">
                    <x-heroicon-m-pause class="h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
                    <span class="text-sm font-semibold text-amber-700 dark:text-amber-400" x-text="breakDetail?.name"></span>
                </div>
                <p class="text-xs text-amber-600 dark:text-amber-500 mb-2" x-text="breakDetail ? breakDetail.start_str + ' – ' + breakDetail.end_str : ''"></p>
            </div>

            {{-- Division detail --}}
            <div x-show="detailDivision !== null" style="display:none;">
                <div class="flex items-center gap-2 mb-0.5">
                    <span class="font-mono text-sm font-bold text-gray-900 dark:text-white shrink-0" x-text="detailDivision?.code"></span>
                    <span class="text-xs text-gray-500 dark:text-gray-400 truncate" x-text="detailDivision?.event"></span>
                </div>
                <p class="text-xs text-gray-700 dark:text-gray-200 mb-1 truncate" x-text="detailDivision?.label"></p>

                <div class="flex items-center gap-3 mb-2 text-xs text-gray-400 dark:text-gray-500">
                    <span class="capitalize" x-text="detailDivision?.status"></span>
                    <template x-if="(detailDivision?.maxCompetitors ?? 0) > 0">
                        <span>
                            <span x-text="detailDivision?.enrolled ?? 0"></span>/<span x-text="detailDivision?.maxCompetitors"></span> cap
                        </span>
                    </template>
                    <template x-if="!(detailDivision?.maxCompetitors) && (detailDivision?.enrolled ?? 0) > 0">
                        <span><span x-text="detailDivision?.enrolled"></span> registered</span>
                    </template>
                    <span x-show="(detailDivision?.checkedIn ?? 0) > 0" style="display:none;" class="text-success-600 dark:text-success-400">
                        <span x-text="detailDivision?.checkedIn"></span> checked in
                    </span>
                    <span x-show="detailDivision?.noneShowed" style="display:none;" class="text-warning-600 dark:text-warning-400">0 checked in</span>
                </div>
                <template x-if="(detailDivision?.maxCompetitors ?? 0) > 0">
                    <div class="rounded-full overflow-hidden h-1 bg-gray-200 dark:bg-gray-700 mb-2">
                        <div class="h-full rounded-full transition-all duration-500"
                             :style="`width: ${Math.min(100, Math.round(((detailDivision?.enrolled ?? 0) / detailDivision.maxCompetitors) * 100))}%; background-color: ${(() => { const p = Math.round(((detailDivision?.enrolled ?? 0) / detailDivision.maxCompetitors) * 100); return p >= 100 ? '#f87171' : p >= 80 ? '#fbbf24' : '#22c55e'; })()}`">
                        </div>
                    </div>
                </template>

                <template x-if="selectedLocation">
                    <button
                        @click="$dispatch('sched-board-assign'); detailDivision = null; breakDetail = null"
                        class="w-full rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium py-2 text-center transition-colors"
                    >
                        Assign to <span x-text="selectedLocation"></span>
                    </button>
                </template>
            </div>
        </div>
    </div>
        </template>

        </div>
    @endif

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
    <script>
        function schedulingBoard(wire) {
            return {
                sortables: [],
                selectedLocation: null,
                selectedIds: [],
                selectedCount: 0,
                detailDivision: null,
                breakDetail: null,
                touchStartX: 0,
                touchStartY: 0,
                touchStartTime: 0,
                mergeModal: { open: false, divisions: [], sameEventType: true },

                openMergeModal() {
                    const divs = this.selectedIds
                        .map(id => {
                            const card = document.querySelector(`[data-id="${id}"]`);
                            return card?.dataset.division ? JSON.parse(card.dataset.division) : null;
                        })
                        .filter(Boolean)
                        .sort((a, b) => a.id - b.id);

                    const eventIds = [...new Set(divs.map(d => d.competition_event_id))];
                    this.mergeModal = {
                        open: true,
                        divisions: divs,
                        sameEventType: eventIds.length === 1,
                    };
                },

                closeMergeModal() {
                    this.mergeModal = { open: false, divisions: [], sameEventType: true };
                },

                confirmMerge() {
                    if (!this.mergeModal.sameEventType) return;
                    wire.performMerge(this.mergeModal.divisions.map(d => d.id));
                    this.closeMergeModal();
                    this.clearSelection();
                },

                selectLocation(location) {
                    const selecting = this.selectedLocation !== location;

                    if (selecting && window.innerWidth < 640 && this.selectedIds.length > 0) {
                        wire.moveDivisions([...this.selectedIds], location, 9999);
                        this.clearItems();
                        this.selectedLocation = null;
                        window.dispatchEvent(new CustomEvent('sched-board-location', { detail: { location: null } }));
                        return;
                    }

                    this.selectedLocation = selecting ? location : null;
                    window.dispatchEvent(new CustomEvent('sched-board-location', { detail: { location: this.selectedLocation } }));
                },

                selectOnly(id) {
                    document.querySelectorAll('.sched-selected, .sched-draggable-item')
                        .forEach(el => el.classList.remove('sched-selected', 'sched-draggable-item'));
                    this.selectedIds = [id];
                    this.selectedCount = 1;
                    const el = document.querySelector(`[data-id="${id}"]`);
                    if (el) {
                        el.classList.add('sched-selected');
                        // Mark the direct child of .sortable-col so the filter can find it
                        const col = el.closest('.sortable-col');
                        if (col) {
                            const item = Array.from(col.children).find(c => c === el || c.contains(el));
                            if (item) item.classList.add('sched-draggable-item');
                        }
                    }
                },

                toggleSelect(id) {
                    const idx = this.selectedIds.indexOf(id);
                    if (idx === -1) {
                        this.selectedIds.push(id);
                        this.selectedCount++;
                        document.querySelector(`[data-id="${id}"]`)?.classList.add('sched-selected');
                    } else {
                        this.selectedIds.splice(idx, 1);
                        this.selectedCount--;
                        document.querySelector(`[data-id="${id}"]`)?.classList.remove('sched-selected');
                    }
                },

                clearItems() {
                    this.selectedIds = [];
                    this.selectedCount = 0;
                    document.querySelectorAll('.sched-selected, .sched-draggable-item')
                        .forEach(el => el.classList.remove('sched-selected', 'sched-draggable-item'));
                },

                clearSelection() {
                    this.clearItems();
                    this.selectedLocation = null;
                    window.dispatchEvent(new CustomEvent('sched-board-location', { detail: { location: null } }));
                },

                showDetail(id) {
                    const card = document.querySelector(`[data-id="${id}"]`);
                    if (card && card.dataset.division) {
                        this.detailDivision = JSON.parse(card.dataset.division);
                        window.dispatchEvent(new CustomEvent('sched-board-open', { detail: { division: this.detailDivision } }));
                        this.$nextTick(() => {
                            const panelHeight = 170;
                            const rect = card.getBoundingClientRect();
                            const overflow = rect.bottom - (window.innerHeight - panelHeight - 16);
                            if (overflow > 0) {
                                window.scrollBy({ top: overflow, behavior: 'smooth' });
                            }
                        });
                    }
                },

                closeDetail() {
                    this.detailDivision = null;
                    this.breakDetail = null;
                    window.dispatchEvent(new CustomEvent('sched-board-close'));
                },

                assignDetailToLocation() {
                    if (!this.selectedLocation || !this.detailDivision) return;
                    wire.moveDivisions([this.detailDivision.id], this.selectedLocation, 9999);
                    this.closeDetail();
                    this.clearItems();
                },

                assignToSelected(divisionId) {
                    if (!this.selectedLocation) return;
                    const ids = this.selectedIds.length > 0 ? [...this.selectedIds] : [divisionId];
                    wire.moveDivisions(ids, this.selectedLocation, 9999);
                    this.clearItems();
                },

                onTouchStart(e) {
                    if (window.innerWidth >= 640) return;
                    this.touchStartX = e.touches[0].clientX;
                    this.touchStartY = e.touches[0].clientY;
                    this.touchStartTime = Date.now();
                },

                onTouchEnd(e) {
                    if (window.innerWidth >= 640) return;
                    if (e.target.closest('button')) return;
                    if (Date.now() - this.touchStartTime >= 300) return;
                    const touch = e.changedTouches[0];
                    const dx = touch.clientX - this.touchStartX;
                    const dy = touch.clientY - this.touchStartY;
                    if (dx * dx + dy * dy > 100) return;

                    const breakRow = e.target.closest('.sched-break-row');
                    if (breakRow) {
                        e.preventDefault();
                        this.detailDivision = null;
                        this.breakDetail = {
                            name: breakRow.dataset.breakName,
                            start_str: breakRow.dataset.breakStart,
                            end_str: breakRow.dataset.breakEnd,
                        };
                        window.dispatchEvent(new CustomEvent('sched-board-break', { detail: this.breakDetail }));
                        return;
                    }

                    const card = e.target.closest('[data-id]');
                    if (!card) return;
                    e.preventDefault();
                    this.breakDetail = null;
                    const id = parseInt(card.dataset.id);
                    this.selectOnly(id);
                    this.showDetail(id);
                },

                onCardClick(e) {
                    if (e.target.closest('button')) return;
                    if (window.innerWidth >= 640) return; // desktop: mousedown handles ctrl+click
                    // Mobile fallback — only reached if onTouchEnd didn't call preventDefault()
                    const card = e.target.closest('[data-id]');
                    if (!card) return;
                    const id = parseInt(card.dataset.id);
                    this.selectOnly(id);
                    this.showDetail(id);
                },

                onCardMousedown(e) {
                    if (window.innerWidth < 640) return;
                    if (e.target.closest('button')) return;
                    const card = e.target.closest('[data-id]');
                    if (!card) return;
                    const id = parseInt(card.dataset.id);
                    if (e.ctrlKey || e.metaKey) {
                        e.preventDefault();
                        e.stopPropagation();
                        this.toggleSelect(id);
                    }
                    // Plain click: no selectOnly — drag works without pre-selection
                },

                init() {
                    this.initSortables();
                    this._onLivewireUpdate = () => {
                        this.$nextTick(() => {
                            this.initSortables();
                            this.selectedIds = [];
                            this.selectedCount = 0;
                        });
                    };
                    this._onNavigating = () => {
                        this.detailDivision = null;
                        this.breakDetail = null;
                        this.clearItems();
                        window.dispatchEvent(new CustomEvent('sched-board-close'));
                    };
                    this._onAssign = () => {
                        if (!this.selectedLocation || !this.detailDivision) return;
                        wire.moveDivisions([this.detailDivision.id], this.selectedLocation, 9999);
                        this.closeDetail();
                        this.clearItems();
                    };
                    this._onClose = () => {
                        this.detailDivision = null;
                        this.breakDetail = null;
                    };
                    document.addEventListener('livewire:update', this._onLivewireUpdate);
                    document.addEventListener('livewire:navigating', this._onNavigating);
                    window.addEventListener('sched-board-assign', this._onAssign);
                    window.addEventListener('sched-board-close', this._onClose);
                },

                destroy() {
                    document.removeEventListener('livewire:update', this._onLivewireUpdate);
                    document.removeEventListener('livewire:navigating', this._onNavigating);
                    window.removeEventListener('sched-board-assign', this._onAssign);
                    window.removeEventListener('sched-board-close', this._onClose);
                },

                initSortables() {
                    this.sortables.forEach(s => s.destroy());
                    this.sortables = [];

                    document.querySelectorAll('.sortable-col').forEach(col => {
                        const isMobile = window.innerWidth < 640;
                        const sortable = Sortable.create(col, {
                            group: 'divisions',
                            animation: 150,
                            delay: 200,
                            delayOnTouchOnly: true,
                            filter: function(e, el) {
                                if (el.classList.contains('sched-break-row')) return true;
                                if (window.innerWidth >= 640) return false;
                                return !el.classList.contains('sched-draggable-item');
                            },
                            preventOnFilter: false,
                            ghostClass: 'sortable-ghost',
                            dragClass: 'sortable-drag',
                            emptyInsertThreshold: 100,
                            onEnd: (evt) => {
                                const ids = this.selectedIds.length > 0
                                    ? [...this.selectedIds]
                                    : (() => {
                                        const id = parseInt(
                                            evt.item.dataset.id ?? evt.item.querySelector('[data-id]')?.dataset.id
                                        );
                                        return id ? [id] : [];
                                    })();

                                if (!ids.length) return;
                                // Exclude break separators from index count
                                const divCards = Array.from(evt.to.children)
                                    .filter(el => !el.classList.contains('sched-break-row'));
                                const cardIndex = divCards.indexOf(evt.item);
                                wire.moveDivisions(ids, evt.to.dataset.location, cardIndex >= 0 ? cardIndex : 9999);
                                this.clearItems();
                            },
                        });
                        this.sortables.push(sortable);
                    });
                },
            };
        }
    </script>

    <style>
        /* Prevent text selection and double-tap zoom across the board */
        .sched-board           { user-select: none; -webkit-user-select: none; }
        [data-id]              { touch-action: manipulation; }

        /* Column container backgrounds */
        .sched-col-bg          { background-color: #f9fafb; }
        .dark .sched-col-bg    { background-color: rgba(31,41,55,0.5); }

        /* Card status colours — light mode */
        .sched-complete { background-color: #bbf7d0; border-color: #9ca3af; }
        .sched-full     { background-color: #c7d2fe; border-color: #9ca3af; }
        .sched-assigned { background-color: #fde68a; border-color: #9ca3af; }
        .sched-pending  { background-color: #ffffff; border-color: #9ca3af; }

        /* Card status colours — dark mode */
        .dark .sched-complete { background-color: rgba(20,83,45,0.45);  border-color: #166534; }
        .dark .sched-full     { background-color: rgba(30,27,81,0.55);  border-color: #3730a3; }
        .dark .sched-assigned { background-color: rgba(120,53,15,0.45); border-color: #b45309; }
        .dark .sched-pending  { background-color: #1f2937;              border-color: #374151; }

        /* Secondary text */
        .sched-text-meta                 { color: #6b7280; }
        .sched-complete .sched-text-meta,
        .sched-full     .sched-text-meta,
        .sched-assigned .sched-text-meta { color: #374151; }
        .dark .sched-text-meta           { color: #9ca3af; }

        .sortable-ghost    { opacity: 0.4; }
        .sortable-drag     { opacity: 0.9; transform: rotate(2deg); }
        .sched-selected    { box-shadow: 0 0 0 3px #6366f1 !important; border-color: #6366f1 !important; }
        [x-cloak]          { display: none !important; }
    </style>
</x-filament-panels::page>
