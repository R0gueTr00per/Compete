<x-filament-panels::page>
    @php $history = $this->getHistory(); @endphp

    @if ($history->isEmpty())
        <p class="text-center text-gray-400 py-12">No competition results yet.</p>
    @else
        <div class="space-y-8">
            @foreach ($history as $competitionId => $enrolments)
                @php $competition = $enrolments->first()->competition; @endphp

                <div>
                    <div class="flex items-baseline gap-3 mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-base font-semibold text-gray-800 dark:text-gray-200">
                            {{ $competition->name }}
                        </h2>
                        <span class="text-xs text-gray-400">
                            {{ $competition->competition_date->format('j M Y') }}
                        </span>
                        @if ($competition->status === 'running')
                            <span class="text-xs text-warning-600 dark:text-warning-400">(in progress)</span>
                        @endif
                    </div>

                    <div class="space-y-4">
                        @foreach ($enrolments as $enrolment)
                            @php
                                $profileName = $enrolment->competitor?->full_name ?? '—';
                                $events = $enrolment->activeEvents->sortBy(fn ($ee) =>
                                    ($ee->competitionEvent?->running_order ?? 999)
                                );
                            @endphp

                            @if ($events->isEmpty())
                                @continue
                            @endif

                            <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                                <div class="px-4 py-2.5 bg-gray-50 dark:bg-gray-800/60">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ $profileName }}
                                    </span>
                                </div>

                                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach ($events as $ee)
                                        @php
                                            $result    = $ee->result;
                                            $eventName = $ee->competitionEvent?->name ?? '—';
                                            $divLabel  = $ee->division?->label ?: $ee->division?->full_label;
                                        @endphp
                                        <div class="flex items-center gap-3 px-4 py-2.5">
                                            <span class="w-8 text-center font-bold text-lg shrink-0">
                                                @if ($result?->disqualified || ! $result?->placement)
                                                    <span class="text-gray-300 text-sm">—</span>
                                                @else
                                                    @switch($result->placement)
                                                        @case(1) 🥇 @break
                                                        @case(2) 🥈 @break
                                                        @case(3) 🥉 @break
                                                        @default <span class="text-sm text-gray-500">{{ $result->placement }}</span>
                                                    @endswitch
                                                @endif
                                            </span>

                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ $eventName }}
                                                    @if ($result?->disqualified)
                                                        <span class="ml-1 text-xs text-danger-600">DQ</span>
                                                    @endif
                                                </p>
                                                @if ($divLabel)
                                                    <p class="text-xs text-gray-400">{{ $divLabel }}</p>
                                                @endif
                                            </div>

                                            <div class="text-right text-sm shrink-0 text-gray-500 dark:text-gray-400">
                                                @if ($result)
                                                    @php $method = $ee->competitionEvent?->effectiveScoringMethod(); @endphp
                                                    @if ($result->total_score !== null && in_array($method, ['judges_total', 'judges_average']))
                                                        {{ number_format((float) $result->total_score, 2) }}
                                                    @elseif ($result->total_score !== null && $method === 'first_to_n')
                                                        {{ (int) $result->total_score }} pts
                                                    @elseif ($result->win_loss)
                                                        <span class="{{ $result->win_loss === 'win' ? 'text-success-600' : 'text-danger-600' }}">
                                                            {{ ucfirst($result->win_loss) }}
                                                        </span>
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
</x-filament-panels::page>
