<x-filament-panels::page>
    @php $competitions = $this->getActiveCompetitions(); @endphp

    @if ($competitions->isEmpty())
        <x-filament::section>
            <p class="text-center text-gray-500 py-8">No active competitions. <a href="{{ route('filament.admin.resources.competitions.create') }}" class="text-primary-600 underline">Create one</a>.</p>
        </x-filament::section>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
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
                    $nextLabel = match ($competition->status) {
                        'draft'    => 'Open Enrolments',
                        'open'     => 'Close Enrolments',
                        'closed'   => 'Begin Check-ins',
                        'check_in' => 'Start Competition',
                        'running'  => 'Conclude Competition',
                        default    => null,
                    };
                    $nextColor = match ($competition->status) {
                        'running'  => 'info',
                        'check_in' => 'warning',
                        'open'     => 'success',
                        default    => 'gray',
                    };
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
                        &bull; {{ $competition->enrolments_count }} enrolment{{ $competition->enrolments_count !== 1 ? 's' : '' }}
                    </x-slot>

                    <div class="flex flex-wrap gap-2">
                        @if ($nextLabel)
                            <x-filament::button
                                size="sm"
                                :color="$nextColor"
                                x-on:click="$wire.mountAction('advanceStatus', {competitionId: {{ $competition->id }}})"
                            >
                                {{ $nextLabel }}
                            </x-filament::button>
                        @endif

                        <x-filament::button
                            size="sm"
                            color="gray"
                            tag="a"
                            href="{{ route('filament.admin.resources.competitions.edit', $competition) }}"
                        >
                            Edit competition
                        </x-filament::button>

                        <x-filament::button
                            size="sm"
                            color="gray"
                            tag="a"
                            href="{{ route('filament.admin.resources.enrolments.index') }}?competition_id={{ $competition->id }}"
                        >
                            Enrolments
                        </x-filament::button>

                        <x-filament::button
                            size="sm"
                            color="primary"
                            tag="a"
                            href="{{ route('filament.admin.pages.check-in') }}?competition_id={{ $competition->id }}"
                        >
                            Check-in
                        </x-filament::button>

                        <x-filament::button
                            size="sm"
                            color="info"
                            tag="a"
                            href="{{ route('filament.admin.resources.competitions.schedule', $competition) }}"
                        >
                            Scheduling
                        </x-filament::button>

                        <x-filament::button
                            size="sm"
                            color="warning"
                            tag="a"
                            href="{{ route('filament.admin.pages.scoring') }}?competition_id={{ $competition->id }}"
                        >
                            Scoring
                        </x-filament::button>
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
