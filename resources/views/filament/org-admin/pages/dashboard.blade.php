<x-filament-panels::page>
    @script
    <script>
        document.addEventListener('livewire:navigated', () => $wire.$refresh());
    </script>
    @endscript

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
        @keyframes icon-shimmer {
            0%, 100% { filter: brightness(1) drop-shadow(0 0 0px currentColor); }
            50%       { filter: brightness(1.6) drop-shadow(0 0 3px currentColor); }
        }
        .icon-shimmer { animation: icon-shimmer 2.5s ease-in-out infinite; }
    </style>

    @if ($competitions->isEmpty())
        <x-filament::section>
            <p class="text-center text-gray-500 py-8">No active competitions.@if($isOrgAdmin) <a href="{{ route('filament.org-admin.resources.competitions.create') }}" class="text-primary-600 underline">Create one</a>.@endif</p>
        </x-filament::section>
    @else
        <div class="grid gap-4">
            @foreach ($competitions as $competition)
                @php
                    $qrUrl        = config('app.scheme') . '://' . app('tenant')->slug . '.' . config('app.domain') . '/schedule/' . $competition->id;
                    $isQrAvailable = $competition->isPublicScheduleAvailable();
                    $statusLabel = match ($competition->status) {
                        'planning'          => 'Planning',
                        'advertise'         => 'Advertise',
                        'open'              => 'Open',
                        'enrolments_closed' => 'Registrations Closed',
                        'check_in'          => 'Check-in',
                        'running'           => 'Running',
                        'complete'          => 'Complete',
                        default             => ucfirst($competition->status),
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
                    $schedulingColor = $competition->status === 'enrolments_closed' ? 'primary' : 'gray';
                    $scoringColor    = $competition->status === 'running'  ? 'warning'  : 'gray';

                    // Countdown chip
                    $daysUntil       = (int) now()->startOfDay()->diffInDays($competition->competition_date->copy()->startOfDay(), false);
                    $countdownLabel  = match(true) {
                        $daysUntil === 0  => 'Today',
                        $daysUntil === 1  => 'Tomorrow',
                        $daysUntil > 1    => $daysUntil . ' days',
                        $daysUntil === -1 => 'Yesterday',
                        default           => abs($daysUntil) . ' days ago',
                    };
                    $countdownClass  = match(true) {
                        $daysUntil <= 0  => 'bg-gray-100/60 text-gray-400 border-gray-200/60 dark:bg-gray-800/40 dark:text-gray-500 dark:border-gray-600/40',
                        $daysUntil <= 3  => 'bg-red-100/70 text-red-700 border-red-200/70 dark:bg-red-900/30 dark:text-red-300 dark:border-red-700/40',
                        $daysUntil <= 7  => 'bg-amber-100/70 text-amber-700 border-amber-200/70 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-700/40',
                        default          => 'bg-gray-100/60 text-gray-400 border-gray-200/60 dark:bg-gray-800/40 dark:text-gray-500 dark:border-gray-600/40',
                    };

                    // Which button to spotlight as the primary next action
                    $spotlightSection = match($competition->status) {
                        'open'              => 'enrolments',
                        'enrolments_closed' => 'scheduling',
                        'check_in'          => 'checkin',
                        'running'           => 'scoring',
                        default             => null,
                    };
                @endphp
                <div x-data="{
                    qrOpen: false,
                    copied: false,
                    async copyQr() {
                        const svg = this.$refs.qrcode.querySelector('svg');
                        const svgData = new XMLSerializer().serializeToString(svg);
                        const blob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
                        const url = URL.createObjectURL(blob);
                        const img = new Image();
                        await new Promise(resolve => { img.onload = resolve; img.src = url; });
                        const canvas = document.createElement('canvas');
                        canvas.width = img.naturalWidth;
                        canvas.height = img.naturalHeight;
                        canvas.getContext('2d').drawImage(img, 0, 0);
                        URL.revokeObjectURL(url);
                        canvas.toBlob(async png => {
                            await navigator.clipboard.write([new ClipboardItem({ 'image/png': png })]);
                            this.copied = true;
                            setTimeout(() => this.copied = false, 2000);
                        }, 'image/png');
                    }
                }">
                @php
                    $cardAccent = match ($competition->status) {
                        'open'              => 'accent-green',
                        'enrolments_closed' => 'accent-amber',
                        'check_in'          => 'accent-amber',
                        'running'           => 'accent-blue',
                        'complete'          => 'accent-gray',
                        default             => 'accent-violet',
                    };
                    $cardGlow = match ($competition->status) {
                        'open'              => 'shadow-[0_0_20px_-5px_rgba(74,222,128,0.45)]',
                        'planning'          => 'shadow-[0_0_20px_-5px_rgba(167,139,250,0.45)]',
                        'advertise'         => 'shadow-[0_0_20px_-5px_rgba(167,139,250,0.45)]',
                        'enrolments_closed' => 'shadow-[0_0_20px_-5px_rgba(251,191,36,0.45)]',
                        'check_in'          => 'shadow-[0_0_20px_-5px_rgba(251,191,36,0.45)]',
                        'running'           => 'shadow-[0_0_20px_-5px_rgba(96,165,250,0.45)]',
                        default             => '',
                    };
                @endphp
                <div class="rounded-xl {{ $cardAccent }} {{ $cardGlow }}">
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center gap-3">
                            <div class="flex-shrink-0 self-start flex flex-col items-center justify-center w-12 h-12 rounded-lg bg-primary-500 dark:bg-primary-600 text-white text-center leading-none select-none shadow-sm">
                                <span class="text-[0.65rem] font-bold uppercase tracking-wide opacity-90">{{ $competition->competition_date->format('M') }}</span>
                                <span class="text-xl font-bold leading-none mt-0.5">{{ $competition->competition_date->format('j') }}</span>
                            </div>
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span>{{ $competition->name }}</span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border {{ $statusBadgeClass }}">
                                        {{ $statusLabel }}
                                    </span>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium border {{ $countdownClass }}">
                                        @if ($daysUntil > 0 && $daysUntil <= 7)
                                            <svg class="w-3 h-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        @endif
                                        {{ $countdownLabel }}
                                    </span>
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400 mt-0.5 font-normal">
                                    {{ tenant_date($competition->competition_date) }}
                                    @if ($competition->location_name) &mdash; {{ $competition->location_name }} @endif
                                </div>
                            </div>
                        </div>
                    </x-slot>
                    @if ($isQrAvailable)
                        <x-slot name="headerActions">
                            <button
                                type="button"
                                x-on:click="qrOpen = true"
                                title="Public schedule & results"
                                class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:text-gray-300 dark:hover:bg-gray-700 transition"
                            >
                                <x-heroicon-o-qr-code class="w-5 h-5" />
                            </button>
                        </x-slot>
                    @endif

                    @php
                        $allStatuses = ['planning', 'advertise', 'open', 'enrolments_closed', 'check_in', 'running', 'complete'];
                        $stepLine1   = ['planning' => 'Planning', 'advertise' => 'Advertise', 'open' => 'Open for', 'enrolments_closed' => 'Registrations', 'check_in' => 'Check-in', 'running' => 'Running', 'complete' => 'Complete'];
                        $stepLine2   = ['planning' => '',         'advertise' => '',          'open' => 'Registrations', 'enrolments_closed' => 'Closed', 'check_in' => '',          'running' => '',        'complete' => ''];
                        $stepTitle   = ['planning' => 'Planning', 'advertise' => 'Advertise', 'open' => 'Open for Registrations', 'enrolments_closed' => 'Registrations Closed', 'check_in' => 'Check-in', 'running' => 'Running', 'complete' => 'Complete'];
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
                                $line1       = $step === 'enrolments_closed' ? 'Reg.' : $stepLine1[$step];
                                $line2       = $step === 'open' ? 'Reg.' : $stepLine2[$step];
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

                    {{-- Progress indicator --}}
                    @php
                        $showProgressBar = false;
                        $progressPct     = null;
                        $progressText    = '';
                        $progressExtra   = '';
                        $progressAbsent  = null;

                        if (in_array($competition->status, ['planning', 'advertise']) && $competition->schedulable_divisions_count > 0) {
                            $showProgressBar = true;
                            $progressPct     = (int) round(($competition->scheduled_divisions_count / $competition->schedulable_divisions_count) * 100);
                            $progressText    = $competition->scheduled_divisions_count . ' / ' . $competition->schedulable_divisions_count . ' divisions scheduled';
                        } elseif (in_array($competition->status, ['open', 'enrolments_closed'])) {
                            $showProgressBar = true;
                            $enrolled        = $competition->enrolments_count;
                            $target          = $competition->target_competitors;
                            if ($target) {
                                $progressPct  = (int) round(($enrolled / $target) * 100);
                                $progressText = $enrolled . ' / ' . $target . ' registered';
                            } else {
                                $progressText = $enrolled . ' registered';
                            }
                            if ($competition->status === 'open' && $competition->enrolment_due_date) {
                                if ($competition->enrolment_due_date->isFuture()) {
                                    $days          = (int) now()->diffInDays($competition->enrolment_due_date);
                                    $progressExtra = '· ' . $days . ' ' . ($days === 1 ? 'day' : 'days') . ' to close';
                                } else {
                                    $progressExtra = '· registration closed';
                                }
                            }
                        } elseif ($competition->status === 'check_in') {
                            $showProgressBar = true;
                            $checkedIn       = $competition->checkins_count;
                            $enrolled        = $competition->enrolments_count;
                            $progressPct     = $enrolled > 0 ? (int) round(($checkedIn / $enrolled) * 100) : null;
                            $progressText    = $checkedIn . ' / ' . $enrolled . ' checked in';
                            $absent          = $enrolled - $checkedIn;
                            if ($absent > 0) {
                                $progressAbsent = $absent . ' absent';
                            }
                        } elseif ($competition->status === 'running') {
                            $showProgressBar = true;
                            $completed       = $competition->scheduled_completed_divisions_count;
                            $total           = $competition->scheduled_divisions_count;
                            $progressPct     = $total > 0 ? (int) round(($completed / $total) * 100) : null;
                            $progressText    = $completed . ' / ' . $total . ' divisions complete';
                        }

                        $barColor = match(true) {
                            $progressPct === null => null,
                            $progressPct >= 75    => '#22c55e',
                            $progressPct >= 40    => '#fbbf24',
                            default               => '#f87171',
                        };
                    @endphp
                    @if ($showProgressBar)
                        <div class="mb-3 flex items-center gap-3" wire:key="{{ $competition->id }}-progress">
                            @if ($progressPct !== null)
                                <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 overflow-hidden min-w-0">
                                    <div
                                        x-data="{ pct: 0 }"
                                        x-init="requestAnimationFrame(() => setTimeout(() => pct = {{ min($progressPct, 100) }}, 80))"
                                        class="h-full rounded-full transition-[width] duration-700 ease-out"
                                        :style="`width: ${pct}%; background-color: {{ $barColor }};`"
                                    ></div>
                                </div>
                            @endif
                            <p class="text-xs text-gray-500 dark:text-gray-400 flex-shrink-0 {{ $progressPct === null ? 'w-full' : '' }}">
                                {{ $progressText }}
                                @if ($progressExtra)
                                    <span class="opacity-60 ml-1">{{ $progressExtra }}</span>
                                @endif
                                @if ($progressAbsent)
                                    <span class="text-red-400 dark:text-red-400 ml-1 opacity-80">· {{ $progressAbsent }}</span>
                                @endif
                            </p>
                        </div>
                    @endif

                    @php
                        $hideOnMobile = match ($competition->status) {
                            'planning'  => ['scheduling', 'scoring'],
                            'advertise' => ['scheduling', 'scoring'],
                            'open'      => ['scoring'],
                            'enrolments_closed' => ['scoring'],
                            'check_in'  => ['scheduling'],
                            'running'   => ['scheduling'],
                            'complete'  => ['checkin'],
                            default     => ['scoring'],
                        };
                    @endphp
                    <div class="flex flex-wrap gap-2">
                        @if ($isOrgAdmin && in_array($competition->status, ['planning', 'advertise']))
                            <x-filament::button size="sm" color="gray" tag="a" href="{{ route('filament.org-admin.resources.competitions.edit', $competition) }}">
                                Edit competition
                            </x-filament::button>
                        @endif

                        @if (($isOrgAdmin || $officialRole?->can_access_enrolments) && $competition->status !== 'complete')
                            <div class="{{ in_array('enrolments', $hideOnMobile) ? 'hidden sm:block' : '' }} {{ $spotlightSection === 'enrolments' ? 'btn-spotlight' : '' }}">
                                <x-filament::button size="sm" :color="$enrolmentsColor" tag="a" href="{{ route('filament.org-admin.resources.enrolments.index') }}?competition_id={{ $competition->id }}">
                                    Registrations
                                </x-filament::button>
                            </div>
                        @endif

                        @if (($isOrgAdmin || $officialRole?->can_access_checkin) && in_array($competition->status, ['check_in', 'running']))
                            <div class="{{ in_array('checkin', $hideOnMobile) ? 'hidden sm:block' : '' }} {{ $spotlightSection === 'checkin' ? 'btn-spotlight' : '' }}">
                                <x-filament::button size="sm" :color="$checkInColor" tag="a" href="{{ route('filament.org-admin.pages.check-in') }}?competition_id={{ $competition->id }}">
                                    Check-in
                                </x-filament::button>
                            </div>
                        @endif

                        @if ($isOrgAdmin && $competition->status !== 'complete')
                            <div class="{{ in_array('scheduling', $hideOnMobile) ? 'hidden sm:block' : '' }} {{ $spotlightSection === 'scheduling' ? 'btn-spotlight' : '' }}">
                                <x-filament::button size="sm" :color="$schedulingColor" tag="a" href="{{ route('filament.org-admin.resources.competitions.schedule', $competition) }}">
                                    Scheduling
                                </x-filament::button>
                            </div>
                        @endif

                        @if ($isOrgAdmin && $competition->status === 'complete')
                            <x-filament::button size="sm" color="gray" tag="a" href="{{ route('filament.org-admin.pages.results') }}?competition_id={{ $competition->id }}">
                                Results
                            </x-filament::button>
                            <x-filament::button size="sm" color="gray" tag="a" href="{{ route('filament.org-admin.pages.transactions-page') }}">
                                Transactions
                            </x-filament::button>
                        @endif

                        @if (($isOrgAdmin || $officialRole?->can_access_scoring) && $competition->status === 'running')
                            <div class="{{ in_array('scoring', $hideOnMobile) ? 'hidden sm:block' : '' }} {{ $spotlightSection === 'scoring' ? 'btn-spotlight' : '' }}">
                                <x-filament::button size="sm" :color="$scoringColor" tag="a" href="{{ route('filament.org-admin.pages.scoring') }}?competition_id={{ $competition->id }}">
                                    Scoring
                                </x-filament::button>
                            </div>
                        @endif
                    </div>

                    {{-- Outstanding platform fee warning (org admin only) --}}
                    @if ($isOrgAdmin && $competition->competition_date->isPast() && ($outstandingFee = $competition->unpaidPlatformFeeTotal()) > 0)
                        <div class="mt-3 rounded-lg bg-danger-50 dark:bg-danger-950 border border-danger-200 dark:border-danger-800 px-3 py-2 flex items-center justify-between gap-3">
                            <p class="text-xs text-danger-700 dark:text-danger-400 flex items-center gap-1.5">
                                <x-heroicon-o-exclamation-triangle class="w-4 h-4 flex-shrink-0" />
                                Platform fees {{ app('tenant')?->currency ?: 'AUD' }} {{ number_format($outstandingFee, 2) }} outstanding
                            </p>
                            <a href="{{ route('filament.org-admin.pages.platform-fees-page') }}" class="text-xs font-medium text-danger-700 dark:text-danger-400 hover:underline whitespace-nowrap">
                                View →
                            </a>
                        </div>
                    @endif

                    {{-- AI Insights summary (org admin only) --}}
                    @if ($isOrgAdmin)
                        @php
                            $insight     = $this->getInsightsForCompetition($competition->id);
                            $insightsUrl = route('filament.org-admin.resources.competitions.insights', $competition);
                        @endphp
                        <div class="mt-3 pt-3 border-t border-gray-200/60 dark:border-gray-700/60">
                            @if ($insight)
                                @php
                                    $isComplete = $competition->status === 'complete';
                                    $sectionPattern = $isComplete
                                        ? '/## 🔍 Recommendations for Next Competition\s*([\s\S]*?)(?=\n## |$)/u'
                                        : '/## ✅ Action Items\s*([\s\S]*?)(?=\n## |$)/u';
                                    preg_match($sectionPattern, $insight->content, $matches);
                                    $actionSection = trim($matches[1] ?? '');
                                    $bulletLines = collect(explode("\n", $actionSection))
                                        ->filter(fn ($l) => preg_match('/^[-*]/', $l))
                                        ->take(3)
                                        ->map(function ($l) {
                                            preg_match_all('/\*\*(.+?)\*\*/', $l, $bolds);
                                            if (! empty($bolds[1])) {
                                                return implode(', ', array_map(fn ($s) => rtrim($s, ':'), $bolds[1]));
                                            }
                                            return trim(preg_replace('/^[-*]\s*/', '', $l));
                                        })
                                        ->filter()
                                        ->values();
                                @endphp
                                <div class="flex flex-col gap-2">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5 flex items-center gap-1">
                                            <x-heroicon-o-sparkles class="w-3.5 h-3.5 icon-shimmer" />
                                            AI Insights
                                            <span class="font-normal opacity-70">&bull; {{ $insight->generated_at->diffForHumans() }}</span>
                                            <a href="{{ $insightsUrl }}" class="ml-auto text-xs font-normal text-primary-600 dark:text-primary-400 hover:underline whitespace-nowrap">
                                                View full insights →
                                            </a>
                                        </p>
                                        @if ($bulletLines->isNotEmpty())
                                            <ul class="space-y-1">
                                                @foreach ($bulletLines as $line)
                                                    <li class="text-xs text-gray-600 dark:text-gray-300 flex items-start gap-1.5">
                                                        <span class="text-primary-500 mt-0.5 flex-shrink-0">•</span>
                                                        <span class="line-clamp-1 sm:line-clamp-none">{{ $line }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <p class="text-xs text-gray-500 dark:text-gray-400 italic">{{ $isComplete ? 'No recommendations noted.' : 'No action items noted.' }}</p>
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
                                    <x-heroicon-o-clipboard-document-check class="w-3.5 h-3.5 icon-shimmer" />
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
                </div>{{-- /card accent wrapper --}}

                {{-- QR modal --}}
                @if ($isQrAvailable)
                    <div
                        x-show="qrOpen"
                        x-on:click.self="qrOpen = false"
                        x-on:keydown.escape.window="qrOpen = false"
                        x-transition
                        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                        style="display: none;"
                    >
                        <div class="bg-white rounded-xl shadow-xl p-6 max-w-sm w-full space-y-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-gray-900">Public Schedule &amp; Results</h3>
                                <button type="button" x-on:click="qrOpen = false" class="text-gray-400 hover:text-gray-600 -mr-1 p-1">
                                    <x-heroicon-o-x-mark class="w-5 h-5" />
                                </button>
                            </div>
                            <div x-ref="qrcode" class="flex justify-center">
                                <x-qr-code :value="$qrUrl" :size="220" />
                            </div>
                            <div class="rounded-lg bg-gray-100 border border-gray-200 px-3 py-2 text-center">
                                <a href="{{ $qrUrl }}" target="_blank" style="color: #2563eb; font-size: 0.875rem; word-break: break-all;" class="hover:underline">
                                    {{ $qrUrl }}
                                </a>
                            </div>
                            <div class="flex justify-center">
                                <button
                                    type="button"
                                    x-on:click="copyQr()"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 transition"
                                >
                                    <svg x-show="!copied" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-4 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    <svg x-show="copied" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span x-text="copied ? 'Copied!' : 'Copy QR code'"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                @endif
                </div>{{-- /x-data QR wrapper --}}
            @endforeach
        </div>
    @endif

    <div wire:poll.10s class="hidden"></div>
</x-filament-panels::page>
