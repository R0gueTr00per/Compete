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
            class="flex gap-4 overflow-x-auto pb-6"
            x-data="schedulingBoard(@this)"
            x-init="init()"
        >
            {{-- Unassigned column --}}
            <div class="flex-shrink-0 w-72">
                <div class="mb-2 flex items-center gap-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Unassigned</span>
                    <span class="rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs text-gray-500 dark:text-gray-400">
                        ({{ count($divisionsByColumn['__unassigned__'] ?? []) }})
                    </span>
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
                    class="sortable-col rounded-lg bg-gray-50 dark:bg-gray-800/50 p-2"
                    style="min-height: 300px; padding-bottom: 80px;"
                    data-location="__unassigned__"
                >
                    @foreach($divisionsByColumn['__unassigned__'] ?? [] as $div)
                        @php
                            $eventTypeId = $div->competitionEvent->eventType->id ?? null;
                            $hidden = $filterEventType && $eventTypeId != $filterEventType;
                        @endphp
                        <div @if($hidden) style="display:none" @endif>
                            @include('filament.admin.partials.scheduling-card', ['div' => $div])
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Location columns --}}
            @foreach($columns as $col)
                <div class="flex-shrink-0 w-72">
                    <div class="mb-2 flex items-center gap-2">
                        <span class="text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">{{ $col }}</span>
                        <span class="rounded-full bg-primary-100 dark:bg-primary-900/40 px-2 py-0.5 text-xs text-primary-700 dark:text-primary-300">
                            ({{ count($divisionsByColumn[$col] ?? []) }})
                        </span>
                    </div>
                    <div
                        class="sortable-col rounded-lg bg-gray-50 dark:bg-gray-800/50 p-2"
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

                init() {
                    this.initSortables();

                    document.addEventListener('livewire:update', () => {
                        this.$nextTick(() => this.initSortables());
                    });
                },

                initSortables() {
                    this.sortables.forEach(s => s.destroy());
                    this.sortables = [];

                    document.querySelectorAll('.sortable-col').forEach(el => {
                        const sortable = Sortable.create(el, {
                            group: 'divisions',
                            animation: 150,
                            ghostClass: 'sortable-ghost',
                            dragClass: 'sortable-drag',
                            emptyInsertThreshold: 100,
                            filter: '[data-scored]',
                            preventOnFilter: true,
                            onEnd: (evt) => {
                                const divisionId = parseInt(evt.item.dataset.id);
                                const location   = evt.to.dataset.location;
                                const newIndex   = evt.newIndex;
                                wire.moveDivision(divisionId, location, newIndex);
                            },
                        });
                        this.sortables.push(sortable);
                    });
                },
            };
        }
    </script>

    <style>
        .sortable-ghost {
            opacity: 0.4;
        }
        .sortable-drag {
            opacity: 0.9;
            transform: rotate(2deg);
        }
    </style>
</x-filament-panels::page>
