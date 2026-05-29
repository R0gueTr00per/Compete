<div class="divide-y divide-gray-100 dark:divide-gray-800">
    @forelse ($enrolment->activeEvents as $ee)
        <div class="py-3">
            <p class="font-medium text-sm text-gray-900 dark:text-gray-100">
                {{ $ee->competitionEvent->name }}
                @if ($ee->competitionEvent->location_label)
                    <span class="text-gray-400 font-normal">({{ $ee->competitionEvent->location_label }})</span>
                @endif
            </p>
            @if ($ee->division)
                <p class="text-xs text-gray-500 mt-0.5">Division: {{ $ee->division->full_label }}</p>
            @else
                <p class="text-xs text-gray-400 mt-0.5">Division: not yet assigned</p>
            @endif
            @if ($ee->competitionEvent->requires_partner)
                <p class="text-xs mt-0.5 {{ $ee->yakusuko_complete ? 'text-success-600' : 'text-warning-600' }}">
                    Partner: {{ $ee->yakusuko_complete ? 'Confirmed' : 'Pending' }}
                </p>
            @endif
        </div>
    @empty
        <p class="py-4 text-sm text-gray-500">No active events.</p>
    @endforelse

    @php
        $fields = $enrolment->competition?->registration_fields ?? [];
        $responses = $enrolment->custom_field_responses ?? [];
    @endphp

    @if (!empty($fields) && !empty($responses))
        <div class="pt-4">
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
