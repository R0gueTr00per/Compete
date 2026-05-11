<x-filament-panels::page>
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
            class="flex gap-4 overflow-x-auto pb-6 px-1 py-1"
            x-data="schedulingBoard(@this)"
            x-init="init()"
            @keydown.escape.window="clearSelection()"
            @mousedown.capture="onCardMousedown($event)"
        >
            {{-- Unassigned column --}}
            <div class="flex-shrink-0 w-72">
                <div class="mb-2 flex items-center gap-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Unassigned</span>
                    <span class="rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs text-gray-500 dark:text-gray-400">
                        ({{ count($divisionsByColumn['__unassigned__'] ?? []) }})
                    </span>
                </div>

                {{-- Context hint: changes based on selection / location state --}}
                <div x-show="selectedCount > 0 || selectedLocation" x-cloak
                     class="mb-2 rounded-md bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-700 px-2 py-1.5 text-xs text-primary-700 dark:text-primary-300">
                    <template x-if="selectedCount > 0 && selectedLocation">
                        <span><strong x-text="selectedCount"></strong> selected — drag any to move all, or double-click to assign to <strong x-text="selectedLocation"></strong>. Ctrl+click to add more. Esc to cancel.</span>
                    </template>
                    <template x-if="selectedCount > 0 && !selectedLocation">
                        <span><strong x-text="selectedCount"></strong> selected — drag any to move all. Ctrl+click to add more. Esc to cancel.</span>
                    </template>
                    <template x-if="selectedCount === 0 && selectedLocation">
                        <span>Click a card to select it. Drag or double-click to assign to <strong x-text="selectedLocation"></strong>. Esc to cancel.</span>
                    </template>
                </div>

                {{-- Event type filter --}}
                @if(count($eventTypes) > 1)
                    <div class="mb-2">
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
                            @include('filament.admin.partials.scheduling-card', ['div' => $div])
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Location columns --}}
            @foreach($columns as $col)
                <div class="flex-shrink-0 w-72">
                    <div class="mb-2 flex items-center gap-2">
                        <button
                            type="button"
                            @click="selectLocation('{{ addslashes($col) }}')"
                            :class="selectedLocation === '{{ addslashes($col) }}'
                                ? 'text-primary-600 dark:text-primary-400 ring-2 ring-primary-500 rounded px-1 -mx-1'
                                : 'text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400'"
                            class="text-xs font-semibold uppercase tracking-wider transition-colors"
                        >{{ $col }}</button>
                        <span class="rounded-full bg-primary-100 dark:bg-primary-900/40 px-2 py-0.5 text-xs text-primary-700 dark:text-primary-300">
                            ({{ count($divisionsByColumn[$col] ?? []) }})
                        </span>
                    </div>
                    <div
                        class="sortable-col sched-col-bg rounded-lg p-2 transition-shadow"
                        :class="selectedLocation === '{{ addslashes($col) }}' ? 'ring-2 ring-primary-400 dark:ring-primary-600' : ''"
                        style="min-height: 300px; padding-bottom: 80px;"
                        data-location="{{ $col }}"
                    >
                        @foreach($divisionsByColumn[$col] ?? [] as $div)
                            @include('filament.admin.partials.scheduling-card', ['div' => $div])
                        @endforeach
                    </div>
                </div>
            @endforeach
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

                selectLocation(location) {
                    this.selectedLocation = this.selectedLocation === location ? null : location;
                },

                selectOnly(id) {
                    document.querySelectorAll('.sched-selected')
                        .forEach(el => el.classList.remove('sched-selected'));
                    this.selectedIds = [id];
                    this.selectedCount = 1;
                    document.querySelector(`[data-id="${id}"]`)
                        ?.classList.add('sched-selected');
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
                    document.querySelectorAll('.sched-selected')
                        .forEach(el => el.classList.remove('sched-selected'));
                },

                clearSelection() {
                    this.clearItems();
                    this.selectedLocation = null;
                },

                assignToSelected(divisionId) {
                    if (!this.selectedLocation) return;
                    const ids = this.selectedIds.length > 0 ? [...this.selectedIds] : [divisionId];
                    wire.moveDivisions(ids, this.selectedLocation, 9999);
                    this.clearItems();
                },

                onCardMousedown(e) {
                    if (e.target.closest('button')) return;
                    const card = e.target.closest('[data-id]');
                    if (!card) return;
                    const id = parseInt(card.dataset.id);
                    if (e.ctrlKey || e.metaKey) {
                        e.preventDefault();
                        e.stopPropagation();
                        this.toggleSelect(id);
                    } else {
                        // Only replace selection if this card isn't already selected;
                        // if it is, keep the full selection so dragging moves all of them.
                        if (!this.selectedIds.includes(id)) {
                            this.selectOnly(id);
                        }
                        // don't stopPropagation — let SortableJS handle the drag
                    }
                },

                init() {
                    this.initSortables();
                    document.addEventListener('livewire:update', () => {
                        this.$nextTick(() => {
                            this.initSortables();
                            this.selectedIds = [];
                            this.selectedCount = 0;
                        });
                    });
                },

                initSortables() {
                    this.sortables.forEach(s => s.destroy());
                    this.sortables = [];

                    document.querySelectorAll('.sortable-col').forEach(col => {
                        const sortable = Sortable.create(col, {
                            group: 'divisions',
                            animation: 150,
                            ghostClass: 'sortable-ghost',
                            dragClass: 'sortable-drag',
                            emptyInsertThreshold: 100,
                            filter: '[data-scored]',
                            preventOnFilter: true,
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
                                wire.moveDivisions(ids, evt.to.dataset.location, evt.newIndex);
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
        /* Column container backgrounds */
        .sched-col-bg          { background-color: #f9fafb; }   /* gray-50  */
        .dark .sched-col-bg    { background-color: rgba(31,41,55,0.5); } /* gray-800/50 */

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

        /* Secondary text — darker on coloured cards in light mode, muted in dark */
        .sched-text-meta                 { color: #6b7280; } /* gray-500 — pending/white */
        .sched-complete .sched-text-meta,
        .sched-full     .sched-text-meta,
        .sched-assigned .sched-text-meta { color: #374151; } /* gray-700 — coloured cards */
        .dark .sched-text-meta           { color: #9ca3af; } /* gray-400 — all dark mode */

        .sortable-ghost    { opacity: 0.4; }
        .sortable-drag     { opacity: 0.9; transform: rotate(2deg); }
        .sched-selected    { box-shadow: 0 0 0 3px #6366f1 !important; border-color: #6366f1 !important; }
        [x-cloak]          { display: none !important; }
    </style>
</x-filament-panels::page>
