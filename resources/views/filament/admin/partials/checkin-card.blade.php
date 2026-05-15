@php
    $profile            = $enrolment->competitor?->competitorProfile;
    $fullName           = $profile ? "{$profile->first_name} {$profile->surname}" : $enrolment->competitor?->name;
    $competitionStatus  = $competitionStatus ?? null;
    $checkedIn          = $enrolment->checked_in;
    $needsWeight        = $enrolment->activeEvents->contains(fn ($ee) => $ee->competitionEvent->requires_weight_check);
    $weightDone         = $enrolment->activeEvents
        ->filter(fn ($ee) => $ee->competitionEvent->requires_weight_check)
        ->every(fn ($ee) => $ee->weight_confirmed_kg);
    $dojoLabel          = match ($enrolment->dojo_type) {
        'guest' => 'Guest — ' . ($enrolment->guest_style ?? 'Guest'),
        'lfp'   => $enrolment->dojo_name ?? 'LFP',
        default => null,
    };
    $paymentOutstanding = $enrolment->isPaymentOutstanding();
@endphp

<div class="rounded-xl border {{ $checkedIn ? 'border-success-200 dark:border-success-800' : 'border-gray-200 dark:border-slate-700' }} bg-white dark:bg-slate-900 shadow-sm p-4">

    {{-- Header row: name + check-in button --}}
    <div class="flex items-center justify-between gap-3 mb-3">
        <div>
            <p class="font-semibold text-gray-900 dark:text-white text-base">{{ $fullName }}</p>
            <div class="flex flex-wrap gap-x-3 gap-y-0.5 mt-0.5">
                @if ($profile?->date_of_birth)
                    <span class="text-xs text-gray-500">Age: <strong>{{ $profile->age }}</strong></span>
                @endif
                @if ($enrolment->weight_kg)
                    <span class="text-xs text-gray-500">Enrolled weight: <strong>{{ number_format($enrolment->weight_kg, 1) }} kg</strong></span>
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
                    @if ($competitionStatus !== 'running')
                        <x-filament::button size="xs" color="gray" wire:click="undoCheckIn({{ $enrolment->id }})">
                            Undo
                        </x-filament::button>
                    @endif
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
        <div class="mb-3 p-3 rounded-lg bg-gray-50 dark:bg-slate-800">
            @if ($pendingDivisionChange)
                {{-- Division was changed automatically — confirm or revert --}}
                <p class="text-xs font-semibold text-warning-700 dark:text-warning-300 mb-2">
                    Weight {{ $pendingDivisionChange['weight_kg'] }} kg — division updated:
                </p>
                @foreach ($pendingDivisionChange['changes'] as $change)
                    <div class="text-xs text-gray-700 dark:text-gray-300 mb-1">
                        <span class="font-medium">{{ $change['event_name'] }}</span>:
                        <span class="text-gray-400 line-through">{{ $change['original_label'] }}</span>
                        <span class="mx-1">→</span>
                        <span class="font-medium text-success-600 dark:text-success-400">{{ $change['new_label'] }}</span>
                    </div>
                @endforeach
                <div class="flex flex-wrap gap-2 mt-3">
                    <x-filament::button size="xs" color="success" wire:click="acceptDivisionChange({{ $enrolment->id }})">
                        Accept
                    </x-filament::button>
                    <x-filament::button size="xs" color="gray" wire:click="ignoreDivisionChange({{ $enrolment->id }})">
                        Keep original division
                    </x-filament::button>
                    <x-filament::button size="xs" color="danger" wire:click="cancelWeightChange({{ $enrolment->id }})">
                        Cancel (undo weight)
                    </x-filament::button>
                </div>
            @elseif ($weightDone)
                @php $confirmedKg = $enrolment->activeEvents->firstWhere(fn($ee) => $ee->weight_confirmed_kg)?->weight_confirmed_kg; @endphp
                <p class="text-xs text-success-600 font-medium">✓ Weight confirmed: {{ number_format($confirmedKg, 1) }} kg</p>
            @else
                <p class="text-xs text-gray-500 mb-2">Check-in Weight</p>
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

    {{-- Payment --}}
    @if ($paymentOutstanding)
        <div class="mb-3 p-3 rounded-lg bg-warning-50 dark:bg-warning-900/20 border border-warning-200 dark:border-warning-800">
            <div class="flex flex-wrap items-center gap-2">
                <p class="text-xs font-semibold text-warning-800 dark:text-warning-200 flex-1 min-w-0">
                    💰 Payment outstanding — ${{ number_format($enrolment->fee_calculated, 2) }}
                </p>
                <div class="flex items-center gap-2 shrink-0">
                    <x-filament::input.wrapper class="w-20">
                        <x-filament::input
                            type="number"
                            step="0.01"
                            min="0"
                            wire:model="paymentAmounts.{{ $enrolment->id }}"
                            placeholder="{{ number_format($enrolment->fee_calculated, 2) }}"
                        />
                    </x-filament::input.wrapper>
                    <x-filament::button size="xs" color="warning"
                        wire:click="recordPayment({{ $enrolment->id }})">
                        Mark paid
                    </x-filament::button>
                </div>
            </div>
        </div>
    @else
        <div class="mb-3 flex items-center gap-1.5 text-xs text-success-600 dark:text-success-400">
            <x-heroicon-m-check-circle class="w-3.5 h-3.5 shrink-0" />
            Paid
            @if ($enrolment->payment_amount)
                — ${{ number_format($enrolment->payment_amount, 2) }}
            @endif
        </div>
    @endif

    {{-- Events list --}}
    <div class="divide-y divide-gray-100 dark:divide-slate-800">
        @foreach ($enrolment->activeEvents->sortBy('division.code') as $ee)
            <div class="py-2">
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    @if ($ee->division)
                        <span class="font-medium">{{ $ee->division->code }}</span>
                        <span class="text-xs text-gray-400">— {{ $ee->competitionEvent->name }} — {{ $ee->division->label }}</span>
                    @else
                        {{ $ee->competitionEvent->name }}
                        <span class="text-xs text-gray-400">— division not yet assigned</span>
                    @endif
                </p>
            </div>
        @endforeach
    </div>
</div>
