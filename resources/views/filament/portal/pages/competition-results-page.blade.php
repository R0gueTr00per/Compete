<x-filament-panels::page>
    @php $history = $this->getHistory(); @endphp

    @if ($history->isEmpty())
        <p class="text-center text-gray-400 py-12">No competition results yet.</p>
    @else
        <div class="space-y-4">
            @foreach ($history as $competitionId => $enrolments)
                @php
                    $competition = $enrolments->first()->competition;

                    $statusLabel = match($competition->status) {
                        'running'  => 'In Progress',
                        'complete' => 'Finished',
                        default    => ucfirst($competition->status),
                    };

                    $statusBadgeClass = match($competition->status) {
                        'running' => 'bg-blue-100/60 text-blue-700 border-blue-200/60 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-700/40',
                        default   => 'bg-gray-100/60 text-gray-500 border-gray-200/60 dark:bg-gray-800/40 dark:text-gray-400 dark:border-gray-700/40',
                    };

                    $statusIcon = match($competition->status) {
                        'running'  => 'heroicon-m-play',
                        'complete' => 'heroicon-m-check',
                        default    => null,
                    };
                @endphp

                <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800" x-data="{ day: 'all' }">

                    {{-- Competition header --}}
                    <div class="px-4 py-3 border-b border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-900 flex items-center gap-3">
                        <div class="flex-shrink-0 flex flex-col items-center justify-center w-11 h-11 rounded-lg bg-primary-500 dark:bg-primary-600 text-white text-center leading-none select-none">
                            <span class="text-[0.6rem] font-bold uppercase tracking-wide opacity-90">
                                {{ $competition->competition_date->format('M') }}
                            </span>
                            <span class="text-lg font-bold leading-none mt-0.5">
                                {{ $competition->competition_date->format('j') }}
                            </span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $competition->name }}</p>
                                @if ($competition->status === 'running')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium border {{ $statusBadgeClass }}">
                                        <x-dynamic-component :component="$statusIcon" class="w-3 h-3 flex-shrink-0" />
                                        {{ $statusLabel }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                @if ($competition->location_name)
                                    @if ($competition->location_url)
                                        <a href="{{ $competition->location_url }}" target="_blank" rel="noopener noreferrer" class="hover:underline">{{ $competition->location_name }}</a>
                                    @else
                                        {{ $competition->location_name }}
                                    @endif
                                @endif
                                @if ($competition->checkin_time)
                                    @if ($competition->location_name) &mdash; @endif
                                    Check-in {{ tenant_time($competition->checkin_time) }}
                                @endif
                                @if ($competition->start_time)
                                    @if ($competition->location_name || $competition->checkin_time) &mdash; @endif
                                    Starts {{ tenant_time($competition->start_time) }}
                                @endif
                                @if ($competition->end_time)
                                    &mdash; Ends {{ tenant_time($competition->end_time) }}
                                @endif
                            </p>
                        </div>
                    </div>

                    {{-- Day filter (multi-day only) --}}
                    @if ($competition->competitionDays->isNotEmpty())
                        <div class="px-4 py-2 border-b border-gray-100 dark:border-slate-700 flex items-center gap-2 flex-wrap">
                            <button type="button"
                                x-on:click="day = 'all'"
                                :class="day === 'all' ? 'bg-primary-500 text-white border-primary-500' : 'bg-white dark:bg-slate-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-slate-600 hover:border-primary-400 dark:hover:border-primary-500'"
                                class="px-3 py-1 rounded-full text-xs font-medium border transition-colors">
                                All days
                            </button>
                            @foreach ($competition->competitionDays->sortBy('date') as $cday)
                                <button type="button"
                                    x-on:click="day = '{{ $cday->id }}'"
                                    :class="day === '{{ $cday->id }}' ? 'bg-primary-500 text-white border-primary-500' : 'bg-white dark:bg-slate-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-slate-600 hover:border-primary-400 dark:hover:border-primary-500'"
                                    class="px-3 py-1 rounded-full text-xs font-medium border transition-colors">
                                    {{ tenant_date($cday->date) }}@if($cday->label) &mdash; {{ $cday->label }}@endif
                                </button>
                            @endforeach
                        </div>
                    @endif

                    {{-- Profile rows --}}
                    <div class="divide-y divide-gray-100 dark:divide-slate-700">
                        @foreach ($enrolments as $enrolment)
                            @php
                                $profileName = $enrolment->competitor?->full_name ?? '—';
                                $events = $enrolment->activeEvents->sortBy(fn ($ee) =>
                                    ($ee->competitionEvent?->running_order ?? 999)
                                );
                                $enrolmentDayIds = $events->map(fn ($ee) => (string) ($ee->division?->competition_day_id ?? ''))->unique()->values()->all();
                            @endphp

                            @if ($events->isEmpty())
                                @continue
                            @endif

                            <div class="px-4 py-3"
                                 x-show="day === 'all' || {{ json_encode($enrolmentDayIds) }}.includes(day)"
                                 x-cloak>
                                <p class="text-sm font-medium text-gray-900 dark:text-white mb-2">{{ $profileName }}</p>

                                <div class="space-y-1.5">
                                    @foreach ($events as $ee)
                                        @php
                                            $result    = $ee->result;
                                            $eventName = $ee->competitionEvent?->name ?? '—';
                                            $divLabel  = $ee->division ? $ee->division->code . ' — ' . $ee->division->label : null;
                                        @endphp
                                        <div class="flex items-start gap-2 min-w-0"
                                             x-show="day === 'all' || day === '{{ $ee->division?->competition_day_id ?? '' }}'">
                                            <div class="w-14 shrink-0 flex justify-end pt-0.5">
                                                @if ($result)
                                                    @if ($result->disqualified)
                                                        <span class="text-danger-600 font-semibold text-xs">DQ</span>
                                                    @elseif ($result->placement)
                                                        @php
                                                            $placeClass = match($result->placement) {
                                                                1 => 'bg-yellow-50 text-yellow-700 border-yellow-300 dark:bg-yellow-900/20 dark:text-yellow-300 dark:border-yellow-600/50',
                                                                2 => 'bg-slate-50 text-slate-600 border-slate-300 dark:bg-slate-800/40 dark:text-slate-300 dark:border-slate-500/50',
                                                                3 => 'bg-orange-50 text-orange-700 border-orange-300 dark:bg-orange-900/20 dark:text-orange-300 dark:border-orange-600/50',
                                                                default => 'text-primary-600',
                                                            };
                                                        @endphp
                                                        <span class="inline-flex items-center justify-center w-14 py-0.5 rounded-full text-xs font-bold border {{ $placeClass }}">
                                                            @switch($result->placement)
                                                                @case(1) 🥇 1st @break
                                                                @case(2) 🥈 2nd @break
                                                                @case(3) 🥉 3rd @break
                                                                @default {{ $result->placement }}th
                                                            @endswitch
                                                        </span>
                                                    @elseif ($result->win_loss)
                                                        <span class="text-xs {{ $result->win_loss === 'win' ? 'text-success-600' : ($result->win_loss === 'loss' ? 'text-danger-600' : 'text-gray-500') }}">{{ ucfirst($result->win_loss) }}</span>
                                                    @endif
                                                @endif
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <span class="text-sm text-gray-800 dark:text-gray-200">{{ $eventName }}</span>
                                                @if ($divLabel)
                                                    <div class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $divLabel }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                @if ($enrolment->ai_summary)
                                    <div class="mt-2 flex items-start gap-1.5 rounded-lg border border-primary-200/70 dark:border-primary-600/30 bg-primary-50/30 dark:bg-primary-900/10 px-2.5 py-2">
                                        <x-heroicon-m-sparkles class="w-3.5 h-3.5 text-primary-400 dark:text-primary-500 shrink-0 mt-0.5" />
                                        <p class="text-xs italic text-gray-500 dark:text-gray-400 whitespace-pre-line">{!! nl2br(e($enrolment->ai_summary)) !!}</p>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    {{-- Generate insights / spinner --}}
                    @if (config('services.google_ai.api_key'))
                        @php
                            $anyMissingSummary   = $enrolments->whereNull('ai_summary')->isNotEmpty();
                            $activeEvents        = $enrolments->flatMap->activeEvents->filter(fn($e) => $e->division?->location_label !== null);
                            $allEventsCompleted  = $activeEvents->isNotEmpty() && $activeEvents->every(fn($e) => $e->result !== null);
                            $withinWindow        = $competition->completed_at && $competition->completed_at->isAfter(now()->subDays(7));
                            $canGenerateInsights = $anyMissingSummary && (
                                ($competition->status === 'running'  && $allEventsCompleted) ||
                                ($competition->status === 'complete' && $withinWindow)
                            );
                        @endphp
                        @if ($this->isSummaryGenerating($competition->id))
                            <div wire:poll.10s class="px-4 py-3 border-t border-gray-100 dark:border-slate-700 flex items-center gap-2 text-xs text-gray-400 dark:text-gray-500">
                                <svg class="w-3.5 h-3.5 animate-spin text-primary-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                                </svg>
                                <span class="italic">Generating insights…</span>
                            </div>
                        @elseif ($canGenerateInsights)
                            <div class="px-4 py-3 border-t border-gray-100 dark:border-slate-700">
                                <button wire:click="triggerInsights({{ $competition->id }})"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border border-primary-200 dark:border-primary-700 text-primary-600 dark:text-primary-400 bg-primary-50/50 dark:bg-primary-900/10 hover:bg-primary-100/60 dark:hover:bg-primary-900/20 transition-colors">
                                    <x-heroicon-m-sparkles class="w-3.5 h-3.5" />
                                    Generate insights
                                </button>
                            </div>
                        @endif
                    @endif

                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
