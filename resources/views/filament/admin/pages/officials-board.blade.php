<x-filament-panels::page>
    <div
        x-data="officialsBoard(@this)"
        x-init="init()"
        class="min-w-0"
    >
        @if(empty($columns))
            <div class="mb-4 rounded-md bg-warning-50 dark:bg-warning-900/20 border border-warning-200 dark:border-warning-700 px-4 py-3 text-sm text-warning-700 dark:text-warning-300">
                No locations have been defined for this competition.
                Officials can still be added but cannot be assigned to a location until
                <a href="{{ \App\Filament\Admin\Resources\CompetitionResource::getUrl('edit', ['record' => $this->getRecord()]) }}"
                   class="underline font-medium">locations are configured</a>.
            </div>
        @endif

        <div class="flex gap-3 pb-6 py-1">

            {{-- Unassigned column --}}
            <div class="flex-1 min-w-0">
                <div class="mb-2 px-2 flex items-center gap-2 min-w-0">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 truncate">Unassigned</span>
                    <span class="shrink-0 rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs text-gray-500 dark:text-gray-400">
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
                <div class="flex-1 min-w-0">
                    <div class="mb-2 px-2 flex items-center gap-2 min-w-0">
                        <span class="truncate text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300"
                              title="{{ $col }}">{{ $col }}</span>
                        <span class="shrink-0 rounded-full bg-primary-100 dark:bg-primary-900/40 px-2 py-0.5 text-xs text-primary-700 dark:text-primary-300">
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
    <script>
        function officialsBoard(wire) {
            return {
                sortables: [],

                init() {
                    this.initSortables();
                    document.addEventListener('livewire:update', () => {
                        this.$nextTick(() => this.initSortables());
                    });
                },

                initSortables() {
                    this.sortables.forEach(s => s.destroy());
                    this.sortables = [];

                    document.querySelectorAll('.sortable-col').forEach(col => {
                        const sortable = Sortable.create(col, {
                            group: 'officials',
                            animation: 150,
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
        .officials-col-bg       { background-color: #f9fafb; }
        .dark .officials-col-bg { background-color: rgba(31,41,55,0.5); }

        .sortable-ghost { opacity: 0.4; }
        .sortable-drag  { opacity: 0.9; transform: rotate(2deg); }
    </style>
</x-filament-panels::page>
