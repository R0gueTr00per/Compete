@php
    $profile       = $enrolment->competitor?->competitorProfile;
    $fullName      = $profile ? "{$profile->surname}, {$profile->first_name}" : $enrolment->competitor?->name;
    $checkedIn     = $enrolment->checked_in;
    $needsWeight   = $enrolment->activeEvents->contains(fn ($ee) => $ee->competitionEvent->eventType->requires_weight_check);
    $weightDone    = $enrolment->activeEvents
        ->filter(fn ($ee) => $ee->competitionEvent->eventType->requires_weight_check)
        ->every(fn ($ee) => $ee->weight_confirmed_kg);
    $dojoLabel     = match ($enrolment->dojo_type) {
        'guest' => 'Guest — ' . ($enrolment->guest_style ?? 'Guest'),
        'lfp'   => $enrolment->dojo_name ?? 'LFP',
        default => null,
    };
@endphp

<div class="rounded-xl border {{ $checkedIn ? 'border-success-200 dark:border-success-800' : 'border-gray-200 dark:border-gray-700' }} bg-white dark:bg-gray-900 shadow-sm p-4">

    {{-- Header row: name + check-in button --}}
    <div class="flex items-center justify-between gap-3 mb-3">
        <div>
            <p class="font-semibold text-gray-900 dark:text-white text-base">{{ $fullName }}</p>
            <div class="flex flex-wrap gap-x-3 gap-y-0.5 mt-0.5">
                @if ($profile?->date_of_birth)
                    <span class="text-xs text-gray-500">Age: <strong>{{ $profile->age }}</strong></span>
                @endif
                @if ($enrolment->weight_kg)
                    <span class="text-xs text-gray-500">Enrolled weight: <strong>{{ $enrolment->weight_kg }} kg</strong></span>
                @endif
                @if ($dojoLabel)
                    <span class="text-xs text-gray-400">{{ $dojoLabel }}</span>
                @endif
                @if ($enrolment->rank_type)
                    <span class="text-xs text-gray-400">{{ $enrolment->display_rank }}</span>
                @endif
            </div>
        </div>

        <div class="shrink-0">
            @if ($checkedIn)
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1 text-xs font-medium text-success-700 dark:text-success-400">
                        <x-heroicon-s-check-circle class="w-4 h-4" />
                        {{ $enrolment->checked_in_at?->format('H:i') }}
                    </span>
                    <x-filament::button size="xs" color="gray" wire:click="undoCheckIn({{ $enrolment->id }})">
                        Undo
                    </x-filament::button>
                </div>
            @else
                <x-filament::button size="sm" color="success" wire:click="checkIn({{ $enrolment->id }})">
                    Check in
                </x-filament::button>
            @endif
        </div>
    </div>

    {{-- Single weight input for the whole enrolment --}}
    @if ($needsWeight)
        <div class="mb-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
            @if ($weightDone)
                @php $confirmedKg = $enrolment->activeEvents->firstWhere(fn($ee) => $ee->weight_confirmed_kg)?->weight_confirmed_kg; @endphp
                <p class="text-xs text-success-600 font-medium">✓ Weight confirmed: {{ $confirmedKg }} kg</p>
            @else
                <p class="text-xs text-gray-500 mb-2">
                    Weight required
                    @if ($enrolment->weight_kg)
                        <span class="text-gray-400">(enrolled as {{ $enrolment->weight_kg }} kg)</span>
                    @endif
                </p>
                <div class="flex items-center gap-2">
                    <x-filament::input.wrapper class="w-28">
                        <x-filament::input
                            type="number"
                            step="0.1"
                            min="1"
                            wire:model="weights.{{ $enrolment->id }}"
                            placeholder="{{ $enrolment->weight_kg ?? 'kg' }}"
                        />
                    </x-filament::input.wrapper>
                    <x-filament::button size="xs" color="primary" wire:click="confirmWeight({{ $enrolment->id }})">
                        Confirm weight
                    </x-filament::button>
                </div>
            @endif
        </div>
    @endif

    {{-- Events list --}}
    <div class="divide-y divide-gray-100 dark:divide-gray-800">
        @foreach ($enrolment->activeEvents as $ee)
            <div class="py-2">
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    {{ $ee->competitionEvent->event_code }} — {{ $ee->competitionEvent->eventType->name }}
                    @if ($ee->division)
                        <span class="text-xs text-gray-400">— {{ $ee->division->full_label }}</span>
                    @else
                        <span class="text-xs text-gray-400">— division not yet assigned</span>
                    @endif
                </p>
            </div>
        @endforeach
    </div>
</div>
