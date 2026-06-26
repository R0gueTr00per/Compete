@php
    $profile            = $enrolment->competitor;
    $fullName           = $profile?->full_name ?? '—';
    $competitionStatus  = $competitionStatus ?? null;
    $selectedDayId      = $selectedDayId ?? null;
    $weightConfirmedForDay = $weightConfirmedForDay ?? [];

    // Day-specific check-in state
    $checkedInToday = $selectedDayId && $enrolment->checkedInForDay($selectedDayId);

    // Weight required if any active division on the selected day has a weight class
    $needsWeight = $selectedDayId && $enrolment->activeEvents->contains(fn ($ee) =>
        $ee->division?->competition_day_id === $selectedDayId
        && $ee->division?->weight_class_id !== null
    );

    $weightConfirmedThisSession = $selectedDayId
        && ($weightConfirmedForDay[$enrolment->id] ?? null) === $selectedDayId;

    $weightDone = $weightConfirmedThisSession;

    // Prior-day check-in history (days other than today)
    $priorCheckIns = $enrolment->checkIns
        ->filter(fn ($ci) => $ci->competition_day_id !== $selectedDayId)
        ->sortBy('checked_in_at');

    $dojoLabel = match ($enrolment->dojo_type) {
        'guest' => 'Guest — ' . ($enrolment->guest_style ?? 'Guest'),
        'lfp'   => $enrolment->dojo_name ?? 'LFP',
        default => null,
    };
    $paymentOutstanding = $enrolment->isPaymentOutstanding();
    $cart               = $enrolment->cart;
    $platformFee        = (float) ($cart?->platform_fee_rate ?? app('tenant')?->platform_fee ?? 0);
    $cartOutstanding    = $cart ? $cart->outstandingAmount($platformFee) : 0.0;

    // Total account balance — all unpaid carts for this user in this org
    $tenantId       = app('tenant')?->id;
    $userId         = $cart?->user_id;
    $accountBalance = \App\Models\EnrolmentCart::where('user_id', $userId)
        ->where('status', 'submitted')
        ->where('payment_status', '!=', 'received')
        ->whereHas('enrolments', fn ($q) => $q->withTrashed()
            ->whereHas('competition', fn ($q2) => $q2->where('organisation_id', $tenantId)))
        ->with(['enrolments' => fn ($q) => $q->withoutTrashed()->where('status', '!=', 'withdrawn')])
        ->get()
        ->sum(fn ($c) => $c->outstandingAmount($platformFee));

    $hasOtherCarts = $accountBalance > $cartOutstanding + 0.005;
@endphp

<div data-enrolment-id="{{ $enrolment->id }}" class="rounded-xl border {{ $checkedInToday ? 'border-success-200 dark:border-success-800' : 'border-gray-200 dark:border-slate-700' }} bg-white dark:bg-slate-900 shadow-sm p-4">

    {{-- Header row: name + check-in button --}}
    <div class="flex items-center justify-between gap-3 mb-3">
        <div>
            <p class="font-semibold text-gray-900 dark:text-white text-base">{{ $fullName }}</p>
            <dl class="mt-1 grid grid-cols-2 gap-x-4 gap-y-0.5">
                @if ($profile?->date_of_birth)
                    <div class="flex gap-1 text-xs text-gray-500">
                        <dt>Age:</dt>
                        <dd class="font-semibold">{{ $profile->age }}</dd>
                    </div>
                @endif
                @if ($enrolment->weight_kg)
                    <div class="flex gap-1 text-xs text-gray-500">
                        <dt>Weight:</dt>
                        <dd class="font-semibold">{{ number_format($enrolment->weight_kg, 1) }} kg</dd>
                    </div>
                @endif
                @if ($dojoLabel)
                    <div class="col-span-2 text-xs text-gray-400">{{ $dojoLabel }}</div>
                @endif
                @if ($enrolment->rank_id)
                    <div class="text-xs text-gray-400">{{ $enrolment->display_rank }}</div>
                @endif
            </dl>
        </div>

        <div class="shrink-0">
            @if ($checkedInToday)
                @php $todayCheckIn = $enrolment->checkIns->firstWhere('competition_day_id', $selectedDayId); @endphp
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1 text-xs font-medium text-success-700 dark:text-success-400">
                        <x-heroicon-s-check-circle class="w-4 h-4" />
                        {{ $todayCheckIn?->checked_in_at?->format('H:i') }}
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

    {{-- Prior-day check-in history --}}
    @if ($priorCheckIns->isNotEmpty())
        <div class="mb-3 flex flex-wrap gap-2">
            @foreach ($priorCheckIns as $ci)
                <span class="inline-flex items-center gap-1 text-xs text-success-600 dark:text-success-400 bg-success-50 dark:bg-success-900/20 rounded-full px-2 py-0.5">
                    <x-heroicon-s-check-circle class="w-3 h-3" />
                    {{ $ci->competitionDay?->date?->format('D j M') }}
                    <span class="text-success-400 dark:text-success-600">{{ $ci->checked_in_at?->format('H:i') }}</span>
                </span>
            @endforeach
        </div>
    @endif

    {{-- Weight input for today's weight-bracket events --}}
    @if ($needsWeight && ! $checkedInToday)
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
                        Accept new division
                    </x-filament::button>
                    <x-filament::button size="xs" color="gray" wire:click="ignoreDivisionChange({{ $enrolment->id }})">
                        Keep original division
                    </x-filament::button>
                    <x-filament::button size="xs" color="danger" wire:click="cancelEventRegistration({{ $enrolment->id }})">
                        Cancel event registration
                    </x-filament::button>
                </div>
            @elseif ($weightDone)
                <p class="text-xs text-success-600 font-medium weight-confirm-enter">✓ Weight confirmed for today</p>
            @else
                <p class="text-sm text-gray-500 mb-2">Check-in Weight</p>
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                    <input
                        type="number"
                        step="0.1"
                        min="1"
                        wire:model="weights.{{ $enrolment->id }}"
                        placeholder="{{ $enrolment->weight_kg ?? 'kg' }}"
                        class="w-full sm:w-28 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 py-1.5 px-3 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-primary-500"
                    />
                    <x-filament::button size="sm" color="primary" wire:click="confirmWeight({{ $enrolment->id }})">
                        Confirm weight
                    </x-filament::button>
                </div>
            @endif
        </div>
    @endif

    {{-- Payment --}}
    @if ($paymentOutstanding)
        <div class="mb-3 p-3 rounded-lg bg-warning-50 dark:bg-warning-900/20 border border-warning-200 dark:border-warning-800">
            <div class="flex items-start justify-between gap-2 mb-2">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-warning-600 dark:text-warning-400 mb-0.5">Balance due</p>
                    <p class="text-lg font-bold text-warning-800 dark:text-warning-200 leading-none">{{ tenant_money($accountBalance) }}</p>
                    @if ($hasOtherCarts)
                        <p class="text-xs text-warning-600 dark:text-warning-400 mt-1">
                            Includes other outstanding fees. This competition: {{ tenant_money($cartOutstanding) }}
                        </p>
                    @endif
                </div>
                <x-heroicon-o-banknotes class="w-5 h-5 text-warning-500 shrink-0 mt-0.5" />
            </div>
            <div x-data="{ confirming: false }">
                <div x-show="!confirming">
                    <x-filament::button size="sm" color="warning" x-on:click="confirming = true">
                        Mark payment received
                    </x-filament::button>
                </div>
                <div x-show="confirming" class="flex flex-wrap items-center gap-2">
                    <span class="text-xs text-warning-700 dark:text-warning-300">Confirm {{ tenant_money($cartOutstanding) }} received?</span>
                    <x-filament::button size="sm" color="success"
                        wire:click="recordPayment({{ $enrolment->id }})"
                        x-on:click="confirming = false">
                        Confirm
                    </x-filament::button>
                    <x-filament::button size="sm" color="gray" x-on:click="confirming = false">
                        Cancel
                    </x-filament::button>
                </div>
            </div>
        </div>
    @else
        <div class="mb-3 flex items-center gap-1.5 text-xs text-success-600 dark:text-success-400">
            <x-heroicon-m-check-circle class="w-3.5 h-3.5 shrink-0" />
            Paid
            @if ($cart?->payment_amount)
                — {{ tenant_money($cart->payment_amount) }}
            @endif
        </div>
    @endif

    {{-- Events list (filtered to selected day) --}}
    @php
        $dayEvents = $selectedDayId
            ? $enrolment->activeEvents->filter(fn ($ee) => $ee->division?->competition_day_id === $selectedDayId)
            : $enrolment->activeEvents;
    @endphp
    <div class="divide-y divide-gray-100 dark:divide-slate-800">
        @foreach ($dayEvents->sortBy('division.code') as $ee)
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
