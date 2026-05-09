<x-filament-panels::page>
    {{-- Competition selector --}}
    <div class="mb-5">
        <x-filament::input.wrapper>
            <select wire:model.live="competition_id"
                class="w-full block border-0 bg-transparent py-1.5 text-sm text-gray-900 dark:text-white focus:ring-0">
                <option value="">— Select competition —</option>
                @foreach ($this->getCompetitions() as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </x-filament::input.wrapper>
    </div>

    @if (! $this->competition_id)
        <p class="text-center text-gray-400 py-12">Select a competition to view results.</p>
    @else
        @php $events = $this->getResultsData(); @endphp

        @if ($events->isEmpty())
            <p class="text-center text-gray-400 py-12">No results available yet for this competition.</p>
        @else
            <div class="space-y-8">
                @foreach ($events as $compEvent)
                    <div>
                        <h2 class="text-base font-semibold text-gray-800 dark:text-gray-200 mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">
                            {{ $compEvent->name }}
                        </h2>

                        <div class="space-y-4">
                            @foreach ($compEvent->divisions as $division)
                                @php
                                    $entries = $division->enrolmentEvents
                                        ->sortBy(fn ($ee) => $ee->result?->placement ?? 999);
                                    if ($entries->isEmpty()) continue;
                                @endphp

                                <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                                    <div class="px-4 py-2.5 bg-gray-50 dark:bg-gray-800/60">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ $division->label ?: $division->full_label }}
                                        </span>
                                        @if ($division->status !== 'complete')
                                            <span class="ml-2 text-xs text-warning-600 dark:text-warning-400">(in progress)</span>
                                        @endif
                                    </div>

                                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                                        @foreach ($entries as $ee)
                                            @php
                                                $result  = $ee->result;
                                                $profile = $ee->enrolment->competitor?->competitorProfile;
                                                $name    = $profile
                                                    ? "{$profile->first_name} {$profile->surname}"
                                                    : ($ee->enrolment->competitor?->name ?? '—');
                                                $isMe    = $ee->enrolment->competitor_id === auth()->id();
                                            @endphp
                                            <div class="flex items-center gap-3 px-4 py-2.5 {{ $isMe ? 'bg-primary-50 dark:bg-primary-950/20' : '' }}">
                                                <span class="w-8 text-center font-bold text-lg shrink-0">
                                                    @if ($result?->placement)
                                                        @switch($result->placement)
                                                            @case(1) 🥇 @break
                                                            @case(2) 🥈 @break
                                                            @case(3) 🥉 @break
                                                            @default <span class="text-sm text-gray-500">{{ $result->placement }}</span>
                                                        @endswitch
                                                    @else
                                                        <span class="text-gray-300 text-sm">—</span>
                                                    @endif
                                                </span>

                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-medium {{ $isMe ? 'text-primary-700 dark:text-primary-300' : 'text-gray-900 dark:text-white' }}">
                                                        {{ $name }}
                                                        @if ($isMe)
                                                            <span class="ml-1 text-xs font-normal text-primary-500">(you)</span>
                                                        @endif
                                                        @if ($result?->disqualified)
                                                            <span class="ml-1 text-xs text-danger-600">DQ</span>
                                                        @endif
                                                    </p>
                                                </div>

                                                <div class="text-right text-sm shrink-0 text-gray-500 dark:text-gray-400">
                                                    @if ($result)
                                                        @if ($result->total_score !== null && in_array($compEvent->effectiveScoringMethod(), ['judges_total', 'judges_average']))
                                                            {{ number_format((float) $result->total_score, 2) }}
                                                        @elseif ($result->total_score !== null && $compEvent->effectiveScoringMethod() === 'first_to_n')
                                                            {{ (int) $result->total_score }} pts
                                                        @elseif ($result->win_loss)
                                                            <span class="{{ $result->win_loss === 'win' ? 'text-success-600' : 'text-danger-600' }}">{{ ucfirst($result->win_loss) }}</span>
                                                        @endif
                                                    @endif
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
