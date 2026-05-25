<x-filament-panels::page>
    @php
        $competitions  = $this->getActiveCompetitions();
        $isOrgAdmin    = $this->isOrgAdmin();
        $officialRole  = $this->getOfficialRole();
    @endphp

    <style>
        @keyframes chevron-pulse {
            0%, 100% { filter: drop-shadow(0 0 3px var(--primary-glow, #818cf8)); }
            50%       { filter: drop-shadow(0 0 9px var(--primary-glow, #818cf8)) drop-shadow(0 0 2px var(--primary-glow, #818cf8)); }
        }
        @keyframes chevron-activate {
            0%   { transform: scale(1) translateY(0); }
            38%  { transform: scale(1.07) translateY(-5px); }
            68%  { transform: scale(0.96) translateY(2px); }
            100% { transform: scale(1) translateY(0); }
        }
        @keyframes chevron-enter {
            0%   { filter: drop-shadow(0 0 0px rgba(255,255,255,0)); }
            35%  { filter: drop-shadow(0 0 8px rgba(255,255,255,0.65)); }
            100% { filter: drop-shadow(0 0 0px rgba(255,255,255,0)); }
        }
        .chevron-first  { clip-path: polygon(0 0, calc(100% - 11px) 0, 100% 50%, calc(100% - 11px) 100%, 0 100%); }
        .chevron-middle { clip-path: polygon(0 0, calc(100% - 11px) 0, 100% 50%, calc(100% - 11px) 100%, 0 100%, 11px 50%); }
        .chevron-last   { clip-path: polygon(0 0, 100% 0, 100% 100%, 0 100%, 11px 50%); }
        .chevron-pulse-active  { animation: chevron-pulse 2.5s ease-in-out infinite; }
        .chevron-bounce        { animation: chevron-activate 400ms cubic-bezier(0.34, 1.56, 0.64, 1) both !important; }
        .chevron-entering      { animation: chevron-enter 850ms ease-out both; }
        .chevron-partial-left  { clip-path: polygon(0 0, 100% 50%, 0 100%); }
        .chevron-partial-right { clip-path: polygon(0 0, 100% 0, 100% 100%, 0 100%, 11px 50%); }
    </style>

    @if ($competitions->isEmpty())
        <x-filament::section>
            <p class="text-center text-gray-500 py-8">No active competitions.@if($isOrgAdmin) <a href="{{ route('filament.org-admin.resources.competitions.create') }}" class="text-primary-600 underline">Create one</a>.@endif</p>
        </x-filament::section>
    @else
        <div class="grid gap-4 overflow-x-hidden">
            @foreach ($competitions as $competition)
                @php
                    $statusLabel = match ($competition->status) {
                        'planning'    => 'Planning',
                        'check_in' => 'Check-in',
                        default    => ucfirst($competition->status),
                    };
                    $statusBadgeClass = match ($competition->status) {
                        'running'  => 'bg-blue-100/60 text-blue-700 border-blue-200/60 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-700/40',
                        'check_in' => 'bg-amber-100/60 text-amber-700 border-amber-200/60 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-700/40',
                        'open'     => 'bg-green-100/60 text-green-700 border-green-200/60 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700/40',
                        'complete' => 'bg-gray-100/60 text-gray-500 border-gray-200/60 dark:bg-gray-800/40 dark:text-gray-400 dark:border-gray-700/40',
                        default    => 'bg-gray-100/60 text-gray-500 border-gray-200/60 dark:bg-gray-800/40 dark:text-gray-400 dark:border-gray-700/40',
                    };
                    $enrolmentsColor = $competition->status === 'open'     ? 'success'  : 'gray';
                    $checkInColor    = $competition->status === 'check_in' ? 'primary'  : 'gray';
                    $schedulingColor = $competition->status === 'closed'   ? 'primary'  : 'gray';
                    $scoringColor    = $competition->status === 'running'  ? 'warning'  : 'gray';
                @endphp
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span>{{ $competition->name }}</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border {{ $statusBadgeClass }}">
                                {{ $statusLabel }}
                            </span>
                        </div>
                    </x-slot>
                    <x-slot name="description">
                        {{ $competition->competition_date->format('d M Y') }}
                        @if ($competition->location_name)
                            &mdash; {{ $competition->location_name }}
                        @endif
                        <br class="sm:hidden"><span class="hidden sm:inline"> &bull; </span>{{ $competition->enrolments_count }} enrolment{{ $competition->enrolments_count !== 1 ? 's' : '' }}
                        @if (in_array($competition->status, ['check_in', 'running']))
                            &bull; {{ $competition->checkins_count }} checked in
                        @endif
                        @if ($competition->status === 'running')
                            &bull; {{ $competition->total_divisions_count }} division{{ $competition->total_divisions_count !== 1 ? 's' : '' }} ({{ $competition->completed_divisions_count }} completed)
                        @elseif ($competition->events_count > 0)
                            &bull; {{ $competition->events_count }} event{{ $competition->events_count !== 1 ? 's' : '' }}
                        @endif
                    </x-slot>

                    @php
                        $allStatuses = ['planning', 'open', 'closed', 'check_in', 'running', 'complete'];
                        $stepLine1   = ['planning' => 'Planning', 'open' => 'Open for', 'closed' => 'Enrolments', 'check_in' => 'Check-in', 'running' => 'Running', 'complete' => 'Complete'];
                        $stepLine2   = ['planning' => '',        'open' => 'Enrolments', 'closed' => 'Closed',   'check_in' => '',          'running' => '',        'complete' => ''];
                        $stepTitle   = ['planning' => 'Planning', 'open' => 'Open for Enrolments', 'closed' => 'Enrolments Closed', 'check_in' => 'Check-in', 'running' => 'Running', 'complete' => 'Complete'];
                        $currentIdx  = (int) array_search($competition->status, $allStatuses);
                        $totalSteps  = count($allStatuses);

                        $mobileCount   = 3;
                        $mobileStart   = max(0, min($currentIdx - 1, $totalSteps - $mobileCount));
                        $mobileVisible = range($mobileStart, $mobileStart + $mobileCount - 1);

                        $leftDotCount  = $mobileVisible[0];
                        $rightDotCount = $totalSteps - 1 - end($mobileVisible);
                    @endphp

                    {{-- Desktop: all 6 chevrons --}}
                    <div class="hidden sm:grid mb-4" style="grid-template-columns: repeat({{ $totalSteps }}, 1fr);">
                        @foreach ($allStatuses as $i => $step)
                            @php
                                $isPast      = $i < $currentIdx;
                                $isCurrent   = $i === $currentIdx;
                                $isClickable = $isOrgAdmin && ! $isCurrent;
                                $line1       = $stepLine1[$step];
                                $line2       = $stepLine2[$step];
                                $title       = $stepTitle[$step];
                                $bgClass     = $isCurrent ? 'bg-primary-500' : ($isPast ? 'bg-gray-400 dark:bg-gray-500' : 'bg-gray-200 dark:bg-gray-700');
                                $textClass   = $isCurrent || $isPast ? 'text-white' : 'text-gray-500 dark:text-gray-400';
                                $shapeClass  = $loop->first ? 'chevron-first' : ($loop->last ? 'chevron-last' : 'chevron-middle');
                            @endphp
                            <div
                                wire:key="{{ $competition->id }}-step-{{ $step }}"
                                class="relative {{ $isCurrent ? 'chevron-pulse-active' : '' }} {{ $isClickable ? 'hover:-translate-y-0.5 hover:brightness-110 cursor-pointer' : '' }}"
                                style="z-index: {{ $i + 1 }}; {{ $loop->last ? '' : 'margin-right: -11px;' }} transition: all 350ms cubic-bezier(0.34,1.56,0.64,1);"
                                x-data="{ show: false, bouncing: false, entering: false }"
                                x-init="setTimeout(() => { show = true; {{ $isPast ? 'entering = true; setTimeout(() => entering = false, 900);' : '' }} }, {{ $i * 120 }})"
                                :class="{ 'opacity-0 -translate-x-2': !show, 'chevron-bounce': bouncing, 'chevron-entering': entering }"
                                @competition-status-changed.window="if ($event.detail.competitionId == {{ $competition->id }} && $event.detail.newStatus === '{{ $step }}') { bouncing = true; setTimeout(() => bouncing = false, 450); }"
                            >
                                @if ($isClickable)
                                    <button
                                        type="button"
                                        class="{{ $shapeClass }} {{ $bgClass }} {{ $textClass }} w-full h-12 flex flex-col items-center justify-center px-3 select-none"
                                        x-on:click="$wire.mountAction('setStatus', { competitionId: {{ $competition->id }}, targetStatus: '{{ $step }}' })"
                                        title="{{ $title }}"
                                    >
                                        <span class="text-xs font-semibold leading-none">{{ $line1 }}</span>
                                        @if ($line2)<span class="text-xs leading-none mt-0.5 opacity-90">{{ $line2 }}</span>@endif
                                    </button>
                                @else
                                    <div class="{{ $shapeClass }} {{ $bgClass }} {{ $textClass }} w-full h-12 flex flex-col items-center justify-center px-3 select-none">
                                        <span class="text-xs font-semibold leading-none">{{ $line1 }}</span>
                                        @if ($line2)<span class="text-xs leading-none mt-0.5 opacity-90">{{ $line2 }}</span>@endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    {{-- Mobile: 3-step window + partial chevron indicators --}}
                    <div class="flex sm:hidden items-center mb-4">
                        {{-- Left partial: past chevron tip peeking in --}}
                        @if ($leftDotCount > 0)
                            <div class="chevron-partial-left bg-gray-400 dark:bg-gray-500 flex-shrink-0 h-14" style="width: 20px; margin-right: -11px; z-index: 0; position: relative;"></div>
                        @endif

                        @foreach ($mobileVisible as $mIdx => $i)
                            @php
                                $step        = $allStatuses[$i];
                                $isPast      = $i < $currentIdx;
                                $isCurrent   = $i === $currentIdx;
                                $isClickable = $isOrgAdmin && ! $isCurrent;
                                $line1       = $stepLine1[$step];
                                $line2       = $stepLine2[$step];
                                $title       = $stepTitle[$step];
                                $bgClass     = $isCurrent ? 'bg-primary-500' : ($isPast ? 'bg-gray-400 dark:bg-gray-500' : 'bg-gray-200 dark:bg-gray-700');
                                $textClass   = $isCurrent || $isPast ? 'text-white' : 'text-gray-500 dark:text-gray-400';
                                $isFirst     = $mIdx === 0;
                                $isLast      = $mIdx === $mobileCount - 1;
                                $shapeClass  = $isFirst
                                    ? ($leftDotCount  > 0 ? 'chevron-middle' : 'chevron-first')
                                    : ($isLast
                                        ? ($rightDotCount > 0 ? 'chevron-middle' : 'chevron-last')
                                        : 'chevron-middle');
                                $marginRight = ($isLast && $rightDotCount === 0) ? '' : 'margin-right: -11px;';
                            @endphp
                            <div
                                wire:key="{{ $competition->id }}-mobile-step-{{ $step }}"
                                class="relative flex-shrink-0 {{ $isCurrent ? 'chevron-pulse-active' : '' }}"
                                style="z-index: {{ $mIdx + 1 }}; {{ $marginRight }} transition: all 350ms cubic-bezier(0.34,1.56,0.64,1); width: 6rem;"
                                x-data="{ show: false, bouncing: false, entering: false }"
                                x-init="setTimeout(() => { show = true; {{ $isPast ? 'entering = true; setTimeout(() => entering = false, 900);' : '' }} }, {{ $mIdx * 120 }})"
                                :class="{ 'opacity-0 -translate-x-2': !show, 'chevron-bounce': bouncing, 'chevron-entering': entering }"
                                @competition-status-changed.window="if ($event.detail.competitionId == {{ $competition->id }} && $event.detail.newStatus === '{{ $step }}') { bouncing = true; setTimeout(() => bouncing = false, 450); }"
                            >
                                @if ($isClickable)
                                    <button
                                        type="button"
                                        class="{{ $shapeClass }} {{ $bgClass }} {{ $textClass }} w-full h-14 flex flex-col items-center justify-center px-3 select-none"
                                        x-on:click="$wire.mountAction('setStatus', { competitionId: {{ $competition->id }}, targetStatus: '{{ $step }}' })"
                                        title="{{ $title }}"
                                    >
                                        <span class="text-sm font-semibold leading-none">{{ $line1 }}</span>
                                        @if ($line2)<span class="text-xs leading-none mt-0.5 opacity-90">{{ $line2 }}</span>@endif
                                    </button>
                                @else
                                    <div class="{{ $shapeClass }} {{ $bgClass }} {{ $textClass }} w-full h-14 flex flex-col items-center justify-center px-3 select-none">
                                        <span class="text-sm font-semibold leading-none">{{ $line1 }}</span>
                                        @if ($line2)<span class="text-xs leading-none mt-0.5 opacity-90">{{ $line2 }}</span>@endif
                                    </div>
                                @endif
                            </div>
                        @endforeach

                        {{-- Right partial: future chevron notch peeking in --}}
                        @if ($rightDotCount > 0)
                            <div class="chevron-partial-right bg-gray-200 dark:bg-gray-700 flex-shrink-0 h-14" style="width: 20px; z-index: 4; position: relative;"></div>
                        @endif
                    </div>

                    <div class="overflow-x-auto" style="-ms-overflow-style: none; scrollbar-width: none;">
                        <div class="inline-flex gap-2 sm:flex sm:flex-wrap pb-0.5 sm:pb-0">
                            @if ($isOrgAdmin && $competition->status === 'planning')
                                <x-filament::button size="sm" color="gray" tag="a" href="{{ route('filament.org-admin.resources.competitions.edit', $competition) }}">
                                    <span class="whitespace-nowrap">Edit competition</span>
                                </x-filament::button>
                            @endif

                            @if ($isOrgAdmin || $officialRole?->can_access_enrolments)
                                <x-filament::button size="sm" :color="$enrolmentsColor" tag="a" href="{{ route('filament.org-admin.resources.enrolments.index') }}?competition_id={{ $competition->id }}">
                                    <span class="whitespace-nowrap">Enrolments</span>
                                </x-filament::button>
                            @endif

                            @if ($isOrgAdmin || $officialRole?->can_access_checkin)
                                <x-filament::button size="sm" :color="$checkInColor" tag="a" href="{{ route('filament.org-admin.pages.check-in') }}?competition_id={{ $competition->id }}">
                                    <span class="whitespace-nowrap">Check-in</span>
                                </x-filament::button>
                            @endif

                            @if ($isOrgAdmin)
                                <x-filament::button size="sm" :color="$schedulingColor" tag="a" href="{{ route('filament.org-admin.resources.competitions.schedule', $competition) }}">
                                    <span class="whitespace-nowrap">Scheduling</span>
                                </x-filament::button>
                            @endif

                            @if ($isOrgAdmin || $officialRole?->can_access_scoring)
                                <x-filament::button size="sm" :color="$scoringColor" tag="a" href="{{ route('filament.org-admin.pages.scoring') }}?competition_id={{ $competition->id }}">
                                    <span class="whitespace-nowrap">Scoring</span>
                                </x-filament::button>
                            @endif
                        </div>
                    </div>

                    {{-- AI Insights summary (open+ competitions, org admin only) --}}
                    @if ($isOrgAdmin && in_array($competition->status, ['open', 'closed', 'check_in', 'running', 'complete']))
                        @php
                            $insight     = $this->getInsightsForCompetition($competition->id);
                            $insightsUrl = route('filament.org-admin.resources.competitions.insights', $competition);
                        @endphp
                        <div class="mt-3 pt-3 border-t border-gray-200/60 dark:border-gray-700/60">
                            @if ($insight)
                                @php
                                    preg_match('/## ✅ Action Items\s*([\s\S]*?)(?=\n## |$)/u', $insight->content, $matches);
                                    $actionSection = trim($matches[1] ?? '');
                                    $bulletLines = collect(explode("\n", $actionSection))
                                        ->filter(fn ($l) => preg_match('/^\s*[-*]/', $l))
                                        ->take(3)
                                        ->map(function ($l) {
                                            $text = trim(preg_replace('/^\s*[-*]\s*/', '', $l));
                                            return preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
                                        })
                                        ->values();
                                @endphp
                                <div class="flex flex-col gap-2">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5 flex items-center gap-1">
                                            <x-heroicon-o-sparkles class="w-3.5 h-3.5" />
                                            AI Insights
                                            <span class="font-normal opacity-70">&bull; {{ $insight->generated_at->diffForHumans() }}</span>
                                            <a href="{{ $insightsUrl }}" class="ml-auto text-xs font-normal text-primary-600 dark:text-primary-400 hover:underline whitespace-nowrap">
                                                View full insights →
                                            </a>
                                        </p>
                                        @if ($bulletLines->isNotEmpty())
                                            <ul class="space-y-1">
                                                @foreach ($bulletLines as $line)
                                                    <li class="text-xs text-gray-600 dark:text-gray-300 flex items-start gap-1.5 {{ $loop->iteration > 2 ? 'hidden sm:flex' : '' }}">
                                                        <span class="text-primary-500 mt-0.5 flex-shrink-0">•</span>
                                                        <span class="line-clamp-1 sm:line-clamp-none">{!! $line !!}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <p class="text-xs text-gray-500 dark:text-gray-400 italic">No action items noted.</p>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-xs text-gray-400 dark:text-gray-500 flex items-center gap-1">
                                        <x-heroicon-o-sparkles class="w-3.5 h-3.5" />
                                        No AI insights generated yet
                                    </p>
                                    <a href="{{ $insightsUrl }}" class="text-xs text-primary-600 dark:text-primary-400 hover:underline whitespace-nowrap">
                                        Generate insights →
                                    </a>
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Pending tasks (org admin only) --}}
                    @if ($isOrgAdmin && $competition->pending_tasks_count > 0)
                        @php
                            $pendingTasks = $competition->tasks;
                            $tasksUrl     = route('filament.org-admin.resources.competitions.tasks', $competition);
                            $overflowCount = max(0, $pendingTasks->count() - 5);
                            $visibleTasks  = $pendingTasks->take(5);
                        @endphp
                        <div class="mt-3 pt-3 border-t border-gray-200/60 dark:border-gray-700/60">
                            <div class="flex items-center justify-between gap-3 mb-1.5">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                    <x-heroicon-o-clipboard-document-check class="w-3.5 h-3.5" />
                                    Tasks
                                </p>
                                <a href="{{ $tasksUrl }}" class="text-xs text-primary-600 dark:text-primary-400 hover:underline whitespace-nowrap">
                                    Manage tasks →
                                </a>
                            </div>
                            <ul class="space-y-1">
                                @foreach ($visibleTasks as $task)
                                    <li class="flex items-start gap-2" wire:key="dash-task-{{ $task->id }}">
                                        <button
                                            wire:click="markTaskComplete({{ $task->id }})"
                                            class="mt-0.5 flex-shrink-0 w-4 h-4 rounded border-2 border-gray-300 dark:border-gray-500 hover:border-success-500 dark:hover:border-success-400 transition-colors"
                                            title="Mark complete"
                                        ></button>
                                        <span class="text-xs text-gray-600 dark:text-gray-300">{{ $task->title }}</span>
                                    </li>
                                @endforeach
                                @if ($overflowCount > 0)
                                    <li class="pl-6">
                                        <a href="{{ $tasksUrl }}" class="text-xs text-gray-400 dark:text-gray-500 hover:underline">
                                            + {{ $overflowCount }} more
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </div>
                    @endif

                </x-filament::section>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
