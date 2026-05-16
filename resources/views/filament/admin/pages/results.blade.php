<x-filament-panels::page>
    {{-- Competition selector + filters --}}
    <div class="mb-5 rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 dark:border-primary-800 dark:bg-primary-950/30">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-primary-700 dark:text-primary-400">Competition</p>
        <x-filament::input.wrapper>
            <select wire:model.live="competition_id"
                class="w-full block border-0 bg-transparent dark:bg-gray-900 py-1.5 text-sm text-gray-900 dark:text-white focus:ring-0">
                <option value="">— Select competition —</option>
                @foreach ($this->getCompetitions() as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </x-filament::input.wrapper>

        @if ($this->competition_id)
            {{-- Search row --}}
            <div class="mt-3 max-w-xs">
                <div class="flex items-center rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 focus-within:ring-1 focus-within:ring-primary-500">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search…"
                        class="flex-1 bg-transparent py-1 pl-2 pr-1 text-xs text-gray-900 dark:text-white border-0 focus:outline-none focus:ring-0 min-w-0"
                    />
                    @if ($this->search)
                        <button
                            wire:click="$set('search', null)"
                            class="pr-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                            aria-label="Clear search"
                        >
                            <x-heroicon-m-x-mark class="w-3.5 h-3.5" />
                        </button>
                    @endif
                </div>
            </div>
            {{-- Filter row --}}
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <select wire:model.live="selectedEvent"
                    style="width: 13rem" class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 py-1 px-2 text-xs text-gray-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">— All events —</option>
                    @foreach ($this->getEventOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="selectedDojo"
                    style="width: 13rem" class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 py-1 px-2 text-xs text-gray-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">— All dojos —</option>
                    @foreach ($this->getDojoOptions() as $dojo)
                        <option value="{{ $dojo }}">{{ $dojo }}</option>
                    @endforeach
                </select>
                <label class="flex items-center gap-1.5 text-xs text-gray-700 dark:text-gray-300 cursor-pointer select-none whitespace-nowrap">
                    <input type="checkbox" wire:model.live="onlyPlacings"
                        class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800">
                    Top 3 only
                </label>
            </div>
        @endif
    </div>

    @if (! $this->competition_id)
        <p class="text-center text-gray-400 py-12">Select a competition to view results.</p>
    @else
        @php $events = $this->getResultsData(); @endphp

        @if ($events->isEmpty())
            <p class="text-center text-gray-400 py-12">No scored events yet for this competition.</p>
        @else
            <div class="space-y-6">
                @foreach ($events as $compEvent)
                    <div>
                        <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3">
                            {{ $compEvent->name }}
                        </h2>

                        <div class="space-y-4">
                            @foreach ($compEvent->divisions as $division)
                                @php
                                    $entries = $division->enrolmentEvents;
                                    if ($entries->isEmpty()) continue;
                                @endphp

                                <div class="rounded-lg border border-gray-200 dark:border-slate-700 overflow-hidden shadow-sm bg-white dark:bg-slate-900">
                                    <div class="px-4 py-2 bg-gray-50 dark:bg-slate-900 border-b border-gray-200 dark:border-slate-700">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ $division->full_label }}
                                        </span>
                                    </div>

                                    <div class="px-4 py-3 flex flex-wrap gap-2">
                                        @foreach ($entries as $ee)
                                            @php
                                                $result  = $ee->result;
                                                $profile = $ee->enrolment->competitor?->competitorProfile;
                                                $name    = $profile
                                                    ? "{$profile->first_name} {$profile->surname}"
                                                    : ($ee->enrolment->competitor?->name ?? '—');
                                                $dojo = $ee->enrolment->dojo_type === 'guest'
                                                    ? ($ee->enrolment->guest_style ?? 'Guest')
                                                    : ($ee->enrolment->dojo_name ?? '—');
                                            @endphp
                                            <div class="flex items-center gap-2 min-w-[160px] rounded-md border px-3 py-2
                                                {{ $result?->placement && $result->placement <= 3
                                                    ? 'border-gray-300 dark:border-slate-600 bg-gray-200 dark:bg-slate-900'
                                                    : 'border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-800' }}
                                                {{ $result?->disqualified ? 'opacity-50' : '' }}">
                                                <span class="text-xl leading-none">
                                                    @switch($result?->placement)
                                                        @case(1) 🥇 @break
                                                        @case(2) 🥈 @break
                                                        @case(3) 🥉 @break
                                                        @default <span class="text-sm font-bold text-gray-500">{{ $result?->placement ?? '—' }}</span>
                                                    @endswitch
                                                </span>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-900 dark:text-white leading-tight">
                                                        {{ $name }}
                                                        @if ($result?->disqualified) <span class="text-xs text-danger-600 ml-1">DQ</span> @endif
                                                        @if ($result?->placement_overridden) <span class="text-xs text-warning-600 ml-1">*</span> @endif
                                                    </p>
                                                    <p class="text-xs text-gray-400 leading-tight">{{ $dojo }}</p>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</x-filament-panels::page>
