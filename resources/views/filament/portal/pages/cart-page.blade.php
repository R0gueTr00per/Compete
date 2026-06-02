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
            @php $comp = $items->first()['competition']; @endphp
            <x-filament::section class="mb-6">
                <x-slot name="heading">{{ $comp->name }}</x-slot>
                <x-slot name="description">{{ tenant_date($comp->competition_date) }}{{ $comp->location_name ? ' — ' . $comp->location_name : '' }}</x-slot>

                <div class="space-y-4">
                    @foreach ($items as $item)
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 relative">
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

                            <ul class="text-sm text-gray-600 dark:text-gray-400 mt-1.5 space-y-0.5">
                                @foreach ($item['enrolment']->activeEvents as $ee)
                                    <li>&#8226; {{ $ee->competitionEvent->name }}
                                        @if ($ee->division)
                                            <span class="text-xs">({{ $ee->division->full_label }})</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>

                            <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-800 space-y-1 text-xs">
                                @if ($item['is_official'])
                                    <p class="text-primary-600">Official rate applied</p>
                                @endif

                                <div class="flex justify-between text-gray-500 dark:text-gray-400">
                                    <span>Entry fee</span>
                                    <span>{{ tenant_money($item['base_fee']) }}</span>
                                </div>

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

        <x-filament::section>
            <div class="flex items-center justify-between">
                <p class="font-bold text-lg">Total</p>
                <p class="font-bold text-xl">{{ tenant_money($cartTotal['grand_total']) }}</p>
            </div>
            <p class="text-xs text-gray-400 mt-1">Payment is collected at the competition. An invoice will be emailed on submission.</p>
        </x-filament::section>

        <div class="mt-6 flex flex-wrap gap-3">
            <x-filament::button wire:click="submitCart" size="lg">Submit Registration</x-filament::button>
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
