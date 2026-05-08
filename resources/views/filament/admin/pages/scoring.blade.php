<x-filament-panels::page>
    {{-- Top bar: competition + location --}}
    <div class="flex flex-wrap gap-3 mb-4">
        <x-filament::input.wrapper class="flex-1 min-w-48">
            <select wire:model.live="competition_id"
                class="w-full block border-0 bg-transparent py-1.5 text-sm text-gray-900 dark:text-white focus:ring-0">
                <option value="">— Competition —</option>
                @foreach ($this->getCompetitions() as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </x-filament::input.wrapper>

        @php $locations = $this->getLocations(); @endphp
        @if (! empty($locations))
            <x-filament::input.wrapper class="min-w-40">
                <select wire:model.live="filter_location"
                    class="w-full block border-0 bg-transparent py-1.5 text-sm text-gray-900 dark:text-white focus:ring-0">
                    <option value="">— All locations —</option>
                    @foreach ($locations as $loc)
                        <option value="{{ $loc }}">{{ $loc }}</option>
                    @endforeach
                </select>
            </x-filament::input.wrapper>
        @endif
    </div>

    @php $divisionList = $this->getDivisionList(); @endphp

    @if (! $this->competition_id)
        <p class="text-center text-gray-400 py-12">Select a competition to begin scoring.</p>
    @elseif ($divisionList->isEmpty())
        <p class="text-center text-gray-400 py-12">No divisions scheduled for this competition{{ $this->filter_location ? ' at ' . $this->filter_location : '' }}.</p>
    @else
        {{-- Division list --}}
        <div class="space-y-1 mb-4">
            @foreach ($divisionList as $item)
                @php
                    $div      = $item->division;
                    $selected = $this->division_id === $div->id;
                    $rowClass = match ($div->status) {
                        'complete' => 'bg-success-50 border-success-300 dark:bg-success-900/20 dark:border-success-700',
                        'running'  => 'bg-warning-50 border-warning-300 dark:bg-warning-900/20 dark:border-warning-700',
                        default    => 'bg-white border-gray-200 dark:bg-gray-900 dark:border-gray-700',
                    };
                    $textClass = match ($div->status) {
                        'complete' => 'text-success-800 dark:text-success-300',
                        'running'  => 'text-warning-800 dark:text-warning-300',
                        default    => 'text-gray-900 dark:text-white',
                    };
                @endphp
                <div
                    wire:click="selectDivision({{ $div->id }})"
                    class="flex items-center justify-between gap-3 rounded-lg border px-4 py-3 cursor-pointer transition-all
                        {{ $rowClass }}
                        {{ $selected ? 'ring-2 ring-primary-500' : 'hover:border-primary-300 dark:hover:border-primary-600' }}"
                >
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="font-mono text-sm font-bold shrink-0 {{ $textClass }}">{{ $div->code }}</span>
                        <div class="min-w-0">
                            <p class="text-sm font-medium {{ $textClass }} truncate">
                                {{ $div->competitionEvent->eventType->name }}
                                @if ($div->competitionEvent->location_label)
                                    <span class="font-normal text-gray-500 dark:text-gray-400">— {{ $div->competitionEvent->location_label }}</span>
                                @endif
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $div->label }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 shrink-0">
                        <span class="text-xs text-gray-500">{{ $item->checked_in_count }} checked in</span>

                        @if ($div->status === 'complete')
                            <x-heroicon-m-check-circle class="w-5 h-5 text-success-500" />
                        @elseif ($div->status === 'running')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-warning-100 text-warning-800 dark:bg-warning-900 dark:text-warning-200">Running</span>
                        @else
                            <x-heroicon-m-chevron-right class="w-4 h-4 text-gray-400 {{ $selected ? 'rotate-90' : '' }}" />
                        @endif
                    </div>
                </div>

                {{-- Inline scoring panel --}}
                @if ($selected)
                    @php
                        $rows   = $this->getCompetitorRows();
                        $method = $this->getScoringMethod();
                        $judges = $this->getJudgeCount();
                    @endphp
                    <div class="ml-4 mb-2 rounded-lg border border-primary-200 dark:border-primary-700 bg-primary-50/50 dark:bg-primary-900/10 p-4">
                        @if ($rows->isEmpty())
                            <p class="text-center text-sm text-gray-400 py-4">No checked-in competitors in this division.</p>
                        @else
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-gray-200 dark:border-gray-700 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">
                                            <th class="pb-2 pr-4">Competitor</th>
                                            @if (in_array($method, ['judges_total', 'judges_average']))
                                                @for ($j = 1; $j <= $judges; $j++)
                                                    <th class="pb-2 pr-2">J{{ $j }}</th>
                                                @endfor
                                                <th class="pb-2 pr-4">Total</th>
                                            @elseif ($method === 'win_loss')
                                                <th class="pb-2 pr-4">Result</th>
                                            @elseif ($method === 'first_to_n')
                                                <th class="pb-2 pr-4">Points</th>
                                            @endif
                                            <th class="pb-2 pr-4">Place</th>
                                            <th class="pb-2">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                        @foreach ($rows as $row)
                                            @php $result = $row->result; @endphp
                                            <tr class="{{ $result->disqualified ? 'opacity-50' : '' }}">
                                                <td class="py-2 pr-4 font-medium text-gray-900 dark:text-white">
                                                    {{ $row->name }}
                                                    @if ($result->disqualified)
                                                        <span class="ml-1 text-xs text-danger-600">DQ</span>
                                                    @endif
                                                </td>

                                                @if (in_array($method, ['judges_total', 'judges_average']))
                                                    @for ($j = 1; $j <= $judges; $j++)
                                                        <td class="py-2 pr-2">
                                                            <x-filament::input type="number" step="0.1" min="0" max="10"
                                                                wire:model="judgeScores.{{ $result->id }}.{{ $j }}"
                                                                class="w-16" placeholder="0.0" />
                                                        </td>
                                                    @endfor
                                                    <td class="py-2 pr-4">
                                                        <span class="font-semibold">
                                                            {{ $result->total_score ? number_format((float)$result->total_score, 1) : '—' }}
                                                        </span>
                                                        <x-filament::button size="xs" color="primary"
                                                            wire:click="saveJudgeScores({{ $result->id }})" class="ml-1">
                                                            Save
                                                        </x-filament::button>
                                                    </td>

                                                @elseif ($method === 'win_loss')
                                                    <td class="py-2 pr-4">
                                                        <div class="flex gap-1">
                                                            <x-filament::button size="xs"
                                                                color="{{ $result->win_loss === 'win' ? 'success' : 'gray' }}"
                                                                wire:click="saveWinLoss({{ $result->id }}, 'win')">W</x-filament::button>
                                                            <x-filament::button size="xs"
                                                                color="{{ $result->win_loss === 'loss' ? 'danger' : 'gray' }}"
                                                                wire:click="saveWinLoss({{ $result->id }}, 'loss')">L</x-filament::button>
                                                            <x-filament::button size="xs"
                                                                color="{{ $result->win_loss === 'draw' ? 'warning' : 'gray' }}"
                                                                wire:click="saveWinLoss({{ $result->id }}, 'draw')">D</x-filament::button>
                                                        </div>
                                                    </td>

                                                @elseif ($method === 'first_to_n')
                                                    <td class="py-2 pr-4">
                                                        <div class="flex items-center gap-1">
                                                            <x-filament::input type="number" min="0"
                                                                wire:model="pointsInput.{{ $result->id }}"
                                                                class="w-16" placeholder="0" />
                                                            <x-filament::button size="xs" color="primary"
                                                                wire:click="savePoints({{ $result->id }})">
                                                                Save
                                                            </x-filament::button>
                                                        </div>
                                                    </td>
                                                @endif

                                                <td class="py-2 pr-4">
                                                    @if ($result->placement)
                                                        <span class="font-bold {{ $result->placement_overridden ? 'text-warning-600' : '' }}">
                                                            @switch($result->placement)
                                                                @case(1) 🥇 @break
                                                                @case(2) 🥈 @break
                                                                @case(3) 🥉 @break
                                                                @default {{ $result->placement }}
                                                            @endswitch
                                                            @if ($result->placement_overridden)
                                                                <span class="text-xs font-normal">(ov)</span>
                                                            @endif
                                                        </span>
                                                    @else
                                                        <span class="text-gray-400">—</span>
                                                    @endif
                                                </td>

                                                <td class="py-2">
                                                    <div class="flex flex-wrap gap-1">
                                                        <x-filament::input type="number" min="1"
                                                            wire:model="placementInput.{{ $result->id }}"
                                                            class="w-12" placeholder="#" />
                                                        <x-filament::button size="xs" color="warning"
                                                            wire:click="overridePlacement({{ $result->id }})">
                                                            Override
                                                        </x-filament::button>
                                                        @if ($result->placement_overridden)
                                                            <x-filament::button size="xs" color="gray"
                                                                wire:click="clearOverride({{ $result->id }})">
                                                                Auto
                                                            </x-filament::button>
                                                        @endif
                                                        <x-filament::button size="xs"
                                                            color="{{ $result->disqualified ? 'gray' : 'danger' }}"
                                                            wire:click="toggleDisqualify({{ $result->id }})">
                                                            {{ $result->disqualified ? 'Un-DQ' : 'DQ' }}
                                                        </x-filament::button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        {{-- Not checked in --}}
                        @php
                            $notIn = \App\Models\EnrolmentEvent::where('division_id', $this->division_id)
                                ->where('removed', false)
                                ->whereHas('enrolment', fn ($q) => $q->whereNotIn('status', ['checked_in', 'withdrawn']))
                                ->with('enrolment.competitor.competitorProfile')
                                ->get();
                        @endphp
                        @if ($notIn->isNotEmpty())
                            <p class="mt-3 text-xs text-gray-500 font-medium uppercase tracking-wide">Not checked in ({{ $notIn->count() }})</p>
                            <ul class="mt-1 text-xs text-gray-400 space-y-0.5">
                                @foreach ($notIn as $ee)
                                    @php $p = $ee->enrolment->competitor?->competitorProfile; @endphp
                                    <li>{{ $p ? "{$p->surname}, {$p->first_name}" : $ee->enrolment->competitor?->name }}</li>
                                @endforeach
                            </ul>
                        @endif

                        {{-- Mark complete --}}
                        @if ($div->status !== 'complete')
                            <div class="mt-4 flex justify-end">
                                <x-filament::button color="success" size="sm"
                                    wire:click="markDivisionComplete"
                                    wire:confirm="Mark this division as complete? This cannot be undone.">
                                    Mark division complete
                                </x-filament::button>
                            </div>
                        @endif
                    </div>
                @endif
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
