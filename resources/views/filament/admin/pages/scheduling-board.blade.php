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
            x-data="schedulingBoard(@this)"
            x-init="init()"
            @keydown.escape.window="clearSelection()"
            class="min-w-0"
        >
            {{-- Context hint: desktop only, shown when a location is armed --}}
            <div x-show="selectedLocation !== null" x-cloak
                 class="hidden sm:block mb-2 rounded-md bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-700 px-2 py-1.5 text-xs text-primary-700 dark:text-primary-300">
                <template x-if="selectedCount > 0">
                    <span><strong x-text="selectedCount"></strong> selected — drag any to move all, or double-click to assign to <strong x-text="selectedLocation"></strong>. Ctrl+click to add more. Esc to cancel.</span>
                </template>
                <template x-if="selectedCount === 0">
                    <span>Click a card to select it. Drag or double-click to assign to <strong x-text="selectedLocation"></strong>. Esc to cancel.</span>
                </template>
            </div>

            <div
                class="flex gap-4 overflow-x-auto pb-6 px-1 py-1"
                @mousedown.capture="onCardMousedown($event)"
                @click.capture="onCardClick($event)"
            >
                {{-- Unassigned column --}}
                <div class="flex-shrink-0 w-24 sm:w-72 min-w-0">
                    <div class="mb-2 flex items-center gap-2 min-w-0">
                        <span class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 truncate">Unassigned</span>
                        <span class="shrink-0 rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs text-gray-500 dark:text-gray-400">
                            ({{ count($divisionsByColumn['__unassigned__'] ?? []) }})
                        </span>
                    </div>

                    {{-- Event type filter: desktop only --}}
                    @if(count($eventTypes) > 1)
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
                                @include('filament.admin.partials.scheduling-card', ['div' => $div])
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Location columns --}}
                @foreach($columns as $col)
                    <div class="flex-shrink-0 w-24 sm:w-72 min-w-0">
                        <div class="mb-2 flex items-center gap-2 min-w-0">
                            <button
                                type="button"
                                @click="selectLocation('{{ addslashes($col) }}')"
                                :class="selectedLocation === '{{ addslashes($col) }}'
                                    ? 'text-primary-600 dark:text-primary-400 ring-2 ring-primary-500 rounded px-1 -mx-1'
                                    : 'text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400'"
                                class="min-w-0 truncate text-xs font-semibold uppercase tracking-wider transition-colors"
                                title="{{ $col }}"
                            >{{ $col }}</button>
                            <span class="shrink-0 rounded-full bg-primary-100 dark:bg-primary-900/40 px-2 py-0.5 text-xs text-primary-700 dark:text-primary-300">
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

            {{-- Mobile detail panel --}}
            <div x-show="detailDivision !== null" x-cloak class="fixed inset-x-0 bottom-0 z-50 sm:hidden">
                <div class="relative bg-white dark:bg-gray-800 rounded-t-2xl shadow-xl px-4 pb-8">
                    {{-- Drag handle / tap to close --}}
                    <div @click="closeDetail()" class="flex justify-center pt-3 pb-2 cursor-pointer">
                        <div class="w-10 h-1 rounded-full bg-gray-300 dark:bg-gray-600"></div>
                    </div>
                    <div class="flex items-center gap-2 mb-0.5">
                        <span class="font-mono text-sm font-bold text-gray-900 dark:text-white" x-text="detailDivision?.code"></span>
                        <span class="text-xs text-gray-500 dark:text-gray-400 truncate" x-text="detailDivision?.event"></span>
                    </div>
                    <p class="text-sm text-gray-700 dark:text-gray-200 mb-2" x-text="detailDivision?.label"></p>

                    <div class="flex items-center gap-3 mb-4 text-xs text-gray-500 dark:text-gray-400">
                        <span class="capitalize" x-text="detailDivision?.status"></span>
                        <span x-show="(detailDivision?.enrolled ?? 0) > 0">
                            <span x-text="detailDivision?.enrolled"></span> enrolled
                        </span>
                        <span x-show="(detailDivision?.checkedIn ?? 0) > 0" class="text-success-600 dark:text-success-400">
                            <span x-text="detailDivision?.checkedIn"></span> checked in
                        </span>
                        <span x-show="detailDivision?.noneShowed" class="text-warning-600 dark:text-warning-400">
                            0 checked in
                        </span>
                    </div>

                    <template x-if="selectedLocation && detailDivision?.status !== 'complete'">
                        <button
                            @click="assignDetailToLocation()"
                            class="w-full rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium py-2.5 text-center transition-colors"
                        >
                            Assign to <span x-text="selectedLocation"></span>
                        </button>
                    </template>
                    <template x-if="!selectedLocation && detailDivision?.status !== 'complete'">
                        <p class="text-xs text-center text-gray-400 dark:text-gray-500 mt-1">Tap a location header to select it, then tap a card to assign.</p>
                    </template>
                </div>
            </div>
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

                selectLocation(location) {
                    const selecting = this.selectedLocation !== location;
                    this.selectedLocation = selecting ? location : null;

                    if (selecting && window.innerWidth < 640) {
                        const unassignedCol = document.querySelector('.sortable-col[data-location="__unassigned__"]');
                        if (unassignedCol) {
                            const firstCard = [...unassignedCol.querySelectorAll('[data-id]')]
                                .find(card => card.parentElement?.style.display !== 'none');
                            if (firstCard) {
                                this.showDetail(parseInt(firstCard.dataset.id));
                            }
                        }
                    }
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

                showDetail(id) {
                    const card = document.querySelector(`[data-id="${id}"]`);
                    if (card && card.dataset.division) {
                        this.detailDivision = JSON.parse(card.dataset.division);
                    }
                },

                closeDetail() {
                    this.detailDivision = null;
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

                onCardClick(e) {
                    if (window.innerWidth >= 640) return;
                    if (e.target.closest('button')) return;
                    const card = e.target.closest('[data-id]');
                    if (!card) return;
                    if (this.selectedLocation !== null) {
                        const col = card.closest('.sortable-col');
                        if (!col || col.dataset.location !== '__unassigned__') return;
                    }
                    e.preventDefault();
                    e.stopPropagation();
                    this.showDetail(parseInt(card.dataset.id));
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
