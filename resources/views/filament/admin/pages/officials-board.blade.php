<x-filament-panels::page>
    <div
        x-data="officialsBoard(@this)"
        x-init="init()"
        @keydown.escape.window="closeDetail()"
        class="min-w-0 officials-board"
    >
        @if(empty($columns))
            <div class="mb-4 rounded-md bg-warning-50 dark:bg-warning-900/20 border border-warning-200 dark:border-warning-700 px-4 py-3 text-sm text-warning-700 dark:text-warning-300">
                No locations have been defined for this competition.
                Officials can still be added but cannot be assigned to a location until
                <a href="{{ \App\Filament\OrgAdmin\Resources\CompetitionResource::getUrl('edit', ['record' => $this->getRecord()]) }}"
                   class="underline font-medium">locations are configured</a>.
            </div>
        @endif

        {{-- Mobile hint --}}
        <div class="sm:hidden mb-2 rounded-md bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-700 px-2 py-1.5 text-xs text-primary-700 dark:text-primary-300">
            Tap a card to view details. Long-press and drag to move to a location.
        </div>

        <div
            class="flex gap-3 pb-6 py-1 overflow-x-auto"
            @touchstart.passive.capture="onTouchStart($event)"
            @touchend.capture="onTouchEnd($event)"
        >

            {{-- Unassigned column --}}
            <div class="flex-1 min-w-[5rem]">
                <div class="mb-2 px-2 min-w-0 min-h-[2.5rem]">
                    <span class="block text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 truncate">Unassigned</span>
                    <span class="inline-block mt-0.5 rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs text-gray-500 dark:text-gray-400">
                        {{ count($officialsByColumn['__unassigned__'] ?? []) }}
                    </span>
                </div>
                <div
                    class="sortable-col officials-col-bg rounded-lg p-2"
                    style="min-height: 200px; padding-bottom: 60px;"
                    data-location="__unassigned__"
                >
                    @foreach($officialsByColumn['__unassigned__'] ?? [] as $official)
                        @include('filament.admin.partials.official-card', ['official' => $official])
                    @endforeach
                </div>
            </div>

            {{-- Location columns --}}
            @foreach($columns as $col)
                <div class="flex-1 min-w-[5rem]">
                    <div class="mb-2 px-2 min-w-0 min-h-[2.5rem]">
                        <span class="block truncate text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300"
                              title="{{ $col }}">{{ $col }}</span>
                        <span class="inline-block mt-0.5 rounded-full bg-primary-100 dark:bg-primary-900/40 px-2 py-0.5 text-xs text-primary-700 dark:text-primary-300">
                            {{ count($officialsByColumn[$col] ?? []) }}
                        </span>
                    </div>
                    <div
                        class="sortable-col officials-col-bg rounded-lg p-2"
                        style="min-height: 200px; padding-bottom: 60px;"
                        data-location="{{ $col }}"
                    >
                        @foreach($officialsByColumn[$col] ?? [] as $official)
                            @include('filament.admin.partials.official-card', ['official' => $official])
                        @endforeach
                    </div>
                </div>
            @endforeach

        </div>

        {{-- Mobile detail panel --}}
        <div x-show="detailOfficial !== null" x-cloak class="fixed inset-x-0 bottom-0 z-50 sm:hidden">
            <div class="relative bg-white dark:bg-gray-800 rounded-t-2xl shadow-xl px-3" style="padding-bottom: max(1rem, env(safe-area-inset-bottom, 0px));">
                <div @click="closeDetail()" class="flex justify-center pt-2 pb-1 cursor-pointer" aria-label="Close">
                    <div class="w-10 h-1 rounded-full bg-gray-300 dark:bg-gray-600"></div>
                </div>
                <p class="text-sm font-semibold text-gray-900 dark:text-white leading-tight" x-text="detailOfficial?.firstName"></p>
                <p class="text-sm font-medium text-gray-700 dark:text-gray-200 leading-tight mb-0.5" x-show="detailOfficial?.surname" x-text="detailOfficial?.surname"></p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3" x-text="detailOfficial?.role"></p>
                <button
                    @click="removeOfficial()"
                    class="w-full rounded-lg border border-danger-300 dark:border-danger-700 text-danger-600 dark:text-danger-400 text-sm font-medium py-2 text-center transition-colors hover:bg-danger-50 dark:hover:bg-danger-950"
                >
                    Remove official
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
    <script>
        function officialsBoard(wire) {
            return {
                sortables: [],
                detailOfficial: null,
                touchStartX: 0,
                touchStartY: 0,
                touchStartTime: 0,

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
                    if (card.dataset.official) {
                        this.detailOfficial = JSON.parse(card.dataset.official);
                        this.$nextTick(() => {
                            const panelHeight = 140;
                            const rect = card.getBoundingClientRect();
                            const overflow = rect.bottom - (window.innerHeight - panelHeight - 16);
                            if (overflow > 0) {
                                window.scrollBy({ top: overflow, behavior: 'smooth' });
                            }
                        });
                    }
                },

                closeDetail() {
                    this.detailOfficial = null;
                },

                removeOfficial() {
                    if (!this.detailOfficial) return;
                    const id   = this.detailOfficial.id;
                    const name = (this.detailOfficial.firstName + ' ' + (this.detailOfficial.surname ?? '')).trim();
                    this.closeDetail();
                    wire.mountAction('removeOfficial', { officialId: id, name });
                },

                init() {
                    this.initSortables();
                    this._onLivewireUpdate = () => {
                        this.$nextTick(() => this.initSortables());
                    };
                    this._onNavigating = () => {
                        this.detailOfficial = null;
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
                        const sortable = Sortable.create(col, {
                            group: 'officials',
                            animation: 150,
                            delay: 200,
                            delayOnTouchOnly: true,
                            ghostClass: 'sortable-ghost',
                            dragClass: 'sortable-drag',
                            emptyInsertThreshold: 100,
                            onEnd: (evt) => {
                                const id = parseInt(evt.item.dataset.id);
                                const location = evt.to.dataset.location;
                                if (!isNaN(id)) {
                                    wire.moveOfficial(id, location);
                                }
                            },
                        });
                        this.sortables.push(sortable);
                    });
                },
            };
        }
    </script>

    <style>
        .officials-board           { user-select: none; -webkit-user-select: none; }
        [data-id]                  { touch-action: manipulation; }

        .officials-col-bg          { background-color: #f9fafb; }
        .dark .officials-col-bg    { background-color: rgba(31,41,55,0.5); }

        .sortable-ghost { opacity: 0.4; }
        .sortable-drag  { opacity: 0.9; transform: rotate(2deg); }
        [x-cloak]       { display: none !important; }
    </style>
</x-filament-panels::page>
