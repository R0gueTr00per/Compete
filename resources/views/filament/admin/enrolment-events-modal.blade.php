<div class="space-y-4">

    {{-- Late registration banner --}}
    @if ($enrolment->is_late)
        <div class="flex items-center gap-2 rounded-lg bg-warning-50 dark:bg-warning-900/20 border border-warning-200 dark:border-warning-700 px-3 py-2">
            <x-heroicon-o-clock class="h-4 w-4 text-warning-600 dark:text-warning-400 flex-shrink-0" />
            <span class="text-xs font-medium text-warning-700 dark:text-warning-300">Late registration</span>
        </div>
    @endif

    {{-- Active events --}}
    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-2">Active Events</p>
        <div class="flex flex-wrap gap-1.5">
            @forelse ($enrolment->activeEvents as $ee)
                <div class="inline-flex items-stretch rounded-md border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-900 text-xs shadow-sm overflow-hidden">
                    @if ($ee->division)
                        <div class="flex items-center justify-center px-2.5 bg-gray-100 dark:bg-gray-700 border-r border-gray-200 dark:border-gray-600 shrink-0">
                            <span class="font-mono font-bold text-gray-600 dark:text-gray-300">{{ $ee->division->code }}</span>
                        </div>
                    @endif
                    <div class="flex flex-col px-2.5 py-1.5">
                        <span class="font-medium text-gray-700 dark:text-gray-300 leading-snug">
                            {{ $ee->competitionEvent->name }}@if ($ee->competitionEvent->location_label) <span class="font-normal text-gray-400">({{ $ee->competitionEvent->location_label }})</span>@endif
                        </span>
                        @if ($ee->division)
                            <span class="text-[0.65rem] text-gray-400 dark:text-gray-500 mt-0.5 leading-snug">{{ $ee->division->full_label }}</span>
                        @else
                            <span class="text-[0.65rem] italic text-gray-400 dark:text-gray-500 mt-0.5">Division not yet assigned</span>
                        @endif
                        @if ($ee->previous_division_id && $ee->previousDivision)
                            <span class="text-[0.65rem] text-info-600 dark:text-info-400 mt-0.5">Changed from: {{ $ee->previousDivision->label }}</span>
                        @endif
                        @if ($ee->competitionEvent->requires_partner)
                            <span class="text-[0.65rem] mt-0.5 {{ $ee->yakusuko_complete ? 'text-success-600' : 'text-warning-600' }}">Partner: {{ $ee->yakusuko_complete ? 'Confirmed' : 'Pending' }}</span>
                        @endif
                    </div>
                </div>
            @empty
                <p class="py-4 text-sm text-gray-500">No active events.</p>
            @endforelse
        </div>
    </div>

    {{-- Removed events --}}
    @php
        $removedEvents = $enrolment->enrolmentEvents->filter(fn ($ee) => $ee->removed);
    @endphp
    @if ($removedEvents->isNotEmpty())
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-2">Removed Events</p>
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($removedEvents as $ee)
                    <div class="py-3 opacity-60">
                        <div class="flex items-center gap-2">
                            <p class="font-medium text-sm text-gray-500 line-through">
                                {{ $ee->competitionEvent?->name ?? 'Unknown event' }}
                            </p>
                            <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium
                                {{ $ee->removal_type === 'user_withdrawn'
                                    ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400'
                                    : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' }}">
                                {{ $ee->removal_type === 'user_withdrawn' ? 'Withdrawn' : 'Cancelled' }}
                            </span>
                        </div>
                        @if ($ee->division)
                            <p class="text-xs text-gray-400 mt-0.5 line-through">{{ $ee->division->label }}</p>
                        @endif
                        @if ($ee->removal_reason)
                            <p class="text-xs text-gray-400 mt-0.5">{{ $ee->removal_reason }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Registration questions --}}
    @php
        $fields = $enrolment->competition?->registration_fields ?? [];
        $responses = $enrolment->custom_field_responses ?? [];
    @endphp
    @if (!empty($fields) && !empty($responses))
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-2">Registration Questions</p>
            @foreach ($fields as $field)
                @php $value = $responses[$field['id']] ?? null; @endphp
                <div class="py-1.5">
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $field['label'] }}</span>
                    <p class="text-sm text-gray-900 dark:text-gray-100 mt-0.5">
                        @if ($value === true || $value === 'true' || $value === 1)
                            Yes
                        @elseif ($value === false || $value === 'false' || $value === 0)
                            No
                        @elseif ($value !== null && $value !== '')
                            {{ $value }}
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </p>
                </div>
            @endforeach
        </div>
    @endif
</div>
