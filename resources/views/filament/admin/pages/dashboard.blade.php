<x-filament-panels::page>
    @php $competitions = $this->getActiveCompetitions(); @endphp

    @if ($competitions->isEmpty())
        <x-filament::section>
            <p class="text-center text-gray-500 py-8">No active competitions. <a href="{{ route('filament.admin.resources.competitions.create') }}" class="text-primary-600 underline">Create one</a>.</p>
        </x-filament::section>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($competitions as $competition)
                <x-filament::section>
                    <x-slot name="heading">{{ $competition->name }}</x-slot>
                    <x-slot name="description">
                        {{ $competition->competition_date->format('d M Y') }}
                        @if ($competition->location_name)
                            &mdash; {{ $competition->location_name }}
                        @endif
                    </x-slot>

                    <div class="space-y-1 text-sm text-gray-600 dark:text-gray-400 mb-4">
                        <p>{{ $competition->enrolments_count }} enrolment{{ $competition->enrolments_count !== 1 ? 's' : '' }}</p>
                        <p>
                            @php
                            $badgeClass = match ($competition->status) {
                                'running'  => 'bg-info-100 text-info-800 dark:bg-info-900 dark:text-info-200',
                                'check_in' => 'bg-warning-100 text-warning-800 dark:bg-warning-900 dark:text-warning-200',
                                'open'     => 'bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200',
                                default    => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                            };
                            $statusLabel = match ($competition->status) {
                                'check_in' => 'Check-in',
                                default    => ucfirst($competition->status),
                            };
                        @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $badgeClass }}">
                                {{ $statusLabel }}
                            </span>
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
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

                        @php
                            $nextLabel = match ($competition->status) {
                                'draft'    => 'Open Enrolments',
                                'open'     => 'Close Enrolments',
                                'closed'   => 'Begin Check-ins',
                                'check_in' => 'Start Competition',
                                'running'  => 'Conclude Competition',
                                default    => null,
                            };
                        @endphp
                        @if ($nextLabel)
                            <x-filament::button
                                size="sm"
                                color="primary"
                                x-on:click="$wire.mountAction('advanceStatus', {competitionId: {{ $competition->id }}})"
                            >
                                {{ $nextLabel }}
                            </x-filament::button>
                        @endif
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
