<x-filament-panels::page>
    @php $cartTotal = $this->getCartTotal(); @endphp

    @if (empty($cartTotal['items']))
        <x-filament::section>
            <div class="py-8 text-center space-y-3">
                <x-heroicon-o-shopping-cart class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="text-gray-500">Your cart is empty.</p>
                <x-filament::button href="{{ route('filament.portal.pages.dashboard') }}" tag="a" color="gray">
                    Back to Dashboard
                </x-filament::button>
            </div>
        </x-filament::section>
    @else
        {{-- Group items by competition --}}
        @php
            $byCompetition = collect($cartTotal['items'])->groupBy(fn($item) => $item['competition']->id);
        @endphp

        @foreach ($byCompetition as $compId => $items)
            @php
                $comp = $items->first()['competition'];
                $enrolmentsClosed = ! $comp->isEnrolmentOpen();
            @endphp
            <x-filament::section class="mb-6 border-l-4 {{ $enrolmentsClosed ? 'border-l-danger-400 dark:border-l-danger-500' : 'border-l-primary-400 dark:border-l-primary-500' }}">
                <x-slot name="heading">
                    <span class="flex items-center gap-2 flex-wrap">
                        {{ $comp->name }}
                        @if ($enrolmentsClosed)
                            <span class="animate-pulse inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold text-white" style="background-color:#dc2626;">Enrolments Closed</span>
                        @endif
                    </span>
                </x-slot>
                <x-slot name="description">{{ tenant_date($comp->competition_date) }}{{ $comp->location_name ? ' — ' . $comp->location_name : '' }}</x-slot>

                <div class="space-y-4">
                    @foreach ($items as $item)
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 border-l-4 border-l-primary-400 dark:border-l-primary-500 p-4 relative">
                            {{-- Remove button --}}
                            <button
                                wire:click="startRemove({{ $item['enrolment']->id }})"
                                type="button"
                                class="absolute top-3 right-3 flex h-6 w-6 items-center justify-center rounded-full text-gray-400 hover:bg-danger-50 hover:text-danger-600 dark:hover:bg-danger-950 transition-colors"
                                title="Remove from cart"
                            >
                                <x-heroicon-s-x-mark class="h-4 w-4" />
                            </button>

                            <p class="font-semibold text-sm pr-8">{{ $item['profile']->full_name }}</p>

                            @php
                                $comp       = $item['competition'];
                                $isOfficial = $item['is_official'];
                                $firstFee   = ($isOfficial && $comp->fee_official_first_event)
                                    ? (float) $comp->fee_official_first_event
                                    : (float) $comp->fee_first_event;
                                $addFee     = ($isOfficial && $comp->fee_official_additional_event)
                                    ? (float) $comp->fee_official_additional_event
                                    : (float) $comp->fee_additional_event;
                                $events     = $item['enrolment']->activeEvents->sortBy('competitionEvent.running_order');
                            @endphp

                            <div class="mt-3 space-y-1 text-xs">
                                @if ($item['is_official'])
                                    <p class="text-primary-600 mb-1">Official rate applied</p>
                                @endif

                                @foreach ($events as $ee)
                                    <div class="flex items-center justify-between gap-2 text-gray-600 dark:text-gray-400">
                                        <span class="flex items-center gap-1.5 min-w-0">
                                            @if ($ee->division)
                                                <span class="shrink-0 font-mono text-[0.65rem] font-semibold px-1 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">{{ $ee->division->code }}</span>
                                            @endif
                                            <span>{{ $ee->competitionEvent->name }}@if ($ee->division) <span class="text-gray-400 dark:text-gray-500">&mdash; {{ $ee->division->label }}</span>@endif</span>
                                        </span>
                                        <span class="shrink-0 tabular-nums">{{ tenant_money($loop->first ? $firstFee : $addFee) }}</span>
                                    </div>
                                @endforeach
                            </div>

                            <div class="mt-2 pt-2 border-t border-gray-100 dark:border-gray-800 space-y-1 text-xs">

                                @if ($item['late_surcharge'] !== null)
                                    <div class="flex justify-between items-center text-warning-600">
                                        <span class="flex items-center gap-1">
                                            <x-heroicon-s-lock-closed class="h-3 w-3 shrink-0" />
                                            Late surcharge
                                        </span>
                                        <span>{{ tenant_money($item['late_surcharge']) }}</span>
                                    </div>
                                @endif

                                @if ($item['platform_fee'] > 0)
                                    <div class="flex justify-between items-center text-gray-500 dark:text-gray-400">
                                        <span class="flex items-center gap-1">
                                            <x-heroicon-s-lock-closed class="h-3 w-3 shrink-0" />
                                            Service fee
                                        </span>
                                        <span>{{ tenant_money($item['platform_fee']) }}</span>
                                    </div>
                                @endif

                                @if (($item['gst_amount'] ?? 0) > 0)
                                    <div class="flex justify-between items-center text-gray-500 dark:text-gray-400">
                                        <span>GST</span>
                                        <span>{{ tenant_money($item['gst_amount']) }}</span>
                                    </div>
                                @endif

                                <div class="flex justify-between font-semibold text-sm pt-1 border-t border-gray-100 dark:border-gray-800">
                                    <span>Subtotal</span>
                                    <span>{{ tenant_money($item['subtotal']) }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endforeach

        @php $totalGst = collect($cartTotal['items'])->sum('gst_amount'); @endphp
        <x-filament::section>
            <div class="flex items-center justify-between">
                <p class="font-bold text-lg">Total</p>
                <p class="font-bold text-xl">{{ tenant_money($cartTotal['grand_total']) }}</p>
            </div>
            @if ($totalGst > 0)
                <p class="text-xs text-gray-400 mt-1">Includes GST of {{ tenant_money($totalGst) }}</p>
            @endif
            <p class="text-xs text-gray-400 mt-1">Payment is collected at the competition. An invoice will be emailed on submission.</p>
        </x-filament::section>

        @php $hasClosedEnrolments = $this->hasClosedEnrolments(); @endphp

        @if ($hasClosedEnrolments)
            <div class="mt-4 rounded-lg bg-danger-50 dark:bg-danger-950 border border-danger-200 dark:border-danger-800 px-4 py-3 text-sm text-danger-700 dark:text-danger-400">
                Your cart contains items for competitions where enrolments are closed. Please remove these items before submitting.
            </div>
        @endif

        <div class="mt-4 flex flex-wrap gap-3">
            <x-filament::button wire:click="submitCart" size="lg" :disabled="$hasClosedEnrolments">Submit Registration</x-filament::button>
            <x-filament::button href="{{ route('filament.portal.pages.dashboard') }}" tag="a" color="gray" size="lg">
                Back to Dashboard
            </x-filament::button>
        </div>
    @endif

    {{-- Remove confirmation modal --}}
    @if ($this->removingId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
            <div class="w-full max-w-sm rounded-xl bg-white dark:bg-gray-900 shadow-xl p-6 space-y-4">
                <h3 class="text-lg font-semibold">Remove from cart?</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    This will remove the competitor from your cart. You can add them again from the dashboard.
                </p>
                <div class="flex gap-3 justify-end">
                    <x-filament::button wire:click="cancelRemove" color="gray">Cancel</x-filament::button>
                    <x-filament::button wire:click="confirmRemove" color="danger">Remove</x-filament::button>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
