@php
    $comp        = $cart->competition;
    $enrolments  = $cart->enrolments->whereNotIn('status', ['draft'])->values();
    $platformFee = (float) ($cart->platform_fee_rate ?? app('tenant')?->platform_fee ?? 0);
    $refunds     = $cart->refunds ?? collect();
@endphp

<div class="space-y-1 text-sm text-gray-500 dark:text-gray-400 mb-4">
    <div>{{ $cart->submitted_at ? tenant_date($cart->submitted_at) : '—' }} &mdash; registered by <strong class="text-gray-900 dark:text-white">{{ $cart->user?->name }}</strong></div>
    @if ($comp)
        <div>{{ tenant_date($comp->competition_date) }}@if ($comp->location_name) &mdash; {{ $comp->location_name }}@endif</div>
    @endif
    @if ($cart->payment_method)
        <div>Payment method: <strong class="text-gray-900 dark:text-white">{{ ucfirst($cart->payment_method) }}</strong></div>
    @endif
</div>

<div class="divide-y divide-gray-100 dark:divide-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
    @foreach ($enrolments as $enrolment)
        @php
            $isOfficial  = $enrolment->is_official_discount;
            $firstRate   = $isOfficial && $cart->fee_official_first_rate !== null
                ? $cart->fee_official_first_rate
                : $cart->fee_first_rate;
            $addRate     = $isOfficial && $cart->fee_official_additional_rate !== null
                ? $cart->fee_official_additional_rate
                : $cart->fee_additional_rate;
        @endphp

        <div class="px-4 py-3">
            <div class="flex items-center justify-between mb-2">
                <span class="font-semibold text-gray-900 dark:text-white text-sm">
                    {{ $enrolment->competitor?->full_name }}
                    @if ($isOfficial)
                        <span class="ml-1 text-xs text-gray-400">(official)</span>
                    @endif
                </span>
                <x-filament::badge :color="$enrolment->status === 'withdrawn' ? 'danger' : ($cart->isPaid() ? 'success' : 'warning')" size="sm">
                    @if ($enrolment->status === 'withdrawn') Withdrawn
                    @elseif ($cart->isPaid()) Paid
                    @else Outstanding
                    @endif
                </x-filament::badge>
            </div>

            @if ($enrolment->status === 'withdrawn')
                <p class="text-xs text-danger-600 mb-2">
                    Withdrawn{{ $enrolment->withdrawn_at ? ' ' . tenant_date($enrolment->withdrawn_at) : '' }}
                    @if ($enrolment->withdrawal_reason) &mdash; {{ $enrolment->withdrawal_reason }} @endif
                </p>
            @endif

            @php
                $allEvents = $enrolment->enrolmentEvents->isNotEmpty()
                    ? $enrolment->enrolmentEvents->sortBy('id')
                    : $enrolment->activeEvents->sortBy('id');
                $activeIdx = 0;
            @endphp
            <div class="space-y-1 @if($enrolment->status === 'withdrawn') opacity-60 @endif">
                @foreach ($allEvents as $ee)
                    @php
                        $isRemoved = (bool) ($ee->removed ?? false);
                        $fee       = $activeIdx === 0 ? $firstRate : $addRate;
                        if (! $isRemoved) $activeIdx++;
                    @endphp
                    <div class="flex items-center justify-between text-xs {{ $isRemoved ? 'text-gray-400' : 'text-gray-600 dark:text-gray-400' }}">
                        <span class="{{ $isRemoved ? 'line-through' : '' }}">
                            {{ $ee->competitionEvent->name }}@if ($ee->division) <span class="text-gray-400">&middot; {{ $ee->division->label }}</span>@endif
                            @if ($isRemoved)
                                <span class="ml-1 rounded px-1 py-0.5 text-xs font-medium {{ ($ee->removal_type ?? '') === 'user_withdrawn' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700' }}" style="text-decoration:none;">
                                    {{ ($ee->removal_type ?? '') === 'user_withdrawn' ? 'Withdrawn' : 'Cancelled' }}
                                </span>
                            @else
                                <span class="ml-1 rounded px-1 py-0.5 text-xs font-medium bg-green-100 text-green-700" style="text-decoration:none;">Active</span>
                            @endif
                        </span>
                        <span class="tabular-nums">{{ tenant_money($fee) }}</span>
                    </div>
                @endforeach

                @if ($enrolment->is_late && $cart->late_surcharge_rate)
                    <div class="flex justify-between text-xs text-warning-600">
                        <span>Late surcharge</span>
                        <span class="tabular-nums">{{ tenant_money($cart->late_surcharge_rate) }}</span>
                    </div>
                @endif

                @if ($platformFee > 0)
                    <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                        <span>Platform fee</span>
                        <span class="tabular-nums">{{ tenant_money($platformFee) }}</span>
                    </div>
                @endif

                <div class="flex justify-between text-xs pt-1 mt-1 border-t border-gray-100 dark:border-gray-800">
                    <span class="text-gray-500">Subtotal</span>
                    <span class="font-semibold text-gray-900 dark:text-white tabular-nums">{{ tenant_money($enrolment->fee_calculated + $platformFee) }}</span>
                </div>

                @if ($cart->isPaid() && $cart->payment_received_at)
                    <p class="text-xs text-success-600 text-right">
                        Paid {{ tenant_money($cart->payment_amount ?? $cart->total_amount) }} on {{ tenant_date($cart->payment_received_at) }}
                    </p>
                @endif
            </div>
        </div>
    @endforeach
</div>

<div class="mt-3 flex justify-between text-sm font-semibold border-t border-gray-200 dark:border-gray-700 pt-3">
    <span class="text-gray-700 dark:text-gray-300">Total charged</span>
    <span class="text-gray-900 dark:text-white tabular-nums">{{ tenant_money($cart->total_amount) }}</span>
</div>

{{-- Refunds section --}}
@if ($refunds->isNotEmpty())
    <div class="mt-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-2">Refunds</p>
        <div class="divide-y divide-gray-100 dark:divide-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
            @foreach ($refunds as $refund)
                <div class="px-4 py-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $refund->enrolment?->competitor?->full_name ?? 'All competitors' }}
                                </span>
                                <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium
                                    {{ $refund->status === 'issued'
                                        ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                        : ($refund->status === 'voided'
                                            ? 'bg-gray-100 text-gray-500'
                                            : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400') }}">
                                    {{ ucfirst($refund->status) }}
                                </span>
                                <span class="text-xs text-gray-400">{{ $refund->typeLabel() }}</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $refund->reason }}</p>
                            @if ($refund->payment_method)
                                <p class="text-xs text-gray-400 mt-0.5">Via {{ ucfirst($refund->payment_method) }}</p>
                            @endif
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
                </div>
            @endforeach
        </div>

        @php
            $issuedTotal = $refunds->where('status', 'issued')->sum('amount');
            $pendingTotal = $refunds->where('status', 'pending')->sum('amount');
        @endphp
        @if ($issuedTotal > 0)
            <div class="mt-2 flex justify-between text-xs text-gray-500">
                <span>Refunded</span>
                <span class="tabular-nums text-danger-600 dark:text-danger-400">&minus;{{ tenant_money($issuedTotal) }}</span>
            </div>
        @endif
        @if ($pendingTotal > 0)
            <div class="mt-1 flex justify-between text-xs text-warning-600 dark:text-warning-400">
                <span>Pending refund</span>
                <span class="tabular-nums">&minus;{{ tenant_money($pendingTotal) }}</span>
            </div>
        @endif
    </div>
@endif
