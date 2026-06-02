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

            <div
                class="flex gap-3 pb-6 px-1 py-1 overflow-x-auto"
                @mousedown.capture="onCardMousedown($event)"
                @touchstart.passive.capture="onTouchStart($event)"
                @touchend.capture="onTouchEnd($event)"
                @click.capture="onCardClick($event)"
            >
                {{-- Unassigned column --}}
                <div class="flex-1 min-w-[5rem]">
                    <div class="mb-2 min-h-[2.5rem]">
                        <span class="block text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Unassigned</span>
                        <span class="inline-block mt-0.5 rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs text-gray-500 dark:text-gray-400">
                            {{ count($divisionsByColumn['__unassigned__'] ?? []) }}
                        </span>
                    </div>

                    {{-- Desktop filter --}}
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
                            @foreach($divisionsByColumn[$col] ?? [] as $div)
                                @include('filament.admin.partials.scheduling-card', ['div' => $div])
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

            {{-- Mobile detail panel --}}
            <div x-show="detailDivision !== null" x-cloak class="fixed inset-x-0 bottom-0 z-50 sm:hidden">
                <div class="relative bg-white dark:bg-gray-800 rounded-t-2xl shadow-xl px-3" style="padding-bottom: max(1rem, env(safe-area-inset-bottom, 0px));">
                    {{-- Drag handle / tap to close --}}
                    <div @click="closeDetail()" class="flex justify-center pt-2 pb-1 cursor-pointer" aria-label="Close">
                        <div class="w-10 h-1 rounded-full bg-gray-300 dark:bg-gray-600"></div>
                    </div>
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
                        <span x-show="(detailDivision?.checkedIn ?? 0) > 0" class="text-success-600 dark:text-success-400">
                            <span x-text="detailDivision?.checkedIn"></span> checked in
                        </span>
                        <span x-show="detailDivision?.noneShowed" class="text-warning-600 dark:text-warning-400">0 checked in</span>
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
                            @click="assignDetailToLocation()"
                            class="w-full rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium py-2 text-center transition-colors"
                        >
                            Assign to <span x-text="selectedLocation"></span>
                        </button>
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
                        return;
                    }

                    this.selectedLocation = selecting ? location : null;
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
                },

                showDetail(id) {
                    const card = document.querySelector(`[data-id="${id}"]`);
                    if (card && card.dataset.division) {
                        this.detailDivision = JSON.parse(card.dataset.division);
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
                    const card = e.target.closest('[data-id]');
                    if (!card) return;
                    if (Date.now() - this.touchStartTime >= 200) return;
                    const touch = e.changedTouches[0];
                    const dx = touch.clientX - this.touchStartX;
                    const dy = touch.clientY - this.touchStartY;
                    if (dx * dx + dy * dy > 64) return;
                    e.preventDefault();
                    const id = parseInt(card.dataset.id);
                    this.selectOnly(id);
                    this.showDetail(id);
                },

                onCardClick(e) {
                    if (window.innerWidth < 640) return;
                    if (e.target.closest('button')) return;
                    // desktop: no extra behaviour; mousedown handles ctrl+click
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
                        this.clearItems();
                    };
                    document.addEventListener('livewire:update', this._onLivewireUpdate);
                    document.addEventListener('livewire:navigating', this._onNavigating);
                },

                destroy() {
                    document.removeEventListener('livewire:update', this._onLivewireUpdate);
                    document.removeEventListener('livewire:navigating', this._onNavigating);
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
