<x-filament-panels::page>
    @php
        $dojos        = $this->getDojos();
        $competitions = $this->getCompetitions();
    @endphp

    @forelse ($competitions as $competition)
        @php
            $dojoNames = $dojos->pluck('name');

            $enrolmentsByDojo = $competition->enrolments
                ->whereIn('dojo_name', $dojoNames)
                ->groupBy('dojo_name')
                ->map(fn ($group) => $group->sortBy(
                    fn ($e) => ($e->competitor?->first_name ?? '') . ' ' . ($e->competitor?->surname ?? '')
                ));

            $totalCompetitors = $enrolmentsByDojo->flatten()->count();

            $statusClass = match($competition->status) {
                'open'     => 'bg-success-100 dark:bg-success-900/30 text-success-700 dark:text-success-400',
                'closed'   => 'bg-warning-100 dark:bg-warning-900/30 text-warning-700 dark:text-warning-400',
                'check_in' => 'bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-400',
                'running'  => 'bg-danger-100 dark:bg-danger-900/30 text-danger-700 dark:text-danger-400',
                default    => 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300',
            };
        @endphp

        {{-- Competition --}}
        <div class="mb-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
            <div class="flex w-full items-center justify-between gap-3 px-4 py-3">
                <div class="min-w-0">
                    <span class="font-semibold text-sm text-gray-900 dark:text-white">{{ $competition->name }}</span>
                    <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">{{ $competition->competition_date->format('d M Y') }}</span>
                    <span class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $statusClass }}">
                        {{ ucfirst(str_replace('_', ' ', $competition->status)) }}
                    </span>
                </div>
                <span class="text-xs text-gray-400 dark:text-gray-500 shrink-0">{{ $totalCompetitors }} {{ Str::plural('competitor', $totalCompetitors) }}</span>
            </div>

            <div class="border-t border-gray-100 dark:border-gray-800">

                @foreach ($enrolmentsByDojo as $dojoName => $enrolments)
                    {{-- Dojo --}}
                    <div x-data="{ open: false }" class="border-b border-gray-100 dark:border-gray-800 last:border-b-0">
                        <button
                            type="button"
                            x-on:click="open = !open"
                            class="flex w-full items-center justify-between gap-2 px-4 py-2.5 text-left bg-gray-50 dark:bg-gray-800/60 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $dojoName }}</span>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $enrolments->count() }}</span>
                                <x-heroicon-m-chevron-down x-bind:class="open ? 'rotate-180' : ''" class="h-3.5 w-3.5 text-gray-400 transition-transform" />
                            </div>
                        </button>

                        <div x-show="open" x-collapse>
                        <div class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($enrolments as $enrolment)
                                @php
                                    $name       = $enrolment->competitor?->full_name ?? '—';
                                    $eventCount = $enrolment->activeEvents->count();
                                @endphp

                                {{-- Competitor --}}
                                <div x-data="{ open: false }" class="px-4 py-2.5">
                                    <button
                                        type="button"
                                        x-on:click="open = !open"
                                        class="flex w-full items-center justify-between gap-2 text-left">
                                        <span class="text-sm text-gray-800 dark:text-gray-200">
                                            {{ $name }}
                                            <span class="ml-1 text-xs text-gray-400 dark:text-gray-500">({{ $eventCount }} {{ Str::plural('event', $eventCount) }})</span>
                                        </span>
                                        <x-heroicon-m-chevron-down
                                            x-bind:class="open ? 'rotate-180' : ''"
                                            class="h-4 w-4 text-gray-400 shrink-0 transition-transform" />
                                    </button>

                                    {{-- Events --}}
                                    <div x-show="open" x-collapse class="mt-2 pl-2 flex flex-col gap-1.5">
                                        @foreach ($enrolment->activeEvents as $ee)
                                            <div class="text-xs text-gray-600 dark:text-gray-400">
                                                <span class="font-medium text-gray-700 dark:text-gray-300">{{ $ee->competitionEvent->event_code }}</span>
                                                — {{ $ee->competitionEvent->name }}
                                                @if ($ee->division)
                                                    <span class="text-gray-400 dark:text-gray-500"> · {{ $ee->division->full_label }}</span>
                                                @endif
                                                @if ($ee->result?->disqualified)
                                                    <span class="ml-1 font-medium text-danger-600 dark:text-danger-400">DQ</span>
                                                @elseif ($ee->result?->placement)
                                                    <span class="ml-1 font-medium text-primary-600 dark:text-primary-400">
                                                        @switch($ee->result->placement)
                                                            @case(1) 1st @break
                                                            @case(2) 2nd @break
                                                            @case(3) 3rd @break
                                                            @default {{ $ee->result->placement }}th
                                                        @endswitch
                                                    </span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        </div>
                    </div>
                @endforeach

            </div>
        </div>
    @empty
        <x-filament::section>
            <p class="text-sm text-center text-gray-500 py-4">No active competitions with enrolments from your dojo{{ $dojos->count() > 1 ? 's' : '' }}.</p>
        </x-filament::section>
    @endforelse
</x-filament-panels::page>
