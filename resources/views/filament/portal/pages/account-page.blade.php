<x-filament-panels::page>
    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm mb-4">
        <span class="text-gray-500 dark:text-gray-400">Account reference</span>
        <span class="font-mono font-semibold text-gray-900 dark:text-white">#{{ auth()->id() }}</span>
        <span class="text-xs text-gray-400 dark:text-gray-500">Quote this when contacting your organisation about payments or refunds.</span>
    </div>

    @php
        $carts     = $this->getTransactions();
        $draftCart = $this->getDraftCart();
    @endphp

    {{-- Draft cart resume banner --}}
    @if ($draftCart)
        @php
            $draftComps = $draftCart->enrolments->map(fn($e) => $e->competition?->name)->filter()->unique()->implode(', ');
        @endphp
        <x-filament::section class="mb-6 border border-primary-200 dark:border-primary-700 bg-primary-50 dark:bg-primary-950">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="font-semibold text-sm text-primary-800 dark:text-primary-200">Incomplete registration</p>
                    <p class="text-sm text-primary-700 dark:text-primary-300 mt-0.5">
                        You have an unfinished registration{{ $draftComps ? ' for <strong>' . e($draftComps) . '</strong>' : '' }}.
                    </p>
                </div>
                <x-filament::button href="{{ route('filament.portal.pages.enrol') }}" tag="a" size="sm">Resume</x-filament::button>
            </div>
        </x-filament::section>
    @endif

    @if ($carts->isEmpty())
        <x-filament::section>
            <p class="text-center text-gray-500 py-8">No transaction history yet.</p>
        </x-filament::section>
    @else
        @foreach ($carts as $cart)
            @php
                $allEnrolments = $cart->enrolments->values();
                $platformFee   = (float) ($cart->platform_fee_rate ?? app('tenant')?->platform_fee ?? 0);
                $cartTotal     = (float) $cart->total_amount;
                $byComp        = $allEnrolments->groupBy(fn ($e) => $e->competition_id ?? 0);

                // Cart-level payment state (across all active enrolments in this cart)
                $cartActive    = $allEnrolments->filter(fn ($e) => ! $e->trashed() && $e->status !== 'withdrawn');
                $cartIsPaid = $cart->isPaid();
                $cartLabel  = $cartActive->isEmpty() ? null : ($cartIsPaid ? 'Paid' : 'Outstanding');
                $cartColor  = $cartIsPaid ? 'success' : 'warning';
            @endphp

            <x-filament::section
                class="mb-6"
                :collapsible="$cartIsPaid"
                :collapsed="$cartIsPaid"
                persist-collapsed
                id="cart-{{ $cart->id }}"
            >
                {{-- Cart / transaction header --}}
                <x-slot name="heading">
                    <div class="flex items-center gap-3">
                        <span>{{ $cart->submitted_at ? tenant_date($cart->submitted_at) : 'Registration' }}</span>
                        @if ($cartLabel)
                            <x-filament::badge :color="$cartColor" size="sm">{{ $cartLabel }}</x-filament::badge>
                        @endif
                    </div>
                </x-slot>
                <x-slot name="description">
                    {{ tenant_money($cartTotal) }}
                    @if ($cart->payment_method) &mdash; {{ ucfirst($cart->payment_method) }}@endif
                </x-slot>

                {{-- Per-competition groups --}}
                @foreach ($byComp as $compId => $compEnrolments)
                    @php
                        $comp        = $compEnrolments->first()?->competition;
                        $live        = $compEnrolments->filter(fn ($e) => ! $e->trashed());
                        $active      = $live->whereNotIn('status', ['withdrawn']);
                        $allReplaced = $live->isEmpty() && $compEnrolments->isNotEmpty();
                        $noActive    = $active->isEmpty();
                    @endphp

                    {{-- Competition sub-heading --}}
                    <div class="{{ $loop->first ? 'mb-1' : 'mt-5 pt-4 border-t border-gray-200 dark:border-gray-700 mb-1' }}">
                        <p class="font-semibold text-sm text-gray-800 dark:text-gray-200">{{ $comp?->name ?? 'Competition' }}</p>
                        @if ($comp)
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                {{ tenant_date($comp->competition_date) }}
                                @if ($comp->location_name) &mdash; {{ $comp->location_name }}@endif
                            </p>
                        @endif
                    </div>

                    <div class="space-y-2 mt-3">
                        @if ($compEnrolments->isEmpty())
                            <p class="py-3 text-xs text-gray-400 italic">Registration superseded by a new registration.</p>
                        @endif

                        @foreach ($compEnrolments as $enrolment)
                            @if ($enrolment->trashed())
                                <div class="rounded-lg border border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-3 py-2.5 opacity-50">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-sm text-gray-500 line-through">{{ $enrolment->competitor?->full_name }}</p>
                                        <x-filament::badge color="gray" size="sm">Replaced</x-filament::badge>
                                    </div>
                                    <p class="text-xs text-gray-400 mt-0.5">Original registration — replaced by new registration</p>
                                </div>
                                @continue
                            @endif

                            @php
                                $isWithdrawnE   = $enrolment->status === 'withdrawn';
                                $isPaid        = $cart->isPaid();
                                $removedEvents = $enrolment->enrolmentEvents ?? collect();
                                $isOfficial     = $enrolment->is_official_discount;
                                // Use competition rates; fall back to frozen cart rates
                                $firstRate = $isOfficial && ($comp?->fee_official_first_event ?? $cart->fee_official_first_rate) !== null
                                    ? (float) ($comp?->fee_official_first_event ?? $cart->fee_official_first_rate)
                                    : (float) ($comp?->fee_first_event ?? $cart->fee_first_rate ?? 0);
                                $addRate = $isOfficial && ($comp?->fee_official_additional_event ?? $cart->fee_official_additional_rate) !== null
                                    ? (float) ($comp?->fee_official_additional_event ?? $cart->fee_official_additional_rate)
                                    : (float) ($comp?->fee_additional_event ?? $cart->fee_additional_rate ?? 0);
                            @endphp

                            @php
                                $enrolmentAccent = $isWithdrawnE
                                    ? 'border-l-gray-300 dark:border-l-gray-600'
                                    : ($isPaid ? 'border-l-green-400 dark:border-l-green-500' : 'border-l-amber-400 dark:border-l-amber-500');
                            @endphp
                            <div class="rounded-lg border border-gray-100 dark:border-gray-700 border-l-4 {{ $enrolmentAccent }} bg-gray-50 dark:bg-gray-800 p-3 hover:bg-gray-100 dark:hover:bg-gray-700/60 transition-colors {{ $isWithdrawnE ? 'opacity-70' : '' }}">
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <p class="font-semibold text-sm text-gray-900 dark:text-white">{{ $enrolment->competitor?->full_name }}</p>
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        @if ($isWithdrawnE)
                                            <x-filament::badge color="danger" size="sm">Withdrawn</x-filament::badge>
                                            @if ($isPaid)
                                                <x-filament::badge color="success" size="sm">Paid</x-filament::badge>
                                            @endif
                                        @elseif ($isPaid)
                                            <x-filament::badge color="success" size="sm">Paid</x-filament::badge>
                                        @endif
                                    </div>
                                </div>

                                {{-- Per-day check-in status pills --}}
                                @if (! $isWithdrawnE && $enrolment->checkIns->isNotEmpty())
                                    @php
                                        // Days on which this enrolment has divisions
                                        $enrolmentDays = $enrolment->activeEvents
                                            ->map(fn ($ee) => $ee->division?->competitionDay)
                                            ->filter()
                                            ->unique('id')
                                            ->sortBy('date');
                                        $showDays = $enrolmentDays->isNotEmpty()
                                            ? $enrolmentDays
                                            : $enrolment->competition?->competitionDays?->sortBy('date') ?? collect();
                                    @endphp
                                    @if ($showDays->count() > 0)
                                        <div class="flex flex-wrap gap-1.5 mb-2">
                                            @foreach ($showDays as $day)
                                                @php $isCheckedIn = $enrolment->checkedInForDay($day->id); @endphp
                                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium
                                                    {{ $isCheckedIn
                                                        ? 'bg-success-100 dark:bg-success-900/30 text-success-700 dark:text-success-400'
                                                        : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400' }}">
                                                    @if ($isCheckedIn)
                                                        <x-heroicon-s-check-circle class="w-3 h-3" />
                                                    @else
                                                        <x-heroicon-o-minus-circle class="w-3 h-3" />
                                                    @endif
                                                    {{ $day->date->format('D j M') }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                @endif

                                <div class="space-y-1">
                                    @foreach ($enrolment->activeEvents as $ee)
                                        <div class="text-xs {{ $isWithdrawnE ? 'text-gray-500 line-through' : 'text-gray-600 dark:text-gray-400' }}">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="flex items-center gap-1.5 min-w-0">
                                                    @if ($ee->division && ! $isWithdrawnE)
                                                        <span class="shrink-0 font-mono text-[0.65rem] font-semibold px-1 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">{{ $ee->division->code }}</span>
                                                    @endif
                                                    <span>{{ $ee->competitionEvent->name }}@if ($ee->division)<span class="{{ $isWithdrawnE ? '' : 'text-gray-400 dark:text-gray-500' }}"> &mdash; {{ $ee->division->label }}</span>@endif@if (! $isWithdrawnE && $loop->first && $isOfficial)<span class="ml-1 text-gray-400">(official rate)</span>@endif</span>
                                                </span>
                                                <span class="{{ $isWithdrawnE ? '' : 'font-medium' }} tabular-nums shrink-0">{{ tenant_money($loop->first ? $firstRate : $addRate) }}</span>
                                            </div>
                                            @if (! $isWithdrawnE && $ee->previous_division_id && $ee->previousDivision)
                                                <p class="text-xs text-info-600 dark:text-info-400 mt-0.5 ml-2">Changed from: {{ $ee->previousDivision->label }}</p>
                                            @endif
                                        </div>
                                    @endforeach

                                    @if (! $isWithdrawnE)
                                        @foreach ($removedEvents as $ree)
                                            <div class="flex items-center gap-2 text-xs text-gray-400 line-through">
                                                <span>{{ $ree->competitionEvent?->name }}@if ($ree->division) &middot; {{ $ree->division->label }}@endif</span>
                                                <span class="no-underline not-italic rounded px-1 py-0.5 text-xs font-medium {{ $ree->removal_type === 'user_withdrawn' ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' }}" style="text-decoration:none;">
                                                    {{ $ree->removal_type === 'user_withdrawn' ? 'Withdrawn' : 'Cancelled' }}
                                                </span>
                                            </div>
                                        @endforeach
                                    @endif

                                    @if ($enrolment->is_late && ($comp?->late_surcharge ?? $cart->late_surcharge_rate))
                                        <div class="flex items-center justify-between text-xs {{ $isWithdrawnE ? 'text-gray-500 line-through' : 'text-warning-600' }}">
                                            <span>Late surcharge</span>
                                            <span class="tabular-nums">{{ tenant_money($comp?->late_surcharge ?? $cart->late_surcharge_rate) }}</span>
                                        </div>
                                    @endif

                                    @if ($platformFee > 0)
                                        <div class="flex items-center justify-between text-xs {{ $isWithdrawnE ? 'text-gray-500 line-through' : 'text-gray-500 dark:text-gray-400' }}">
                                            <span>Platform fee</span>
                                            <span class="tabular-nums">{{ tenant_money($platformFee) }}</span>
                                        </div>
                                    @endif

                                    @if ($isWithdrawnE)
                                        @if ($isPaid)
                                            <div class="flex items-center justify-between pt-1 mt-1 border-t border-gray-100 dark:border-gray-800 text-xs">
                                                <span class="text-gray-400">Originally paid</span>
                                                <span class="font-semibold text-gray-500 tabular-nums">{{ tenant_money($enrolment->fee_calculated + $platformFee) }}</span>
                                            </div>
                                            @if ($enrolment->payment_received_at || $cart->payment_method)
                                                <p class="text-xs text-gray-400 text-right">
                                                    @if ($enrolment->payment_received_at){{ tenant_date($enrolment->payment_received_at) }}@endif
                                                    @if ($cart->payment_method) via {{ ucfirst($cart->payment_method) }}@endif
                                                </p>
                                            @endif
                                            @if ($enrolment->refund_requested)
                                                <p class="text-xs text-warning-600 mt-1">&bull; Refund pending</p>
                                            @endif
                                        @else
                                            <div class="flex items-center justify-between pt-1 mt-1 border-t border-gray-100 dark:border-gray-800 text-xs">
                                                <span class="text-gray-400">
                                                    Withdrawn{{ $enrolment->withdrawn_at ? ' ' . tenant_date($enrolment->withdrawn_at) : '' }}
                                                    @if ($enrolment->withdrawal_reason) &mdash; {{ $enrolment->withdrawal_reason }}@endif
                                                </span>
                                                <span class="font-semibold text-gray-400 tabular-nums">No charge</span>
                                            </div>
                                        @endif
                                    @else
                                        <div class="flex items-center justify-between pt-1 mt-1 border-t border-gray-100 dark:border-gray-800 text-xs">
                                            <span class="text-gray-500">Subtotal</span>
                                            <span class="font-semibold text-gray-900 dark:text-white tabular-nums">{{ tenant_money($enrolment->fee_calculated + $platformFee) }}</span>
                                        </div>
                                        @if ($isPaid)
                                            <p class="text-xs text-success-600 text-right">
                                                Paid {{ tenant_money($enrolment->payment_amount ?? ($enrolment->fee_calculated + $platformFee)) }}
                                                @if ($enrolment->payment_received_at) on {{ tenant_date($enrolment->payment_received_at) }}@endif
                                            </p>
                                        @endif
                                    @endif
                                </div>
                            </div>

                            {{-- Withdrawal confirmation modal --}}
                            @if ($withdrawingId === $enrolment->id)
                                @php
                                    $isPaidW       = $cart->isPaid();
                                    $withinCutoffW = $enrolment->isWithinCancellationCutoff();
                                @endphp
                                <div class="mt-2 rounded-lg border border-danger-200 dark:border-danger-700 bg-danger-50 dark:bg-danger-950 p-4">
                                    <p class="text-sm font-semibold text-danger-800 dark:text-danger-200 mb-1">Withdraw {{ $enrolment->competitor?->full_name }}?</p>
                                    @if ($isPaidW && $withinCutoffW)
                                        <p class="text-xs text-danger-700 dark:text-danger-300 mb-3">A fee return of {{ tenant_money($enrolment->fee_calculated) }} will be created and the organisation will contact you to arrange the refund.</p>
                                    @elseif (!$isPaidW && !in_array($enrolment->competition?->status, ['open', 'planning']))
                                        <p class="text-xs text-danger-700 dark:text-danger-300 mb-3">Registration is closed — you will not be able to re-register after withdrawing.</p>
                                    @else
                                        <p class="text-xs text-danger-700 dark:text-danger-300 mb-3">This action cannot be undone.</p>
                                    @endif
                                    <div class="flex items-center gap-3">
                                        <x-filament::button color="danger" size="sm" wire:click="confirmWithdraw">Confirm withdrawal</x-filament::button>
                                        <button wire:click="cancelWithdraw" class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endforeach

                {{-- Cart total --}}
                @if ($cartTotal > 0 && $cartActive->isNotEmpty() && ! $cartIsPaid)
                    <div class="mt-4 pt-3 border-t border-gray-300 dark:border-gray-600 flex items-center justify-between">
                        <span class="text-sm font-bold text-gray-700 dark:text-gray-200">Total</span>
                        <span class="text-sm font-bold tabular-nums">{{ tenant_money($cartTotal) }}</span>
                    </div>
                @endif
            </x-filament::section>
        @endforeach

        @if ($carts->count() > 1)
            @php
                $allActive      = $carts->flatMap(fn ($c) => $c->enrolments->filter(fn ($e) => ! $e->trashed() && $e->status !== 'withdrawn'));
                $grandPaid        = $carts->filter(fn ($c) => $c->isPaid())->sum(fn ($c) => (float) ($c->payment_amount ?? $c->total_amount));
                $grandOutstanding = $carts->filter(fn ($c) => ! $c->isPaid())->sum(fn ($c) => (float) $c->total_amount);
            @endphp
            <x-filament::section>
                <div class="flex items-center justify-between gap-6 flex-wrap">
                    @if ($grandPaid > 0)
                        <div class="text-center">
                            <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Total paid</p>
                            <p class="text-lg font-bold text-success-600">{{ tenant_money($grandPaid) }}</p>
                        </div>
                    @endif
                    @if ($grandOutstanding > 0)
                        <div class="text-center">
                            <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Total outstanding</p>
                            <p class="text-lg font-bold text-warning-600">{{ tenant_money($grandOutstanding) }}</p>
                        </div>
                    @endif
                    <div class="text-center ml-auto">
                        <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Net balance</p>
                        @php $net = $grandOutstanding - $grandPaid; @endphp
                        <p class="text-lg font-bold {{ $net > 0 ? 'text-warning-600' : ($net < 0 ? 'text-danger-600' : 'text-success-600') }}">
                            {{ $net == 0 ? 'Settled' : tenant_money(abs($net)) . ($net < 0 ? ' refund due' : ' owing') }}
                        </p>
                    </div>
                </div>
            </x-filament::section>
        @endif
    @endif

</x-filament-panels::page>
