<x-filament-panels::page>
    @php
        $competitions  = $this->getActiveCompetitions();
        $isOrgAdmin    = $this->isOrgAdmin();
        $officialRole  = $this->getOfficialRole();
    @endphp

    @if ($competitions->isEmpty())
        <x-filament::section>
            <p class="text-center text-gray-500 py-8">No active competitions.@if($isOrgAdmin) <a href="{{ route('filament.org-admin.resources.competitions.create') }}" class="text-primary-600 underline">Create one</a>.@endif</p>
        </x-filament::section>
    @else
        <div class="grid gap-4">
            @foreach ($competitions as $competition)
                @php
                    $statusLabel = match ($competition->status) {
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
                    $enrolmentsColor = in_array($competition->status, ['open', 'closed']) ? 'success' : 'gray';
                    $checkInColor    = match ($competition->status) {
                        'check_in' => 'primary',
                        'running'  => 'warning',
                        default    => 'gray',
                    };
                    $schedulingColor = in_array($competition->status, ['closed', 'check_in']) ? 'info' : 'gray';
                    $scoringColor    = $competition->status === 'running' ? 'warning' : 'gray';
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
                        $allStatuses  = ['draft', 'open', 'closed', 'check_in', 'running', 'complete'];
                        $stepLabels   = ['draft' => 'Draft', 'open' => 'Open', 'closed' => 'Closed', 'check_in' => 'Check-in', 'running' => 'Running', 'complete' => 'Complete'];
                        $currentIdx   = array_search($competition->status, $allStatuses);
                    @endphp
                    <div class="flex items-start w-full mb-4 overflow-x-auto">
                        @foreach ($allStatuses as $i => $step)
                            @php
                                $isPast      = $i < $currentIdx;
                                $isCurrent   = $i === $currentIdx;
                                $label       = $stepLabels[$step];
                                $isClickable = $isOrgAdmin && ! $isCurrent;
                            @endphp
                            <div class="flex flex-col items-center flex-1 min-w-0">
                                @if ($isClickable)
                                    <button
                                        type="button"
                                        class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold transition-colors {{ $isPast ? 'bg-primary-500 text-white hover:bg-primary-600' : 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300 hover:bg-primary-200 dark:hover:bg-primary-800' }}"
                                        x-on:click="$wire.mountAction('setStatus', { competitionId: {{ $competition->id }}, targetStatus: '{{ $step }}' })"
                                        title="Set to {{ $label }}"
                                    >
                                        @if ($isPast)
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                        @else
                                            {{ $i + 1 }}
                                        @endif
                                    </button>
                                @else
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold {{ $isCurrent ? 'bg-primary-500 ring-4 ring-primary-200 dark:ring-primary-800 text-white' : ($isPast ? 'bg-primary-500 text-white' : 'bg-gray-200 text-gray-400 dark:bg-gray-700 dark:text-gray-500') }}">
                                        @if ($isPast)
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                        @else
                                            {{ $i + 1 }}
                                        @endif
                                    </div>
                                @endif
                                <span class="mt-1 text-xs text-center leading-tight font-medium whitespace-nowrap {{ $isCurrent ? 'text-primary-600 dark:text-primary-400' : ($isPast ? 'text-primary-500 dark:text-primary-500' : 'text-gray-400 dark:text-gray-500') }}">
                                    {{ $label }}
                                </span>
                            </div>
                            @unless ($loop->last)
                                <div class="flex-shrink-0 w-6 h-0.5 mt-4 self-start {{ $isPast ? 'bg-primary-400' : 'bg-gray-200 dark:bg-gray-700' }}"></div>
                            @endunless
                        @endforeach
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @if ($isOrgAdmin && $competition->status === 'draft')
                            <x-filament::button
                                size="sm"
                                color="gray"
                                tag="a"
                                href="{{ route('filament.org-admin.resources.competitions.edit', $competition) }}"
                            >
                                Edit competition
                            </x-filament::button>
                        @endif

                        @if ($isOrgAdmin || $officialRole?->can_access_enrolments)
                            <x-filament::button
                                size="sm"
                                :color="$enrolmentsColor"
                                tag="a"
                                href="{{ route('filament.org-admin.resources.enrolments.index') }}?competition_id={{ $competition->id }}"
                            >
                                Enrolments
                            </x-filament::button>
                        @endif

                        @if ($isOrgAdmin || $officialRole?->can_access_checkin)
                            <x-filament::button
                                size="sm"
                                :color="$checkInColor"
                                tag="a"
                                href="{{ route('filament.org-admin.pages.check-in') }}?competition_id={{ $competition->id }}"
                            >
                                Check-in
                            </x-filament::button>
                        @endif

                        @if ($isOrgAdmin)
                            <x-filament::button
                                size="sm"
                                :color="$schedulingColor"
                                tag="a"
                                href="{{ route('filament.org-admin.resources.competitions.schedule', $competition) }}"
                            >
                                Scheduling
                            </x-filament::button>
                        @endif

                        @if ($isOrgAdmin || $officialRole?->can_access_scoring)
                            <x-filament::button
                                size="sm"
                                :color="$scoringColor"
                                tag="a"
                                href="{{ route('filament.org-admin.pages.scoring') }}?competition_id={{ $competition->id }}"
                            >
                                Scoring
                            </x-filament::button>
                        @endif
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
