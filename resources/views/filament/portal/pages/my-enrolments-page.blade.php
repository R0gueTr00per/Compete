<x-filament-panels::page>
    @php
        $carts     = $this->getTransactions();
        $draftCart = $this->getDraftCart();
    @endphp

    {{-- Draft cart resume banner --}}
    @if ($draftCart)
        <x-filament::section class="mb-6 border border-primary-200 dark:border-primary-700 bg-primary-50 dark:bg-primary-950">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="font-semibold text-sm text-primary-800 dark:text-primary-200">Incomplete registration</p>
                    <p class="text-sm text-primary-700 dark:text-primary-300 mt-0.5">
                        You have an unfinished registration for <strong>{{ $draftCart->competition->name }}</strong>.
                    </p>
                </div>
                <x-filament::button href="{{ route('filament.portal.pages.enrol') }}" tag="a" size="sm">Resume</x-filament::button>
            </div>
        </x-filament::section>
    @endif

    @if ($carts->isEmpty())
        <x-filament::section>
            <p class="text-center text-gray-500 py-8">You have no transactions yet.</p>
            <div class="flex justify-center mt-2">
                <x-filament::button href="{{ route('filament.portal.pages.enrol') }}" tag="a">Register now</x-filament::button>
            </div>
        </x-filament::section>
    @else
        @foreach ($carts as $cart)
            @php
                $comp        = $cart->competition;
                $enrolments  = $cart->enrolments->values();
                $active      = $enrolments->whereNotIn('status', ['withdrawn']);
                $platformFee = (float) ($cart->platform_fee_rate ?? app('tenant')?->platform_fee ?? 0);
                $groupTotal  = (float) $cart->total_amount;
                $allPaid     = $active->isNotEmpty() && $active->every(fn($e) => $e->payment_status === 'received');
                $anyPaid     = $active->where('payment_status', 'received')->isNotEmpty();
                $paymentLabel = $allPaid ? 'Paid' : ($anyPaid ? 'Partial' : 'Outstanding');
                $paymentColor = $allPaid ? 'success' : 'warning';
            @endphp

            <x-filament::section class="mb-6">
                <x-slot name="heading">{{ $comp?->name }}</x-slot>
                <x-slot name="description">
                    @if ($cart->submitted_at){{ tenant_date($cart->submitted_at) }} &mdash; @endif
                    @if ($comp){{ tenant_date($comp->competition_date) }}@if ($comp->location_name) &mdash; {{ $comp->location_name }}@endif@endif
                </x-slot>

                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($enrolments as $enrolment)
                        @php
                            $isOfficial = $enrolment->is_official_discount;
                            $firstRate  = $isOfficial && ($cart->fee_official_first_rate ?? $comp?->fee_official_first_event) !== null
                                ? (float) ($cart->fee_official_first_rate ?? $comp?->fee_official_first_event)
                                : (float) ($cart->fee_first_rate ?? $comp?->fee_first_event ?? 0);
                            $addRate    = $isOfficial && ($cart->fee_official_additional_rate ?? $comp?->fee_official_additional_event) !== null
                                ? (float) ($cart->fee_official_additional_rate ?? $comp?->fee_official_additional_event)
                                : (float) ($cart->fee_additional_rate ?? $comp?->fee_additional_event ?? 0);
                        @endphp

                        <div class="py-4">
                            <div class="flex items-center justify-between mb-2">
                                <p class="font-semibold text-sm text-gray-900 dark:text-white">{{ $enrolment->competitor?->full_name }}</p>
                                @if ($enrolment->status === 'withdrawn')
                                    <x-filament::badge color="danger" size="sm">Withdrawn</x-filament::badge>
                                @else
                                    <x-filament::badge :color="$enrolment->payment_status === 'received' ? 'success' : 'warning'" size="sm">
                                        {{ $enrolment->payment_status === 'received' ? 'Paid' : 'Outstanding' }}
                                    </x-filament::badge>
                                @endif
                            </div>

                            @if ($enrolment->status === 'withdrawn')
                                <p class="text-xs text-danger-600">
                                    Withdrawn{{ $enrolment->withdrawn_at ? ' ' . tenant_date($enrolment->withdrawn_at) : '' }}
                                    @if ($enrolment->withdrawal_reason) &mdash; {{ $enrolment->withdrawal_reason }} @endif
                                    @if ($enrolment->refund_requested) &bull; <span class="text-warning-600">Refund requested</span> @endif
                                </p>
                            @else
                                <div class="space-y-1">
                                    @foreach ($enrolment->activeEvents as $ee)
                                        <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-400">
                                            <span>
                                                {{ $ee->competitionEvent->name }}@if ($ee->division)<span class="text-gray-400"> &middot; {{ $ee->division->code }} &mdash; {{ $ee->division->label }}</span>@endif
                                                @if ($loop->first && $isOfficial)<span class="ml-1 text-gray-400">(official rate)</span>@endif
                                            </span>
                                            <span class="font-medium tabular-nums">{{ tenant_money($loop->first ? $firstRate : $addRate) }}</span>
                                        </div>
                                    @endforeach

                                    @if ($enrolment->is_late && ($cart->late_surcharge_rate ?? $comp?->late_surcharge))
                                        <div class="flex items-center justify-between text-xs text-warning-600">
                                            <span>Late surcharge</span>
                                            <span class="font-medium tabular-nums">{{ tenant_money($cart->late_surcharge_rate ?? $comp?->late_surcharge) }}</span>
                                        </div>
                                    @endif

                                    @if ($platformFee > 0)
                                        <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                            <span>Platform fee</span>
                                            <span class="tabular-nums">{{ tenant_money($platformFee) }}</span>
                                        </div>
                                    @endif

                                    <div class="flex items-center justify-between pt-1 mt-1 border-t border-gray-100 dark:border-gray-800 text-xs">
                                        <span class="text-gray-500">Subtotal</span>
                                        <span class="font-semibold text-gray-900 dark:text-white tabular-nums">{{ tenant_money($enrolment->fee_calculated + $platformFee) }}</span>
                                    </div>

                                    @if ($enrolment->payment_status === 'received' && $enrolment->payment_received_at)
                                        <p class="text-xs text-success-600 text-right">
                                            Paid {{ tenant_money($enrolment->payment_amount ?? $enrolment->fee_calculated) }} on {{ tenant_date($enrolment->payment_received_at) }}
                                        </p>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if ($groupTotal > 0)
                    <div class="mt-2 pt-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Total</span>
                            <x-filament::badge :color="$paymentColor" size="sm">{{ $paymentLabel }}</x-filament::badge>
                        </div>
                        <span class="text-sm font-bold tabular-nums">{{ tenant_money($groupTotal) }}</span>
                    </div>
                @endif
            </x-filament::section>
        @endforeach
    @endif

</x-filament-panels::page>
