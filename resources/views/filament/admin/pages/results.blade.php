<x-filament-panels::page>
    {{-- Competition selector + filters --}}
    <div class="mb-5 rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 dark:border-primary-800 dark:bg-primary-950/30">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-primary-700 dark:text-primary-400">Competition</p>
        <x-filament::input.wrapper class="dark:bg-gray-900">
            <select wire:model.live="competition_id"
                class="w-full block border-0 bg-transparent dark:bg-gray-900 py-1.5 text-sm text-gray-900 dark:text-white focus:ring-0">
                <option value="">— Select competition —</option>
                @foreach ($this->getCompetitions() as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </x-filament::input.wrapper>

        @if ($this->competition_id)
            {{-- View switcher --}}
            <div class="mt-3 flex gap-1">
                @foreach (['events' => 'Results', 'by-competitor' => 'Competitor Rankings', 'by-dojo' => tenant_group_name() . ' Rankings'] as $view => $label)
                    <button
                        wire:click="$set('activeView', '{{ $view }}')"
                        class="px-3 py-1 rounded-full text-xs font-medium transition-colors
                            {{ $this->activeView === $view
                                ? 'bg-primary-600 text-white'
                                : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            {{-- Day filter --}}
            @php $competitionDays = $this->getCompetitionDays(); @endphp
            @if ($competitionDays->isNotEmpty())
                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                    <button
                        wire:click="$set('selectedDay', null)"
                        class="px-3 py-1 rounded-full text-xs font-medium border transition-colors
                            {{ $this->selectedDay === null
                                ? 'bg-primary-600 text-white border-primary-600'
                                : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                        All days
                    </button>
                    @foreach ($competitionDays as $cday)
                        <button
                            wire:click="$set('selectedDay', '{{ $cday->id }}')"
                            class="px-3 py-1 rounded-full text-xs font-medium border transition-colors
                                {{ (string) $this->selectedDay === (string) $cday->id
                                    ? 'bg-primary-600 text-white border-primary-600'
                                    : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                            {{ tenant_date($cday->date) }}@if($cday->label) &mdash; {{ $cday->label }}@endif
                        </button>
                    @endforeach
                </div>
            @endif

            @if ($this->activeView === 'events')
                {{-- Search row --}}
                <div class="mt-3 max-w-xs">
                    <div class="flex items-center rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 focus-within:ring-1 focus-within:ring-primary-500">
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search…"
                            inputmode="search"
                            enterkeyhint="search"
                            x-on:keydown.enter="$el.blur()"
                            class="flex-1 bg-transparent py-1.5 pl-3 pr-1 text-base text-gray-900 dark:text-white border-0 focus:outline-none focus:ring-0 min-w-0"
                        />
                        @if ($this->search)
                            <button
                                wire:click="$set('search', '')"
                                class="pr-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                aria-label="Clear search"
                            >
                                <x-heroicon-m-x-mark class="h-4 w-4" />
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
                        <option value="">— All {{ tenant_group_name_plural() }} —</option>
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
        @endif
    </div>

    @if (! $this->competition_id)
        <p class="text-center text-gray-400 py-12">Select a competition to view results.</p>
    @elseif ($this->activeView === 'events')
        @php $events = $this->getResultsData(); @endphp

        @if ($events->isEmpty())
            @if ($this->selectedEvent || $this->selectedDojo || $this->search)
                <p class="text-center text-gray-400 py-12">No events match your selection.</p>
            @else
                <p class="text-center text-gray-400 py-12">No scored events yet for this competition.</p>
            @endif
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
                                                $name    = $ee->enrolment->competitor?->full_name ?? '—';
                                                $dojo = $ee->enrolment->dojo_type === 'guest'
                                                    ? ($ee->enrolment->guest_style ?? 'Guest')
                                                    : ($ee->enrolment->dojo_name ?? '—');
                                            @endphp
                                            <div class="flex items-center gap-2 min-w-[160px] rounded-md border px-3 py-2
                                                {{ $result?->placement && $result->placement <= 3 && ! $result->disqualified
                                                    ? 'border-gray-300 dark:border-slate-600 bg-gray-200 dark:bg-slate-900'
                                                    : 'border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-800' }}
                                                {{ $result?->disqualified ? 'opacity-50' : '' }}">
                                                <span class="text-xl leading-none">
                                                    @if ($result?->disqualified)
                                                        <span class="text-sm font-bold text-gray-500">—</span>
                                                    @else
                                                        @switch($result?->placement)
                                                            @case(1) 🥇 @break
                                                            @case(2) 🥈 @break
                                                            @case(3) 🥉 @break
                                                            @default <span class="text-sm font-bold text-gray-500">{{ $result?->placement ?? '—' }}</span>
                                                        @endswitch
                                                    @endif
                                                </span>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-900 dark:text-white leading-tight">
                                                        {{ $name }}
                                                        @if ($result?->disqualified) <span class="text-xs text-danger-600 ml-1">DQ</span> @endif
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

    @elseif ($this->activeView === 'by-competitor')
        @php $tally = $this->getMedalTallyByCompetitor(); @endphp

        @if ($tally->isEmpty())
            <p class="text-center text-gray-400 py-12">No medal results yet for this competition.</p>
        @else
            {{-- Mobile: card list --}}
            <div class="block sm:hidden space-y-2">
                @foreach ($tally as $row)
                    <div class="rounded-lg border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 flex items-center gap-3">
                        <span class="shrink-0 text-sm font-bold text-gray-400 dark:text-gray-500 w-6 text-right">{{ $row['rank'] }}</span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $row['name'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $row['dojo'] }}</p>
                        </div>
                        <div class="shrink-0 flex items-center gap-2 text-sm">
                            @if ($row['gold']) <span class="font-semibold text-yellow-600 dark:text-yellow-400">🥇 {{ $row['gold'] }}</span> @endif
                            @if ($row['silver']) <span class="font-semibold text-gray-500 dark:text-gray-300">🥈 {{ $row['silver'] }}</span> @endif
                            @if ($row['bronze']) <span class="font-semibold text-amber-700 dark:text-amber-500">🥉 {{ $row['bronze'] }}</span> @endif
                            @if (!$row['gold'] && !$row['silver'] && !$row['bronze']) <span class="text-xs text-gray-400">—</span> @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Desktop: table --}}
            <div class="hidden sm:block rounded-lg border border-gray-200 dark:border-slate-700 overflow-hidden shadow-sm bg-white dark:bg-slate-900">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-slate-800 border-b border-gray-200 dark:border-slate-700">
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 w-12">#</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">Competitor</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">{{ tenant_group_name() }}</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-400">🥇</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-400">🥈</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-400">🥉</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                        @foreach ($tally as $row)
                            <tr class="hover:bg-black/10 dark:hover:bg-white/10">
                                <td class="px-4 py-2 text-sm font-bold text-gray-500 dark:text-gray-400">{{ $row['rank'] }}</td>
                                <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">{{ $row['name'] }}</td>
                                <td class="px-4 py-2 text-gray-500 dark:text-gray-400">{{ $row['dojo'] }}</td>
                                <td class="px-4 py-2 text-center font-semibold {{ $row['gold'] > 0 ? 'text-yellow-600 dark:text-yellow-400' : 'text-gray-300 dark:text-gray-600' }}">{{ $row['gold'] ?: '—' }}</td>
                                <td class="px-4 py-2 text-center font-semibold {{ $row['silver'] > 0 ? 'text-gray-500 dark:text-gray-300' : 'text-gray-300 dark:text-gray-600' }}">{{ $row['silver'] ?: '—' }}</td>
                                <td class="px-4 py-2 text-center font-semibold {{ $row['bronze'] > 0 ? 'text-amber-700 dark:text-amber-500' : 'text-gray-300 dark:text-gray-600' }}">{{ $row['bronze'] ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

    @elseif ($this->activeView === 'by-dojo')
        @php $tally = $this->getMedalTallyByDojo(); @endphp

        @if ($tally->isEmpty())
            <p class="text-center text-gray-400 py-12">No medal results yet for this competition.</p>
        @else
            {{-- Mobile: card list --}}
            <div class="block sm:hidden space-y-2">
                @foreach ($tally as $row)
                    <div class="rounded-lg border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 flex items-center gap-3">
                        <span class="shrink-0 text-sm font-bold text-gray-400 dark:text-gray-500 w-6 text-right">{{ $row['rank'] }}</span>
                        <p class="flex-1 min-w-0 text-sm font-medium text-gray-900 dark:text-white truncate">{{ $row['name'] }}</p>
                        <div class="shrink-0 flex items-center gap-2 text-sm">
                            @if ($row['gold']) <span class="font-semibold text-yellow-600 dark:text-yellow-400">🥇 {{ $row['gold'] }}</span> @endif
                            @if ($row['silver']) <span class="font-semibold text-gray-500 dark:text-gray-300">🥈 {{ $row['silver'] }}</span> @endif
                            @if ($row['bronze']) <span class="font-semibold text-amber-700 dark:text-amber-500">🥉 {{ $row['bronze'] }}</span> @endif
                            @if (!$row['gold'] && !$row['silver'] && !$row['bronze']) <span class="text-xs text-gray-400">—</span> @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Desktop: table --}}
            <div class="hidden sm:block rounded-lg border border-gray-200 dark:border-slate-700 overflow-hidden shadow-sm bg-white dark:bg-slate-900">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-slate-800 border-b border-gray-200 dark:border-slate-700">
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 w-12">#</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">{{ tenant_group_name() }}</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-400"><span class="text-xl">🥇</span></th>
                            <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-400"><span class="text-xl">🥈</span></th>
                            <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-400"><span class="text-xl">🥉</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                        @foreach ($tally as $row)
                            <tr class="hover:bg-black/10 dark:hover:bg-white/10">
                                <td class="px-4 py-2 text-sm font-bold text-gray-500 dark:text-gray-400">{{ $row['rank'] }}</td>
                                <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">{{ $row['name'] }}</td>
                                <td class="px-4 py-2 text-center font-semibold {{ $row['gold'] > 0 ? 'text-yellow-600 dark:text-yellow-400' : 'text-gray-300 dark:text-gray-600' }}">{{ $row['gold'] ?: '—' }}</td>
                                <td class="px-4 py-2 text-center font-semibold {{ $row['silver'] > 0 ? 'text-gray-500 dark:text-gray-300' : 'text-gray-300 dark:text-gray-600' }}">{{ $row['silver'] ?: '—' }}</td>
                                <td class="px-4 py-2 text-center font-semibold {{ $row['bronze'] > 0 ? 'text-amber-700 dark:text-amber-500' : 'text-gray-300 dark:text-gray-600' }}">{{ $row['bronze'] ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</x-filament-panels::page>
