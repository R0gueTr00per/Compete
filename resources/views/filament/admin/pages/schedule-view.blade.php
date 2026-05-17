<x-filament-panels::page>
    @php
        $competition = $this->getCompetition();
        $locations   = $this->getLocations();
        $divisions   = $this->getDivisions();
    @endphp

    @if (! $competition)
        <x-filament::section>
            <p class="text-center text-gray-500 py-8">No active competition found.</p>
        </x-filament::section>
    @else
        {{-- Competition header --}}
        <x-filament::section>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-500">
                <span>{{ $competition->competition_date->format('l j F Y') }}</span>
                @if ($competition->location_name)
                    <span>&middot; {{ $competition->location_name }}</span>
                @endif
                @if ($competition->start_time)
                    <span>&middot; Starts {{ \Carbon\Carbon::parse($competition->start_time)->format('g:i a') }}</span>
                @endif
                <span class="sm:ml-auto flex items-center gap-2 text-xs text-gray-400">
                    Updated {{ now()->format('g:i a') }}
                    <x-filament::button size="xs" color="gray" wire:click="$refresh">
                        Refresh
                    </x-filament::button>
                </span>
            </div>

            {{-- Legend --}}
            <div class="mt-3 flex flex-wrap gap-4 text-xs text-gray-600 dark:text-gray-400">
                <span class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-sm inline-block bg-green-100 dark:bg-green-900/40 border border-green-300 dark:border-green-700"></span> Complete
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-sm inline-block bg-indigo-100 dark:bg-indigo-900/40 border border-indigo-300 dark:border-indigo-700"></span> 2+ competitors
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-sm inline-block bg-amber-100 dark:bg-amber-900/40 border border-amber-300 dark:border-amber-700"></span> Scheduled
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-sm inline-block bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-600"></span> Pending
                </span>
            </div>
        </x-filament::section>

        {{-- Schedule columns --}}
        @if ($divisions->isEmpty())
            <x-filament::section>
                <p class="text-center text-gray-400 py-12">No divisions scheduled yet.</p>
            </x-filament::section>
        @else
            <div class="w-full overflow-x-auto pb-4">
            <div class="flex gap-4 items-start" style="min-width: max-content;">
                @foreach ($locations as $location)
                    @if ($divisions->has($location))
                        <div class="flex-none w-64">
                            <h2 class="text-xs font-bold uppercase tracking-widest text-gray-500 mb-3 pb-2 border-b-2 border-gray-200 dark:border-gray-700">
                                {{ $location }}
                            </h2>

                            <div class="space-y-2">
                                @foreach ($divisions[$location] as $div)
                                    @php
                                        $cardClass = match(true) {
                                            $div->status === 'complete'              => 'bg-green-100 dark:bg-green-900/40 border-green-300 dark:border-green-700',
                                            $div->active_enrolment_events_count >= 2 => 'bg-indigo-100 dark:bg-indigo-900/40 border-indigo-300 dark:border-indigo-700',
                                            $div->location_label !== null           => 'bg-amber-100 dark:bg-amber-900/40 border-amber-300 dark:border-amber-700',
                                            default                                 => 'bg-white dark:bg-slate-800 border-gray-300 dark:border-slate-600',
                                        };
                                    @endphp
                                    <div class="rounded-md border px-3 py-2 shadow-sm cursor-pointer {{ $cardClass }}">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="font-mono text-xs font-bold text-gray-900 dark:text-white">{{ $div->code }}</span>
                                            @if ($div->active_enrolment_events_count > 0)
                                                <span class="text-xs text-gray-500">{{ $div->active_enrolment_events_count }} <x-heroicon-m-user class="inline h-3 w-3 text-gray-400" /></span>
                                            @elseif ($div->status === 'complete')
                                                <x-heroicon-m-check-circle class="h-4 w-4 text-green-600" />
                                            @endif
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $div->competitionEvent->name }}</div>
                                        <div class="text-xs text-gray-700 dark:text-gray-300 mt-0.5">{{ $div->label }}</div>
                                        @if ($div->status === 'complete')
                                            @php
                                                $placements = $div->activeEnrolmentEvents
                                                    ->filter(fn ($ee) => $ee->result?->placement)
                                                    ->sortBy(fn ($ee) => $ee->result->placement)
                                                    ->take(3);
                                            @endphp
                                            @foreach ($placements as $ee)
                                                @php
                                                    $profile = $ee->enrolment->competitor?->competitorProfile;
                                                    $pName = $profile
                                                        ? $profile->first_name . ' ' . $profile->surname
                                                        : ($ee->enrolment->competitor?->name ?? '—');
                                                    $medal = match($ee->result->placement) { 1 => '🥇', 2 => '🥈', 3 => '🥉', default => $ee->result->placement . '.' };
                                                @endphp
                                                <div class="text-xs text-gray-700 dark:text-gray-300 mt-0.5">{{ $medal }} {{ $pName }}</div>
                                            @endforeach
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
            </div>
        @endif
    @endif
</x-filament-panels::page>
