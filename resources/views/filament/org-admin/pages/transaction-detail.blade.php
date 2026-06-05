@php
    $comp        = $cart->competition;
    $enrolments  = $cart->enrolments->whereNotIn('status', ['draft'])->values();
    $platformFee = (float) ($cart->platform_fee_rate ?? app('tenant')?->platform_fee ?? 0);
@endphp

<div class="space-y-1 text-sm text-gray-500 dark:text-gray-400 mb-4">
    <div>{{ $cart->submitted_at ? tenant_date($cart->submitted_at) : '—' }} &mdash; registered by <strong class="text-gray-900 dark:text-white">{{ $cart->user?->name }}</strong></div>
    @if ($comp)
        <div>{{ tenant_date($comp->competition_date) }}@if ($comp->location_name) &mdash; {{ $comp->location_name }}@endif</div>
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
                <x-filament::badge :color="$enrolment->payment_status === 'received' ? 'success' : ($enrolment->status === 'withdrawn' ? 'danger' : 'warning')" size="sm">
                    @if ($enrolment->status === 'withdrawn')
                        Withdrawn
                    @elseif ($enrolment->payment_status === 'received')
                        Paid
                    @else
                        Outstanding
                    @endif
                </x-filament::badge>
            </div>

            @if ($enrolment->status === 'withdrawn')
                <p class="text-xs text-danger-600">
                    Withdrawn{{ $enrolment->withdrawn_at ? ' ' . tenant_date($enrolment->withdrawn_at) : '' }}
                    @if ($enrolment->withdrawal_reason) &mdash; {{ $enrolment->withdrawal_reason }} @endif
                </p>
            @else
                <div class="space-y-1">
                    @foreach ($enrolment->activeEvents as $ee)
                        <div class="flex justify-between text-xs text-gray-600 dark:text-gray-400">
                            <span>{{ $ee->competitionEvent->name }}@if ($ee->division) <span class="text-gray-400">&middot; {{ $ee->division->code }} &mdash; {{ $ee->division->label }}</span>@endif</span>
                            <span class="tabular-nums">{{ tenant_money($loop->first ? $firstRate : $addRate) }}</span>
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

                    @if ($enrolment->payment_status === 'received' && $enrolment->payment_received_at)
                        <p class="text-xs text-success-600 text-right">
                            Paid {{ tenant_money($enrolment->payment_amount ?? ($enrolment->fee_calculated + $platformFee)) }} on {{ tenant_date($enrolment->payment_received_at) }}
                        </p>
                    @endif
                </div>
            @endif
        </div>
    @endforeach
</div>

<div class="mt-3 flex justify-between text-sm font-semibold border-t border-gray-200 dark:border-gray-700 pt-3">
    <span class="text-gray-700 dark:text-gray-300">Total</span>
    <span class="text-gray-900 dark:text-white tabular-nums">{{ tenant_money($cart->total_amount) }}</span>
</div>
