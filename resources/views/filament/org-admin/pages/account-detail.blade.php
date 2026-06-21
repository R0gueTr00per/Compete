{{-- User identity --}}
<div class="flex items-center gap-3 mb-5 pb-4 border-b border-gray-100 dark:border-gray-800">
    <div class="min-w-0">
        @if ($user->selfProfile?->full_name)
            <p class="text-base font-semibold text-gray-900 dark:text-white">{{ $user->selfProfile->full_name }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
        @else
            <p class="text-base font-semibold text-gray-900 dark:text-white">{{ $user->email }}</p>
        @endif
    </div>
</div>

@php
    $orgFee      = (float) (app('tenant')?->platform_fee ?? 0);
    $outstanding = 0.0;
    $totalPaid   = 0.0;
    $refundDue   = 0.0;

    foreach ($carts as $cart) {
        $pf = (float) ($cart->platform_fee_rate ?? $orgFee);
        if ($cart->isPaid()) {
            $totalPaid += (float) $cart->total_amount;
        } else {
            $outstanding += $cart->outstandingAmount($pf);
        }
        $refundDue += $cart->refunds->where('status', 'pending')->sum('amount');
    }
    $net = $outstanding - $refundDue;
@endphp

{{-- Balance summary --}}
<div class="flex flex-wrap gap-3 mb-6">
    @if ($totalPaid > 0)
        <div class="flex-1 min-w-[120px] rounded-lg bg-success-50 dark:bg-success-900/20 border border-success-200 dark:border-success-800 px-4 py-3 text-center">
            <p class="text-xs text-success-600 dark:text-success-400 font-medium">Paid</p>
            <p class="text-lg font-bold text-success-700 dark:text-success-300 tabular-nums">{{ tenant_money($totalPaid) }}</p>
        </div>
    @endif
    @if ($outstanding > 0)
        <div class="flex-1 min-w-[120px] rounded-lg bg-warning-50 dark:bg-warning-900/20 border border-warning-200 dark:border-warning-800 px-4 py-3 text-center">
            <p class="text-xs text-warning-600 dark:text-warning-400 font-medium">Outstanding</p>
            <p class="text-lg font-bold text-warning-700 dark:text-warning-300 tabular-nums">{{ tenant_money($outstanding) }}</p>
        </div>
    @endif
    @if ($refundDue > 0)
        <div class="flex-1 min-w-[120px] rounded-lg bg-danger-50 dark:bg-danger-900/20 border border-danger-200 dark:border-danger-800 px-4 py-3 text-center">
            <p class="text-xs text-danger-600 dark:text-danger-400 font-medium">Refund due</p>
            <p class="text-lg font-bold text-danger-700 dark:text-danger-300 tabular-nums">{{ tenant_money($refundDue) }}</p>
        </div>
    @endif
    @if ($totalPaid > 0 || $refundDue > 0 || abs($net) < 0.01)
    <div class="flex-1 min-w-[120px] rounded-lg px-4 py-3 text-center
        {{ abs($net) < 0.01
            ? 'bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700'
            : ($net > 0
                ? 'bg-warning-50 dark:bg-warning-900/20 border border-warning-200 dark:border-warning-800'
                : 'bg-danger-50 dark:bg-danger-900/20 border border-danger-200 dark:border-danger-800') }}">
        <p class="text-xs font-medium
            {{ abs($net) < 0.01 ? 'text-gray-500 dark:text-gray-400' : ($net > 0 ? 'text-warning-600 dark:text-warning-400' : 'text-danger-600 dark:text-danger-400') }}">
            @if (abs($net) < 0.01) Settled @elseif ($net > 0) Owes @else Refund due @endif
        </p>
        <p class="text-lg font-bold tabular-nums
            {{ abs($net) < 0.01 ? 'text-gray-600 dark:text-gray-300' : ($net > 0 ? 'text-warning-700 dark:text-warning-300' : 'text-danger-700 dark:text-danger-300') }}">
            {{ abs($net) < 0.01 ? '—' : tenant_money(abs($net)) }}
        </p>
    </div>
    @endif
</div>

@if ($carts->isEmpty())
    <p class="text-sm text-gray-400 dark:text-gray-500 text-center py-4">No transactions found.</p>
@endif

{{-- Per-competition carts --}}
@foreach ($carts as $cart)
    @php
        $comp        = $cart->competition;
        $enrolments  = $cart->enrolments->whereNotIn('status', ['draft'])->values();
        $platformFee = (float) ($cart->platform_fee_rate ?? $orgFee);
        $refunds     = $cart->refunds ?? collect();
        $isPaid      = $cart->isPaid();
        $firstRate   = (float) ($cart->fee_first_rate ?? 0);
        $addRate     = (float) ($cart->fee_additional_rate ?? 0);
        $cartAccent  = $isPaid
            ? 'border-l-green-400 dark:border-l-green-500'
            : 'border-l-danger-400 dark:border-l-danger-500';
    @endphp

    <div class="mb-4 rounded-lg border border-gray-200 dark:border-gray-700 border-l-4 {{ $cartAccent }} bg-white dark:bg-gray-900 overflow-hidden"
         x-data="{ open: {{ $isPaid ? 'false' : 'true' }} }">

        {{-- Clickable header --}}
        <button type="button" @click="open = !open"
                class="w-full flex items-center justify-between gap-3 px-4 pt-3 pb-2 text-left">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $comp?->name ?? '—' }}</p>
                <p class="text-xs text-gray-500">
                    {{ $comp ? tenant_date($comp->competition_date) : '' }}
                    @if ($cart->submitted_at) &mdash; submitted {{ tenant_date($cart->submitted_at) }} @endif
                    @if ($cart->payment_method) &mdash; {{ ucfirst($cart->payment_method) }} @endif
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <x-filament::badge :color="$isPaid ? 'success' : 'warning'" size="sm">
                    {{ $isPaid ? 'Paid' : 'Outstanding' }}
                </x-filament::badge>
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300 tabular-nums">{{ tenant_money($cart->total_amount) }}</span>
                <svg class="w-4 h-4 text-gray-400 transition-transform duration-200"
                     :class="open ? 'rotate-180' : ''"
                     xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06z" clip-rule="evenodd" />
                </svg>
            </div>
        </button>

        {{-- Collapsible body --}}
        <div x-show="open" x-transition:enter="transition ease-out duration-100"
             x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-75"
             x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">

            <div class="divide-y divide-gray-100 dark:divide-gray-800 border-t border-gray-100 dark:border-gray-800">
                @foreach ($enrolments as $enrolment)
                    @php
                        $isEOfficial = $enrolment->is_official_discount;
                        $eFirstRate  = ($isEOfficial && $cart->fee_official_first_rate !== null) ? (float) $cart->fee_official_first_rate : $firstRate;
                        $eAddRate    = ($isEOfficial && $cart->fee_official_additional_rate !== null) ? (float) $cart->fee_official_additional_rate : $addRate;
                        $isWithdrawn = $enrolment->status === 'withdrawn';
                    @endphp
                    <div class="px-4 py-3 {{ $isWithdrawn ? 'opacity-60' : '' }}">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm font-medium text-gray-900 dark:text-white {{ $isWithdrawn ? 'line-through' : '' }}">
                                {{ $enrolment->competitor?->full_name }}
                            </span>
                            @if ($isWithdrawn)
                                <x-filament::badge color="gray" size="sm">Withdrawn</x-filament::badge>
                            @endif
                        </div>

                        @if ($isWithdrawn && $enrolment->withdrawn_at)
                            <p class="text-xs text-danger-600 mb-1">
                                Withdrawn {{ tenant_date($enrolment->withdrawn_at) }}
                                @if ($enrolment->withdrawal_reason) &mdash; {{ $enrolment->withdrawal_reason }} @endif
                            </p>
                        @endif

                        @php
                            $allEvents = $enrolment->enrolmentEvents->isNotEmpty()
                                ? $enrolment->enrolmentEvents->sortBy('id')
                                : $enrolment->activeEvents->sortBy('id');
                            $activeIdx = 0;
                        @endphp
                        <div class="space-y-0.5 text-xs text-gray-500 dark:text-gray-400">
                            @foreach ($allEvents as $ee)
                                @php
                                    $isRemoved = (bool) ($ee->removed ?? false);
                                    $fee       = $activeIdx === 0 ? $eFirstRate : $eAddRate;
                                    if (! $isRemoved) $activeIdx++;
                                @endphp
                                <div class="flex justify-between gap-2 {{ $isRemoved ? 'line-through text-gray-400' : '' }}">
                                    <span class="flex items-center gap-1.5 min-w-0">
                                        @if ($ee->division && ! $isRemoved)
                                            <span class="shrink-0 font-mono text-[0.65rem] font-semibold px-1 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">{{ $ee->division->code }}</span>
                                        @endif
                                        {{ $ee->competitionEvent?->name }}
                                        @if ($ee->division) <span class="text-gray-400">&mdash; {{ $ee->division->label }}</span> @endif
                                        @if ($isRemoved)
                                            <span class="ml-1 rounded px-1 py-0.5 text-xs font-medium no-underline
                                                {{ ($ee->removal_type ?? '') === 'user_withdrawn' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700' }}"
                                                style="text-decoration:none;">
                                                {{ ($ee->removal_type ?? '') === 'user_withdrawn' ? 'Withdrawn' : 'Cancelled' }}
                                            </span>
                                        @endif
                                    </span>
                                    <span class="tabular-nums">{{ tenant_money($fee) }}</span>
                                </div>
                            @endforeach

                            @if ($enrolment->is_late && $cart->late_surcharge_rate)
                                <div class="flex justify-between text-warning-600">
                                    <span>Late surcharge</span>
                                    <span class="tabular-nums">{{ tenant_money($cart->late_surcharge_rate) }}</span>
                                </div>
                            @endif
                            @if ($platformFee > 0)
                                <div class="flex justify-between">
                                    <span>Platform fee</span>
                                    <span class="tabular-nums">{{ tenant_money($platformFee) }}</span>
                                </div>
                            @endif
                            <div class="flex justify-between pt-1 mt-1 border-t border-gray-100 dark:border-gray-800 font-medium text-gray-700 dark:text-gray-300">
                                <span>Subtotal</span>
                                <span class="tabular-nums">{{ tenant_money($enrolment->fee_calculated + $platformFee) }}</span>
                            </div>
                            @if ($isPaid && $cart->payment_received_at)
                                <p class="text-right text-success-600">
                                    Paid {{ tenant_date($cart->payment_received_at) }}
                                    @if ($cart->payment_method) via {{ ucfirst($cart->payment_method) }} @endif
                                    @if ($cart->acceptedBy) &mdash; taken by {{ $cart->acceptedBy->full_name }} @endif
                                </p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Refunds for this cart --}}
            @if ($refunds->isNotEmpty())
                <div class="divide-y divide-gray-100 dark:divide-gray-800 border-t border-gray-100 dark:border-gray-800">
                    @foreach ($refunds as $refund)
                        <div class="flex items-start justify-between gap-3 px-4 py-3 bg-danger-50/40 dark:bg-danger-950/20">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-xs font-medium text-gray-700 dark:text-gray-300">
                                        {{ $refund->enrolment?->competitor?->full_name ?? 'Unknown' }}
                                    </span>
                                    <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium
                                        {{ $refund->status === 'issued' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                                        {{ ucfirst($refund->status) }}
                                    </span>
                                </div>
                                <p class="text-xs text-gray-400 mt-0.5">{{ $refund->reason }}</p>
                                @if ($refund->issued_at)
                                    <p class="text-xs text-gray-400 mt-0.5">
                                        Issued {{ tenant_date($refund->issued_at) }}
                                        @if ($refund->issuedBy) by {{ $refund->issuedBy->name }} @endif
                                    </p>
                                @endif
                            </div>
                            <span class="text-sm font-semibold text-danger-600 dark:text-danger-400 tabular-nums flex-shrink-0">
                                &minus;{{ tenant_money($refund->amount) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>{{-- /collapsible body --}}
    </div>{{-- /cart card --}}
@endforeach
