<x-filament-panels::page>
    @php
        $platformFee = (float) (app('tenant')?->platform_fee ?? 0);
    @endphp

    {{-- Quick lookup --}}
    <div class="mb-6 rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 dark:border-primary-800 dark:bg-primary-950/30">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-primary-700 dark:text-primary-400">Scan competitor's QR code</p>

        <div
            x-data="qrScanner()"
            x-on:qr-scanned.window="$wire.set('code', $event.detail.code)"
            class="flex flex-col gap-2"
        >
            <div class="flex items-center gap-2">
                <div class="flex items-center rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 focus-within:ring-1 focus-within:ring-primary-500 flex-1">
                    <input
                        type="text"
                        wire:model.live.debounce.200ms="code"
                        placeholder="Enter code…"
                        inputmode="text"
                        autocomplete="off"
                        class="flex-1 bg-transparent py-1.5 pl-3 pr-1 text-base font-mono uppercase text-gray-900 dark:text-white border-0 focus:outline-none focus:ring-0 min-w-0"
                    />
                    @if ($this->code)
                        <button
                            wire:click="clearCode"
                            class="pr-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                            aria-label="Clear code"
                        >
                            <x-heroicon-m-x-mark class="h-4 w-4" />
                        </button>
                    @endif
                </div>
                <button
                    type="button"
                    x-on:click="scanning ? stopScan() : startScan()"
                    class="flex items-center gap-1.5 rounded-lg border border-primary-400 bg-primary-50 dark:bg-primary-950/50 dark:border-primary-700 px-3 py-1.5 text-xs font-semibold text-primary-700 dark:text-primary-300 hover:bg-primary-100 dark:hover:bg-primary-900/50 transition-colors shrink-0"
                >
                    <x-heroicon-m-qr-code class="h-4 w-4" />
                    <span x-text="scanning ? 'Stop' : 'Scan QR'"></span>
                </button>
            </div>

            <div x-show="scanning" x-cloak class="relative rounded-xl overflow-hidden bg-black aspect-video max-h-56">
                <video x-ref="video" playsinline muted class="w-full h-full object-cover"></video>
                <canvas x-ref="canvas" class="hidden"></canvas>
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                    <div class="w-40 h-40 border-2 border-white/60 rounded-xl"></div>
                </div>
            </div>
            <p x-show="scanning" x-cloak class="text-center text-xs text-gray-400 dark:text-gray-500">Point at competitor's QR code</p>

            <p x-show="error" x-text="error" class="text-xs text-danger-600 dark:text-danger-400"></p>
        </div>

        <div class="mt-3 pt-3 border-t border-primary-100 dark:border-primary-900">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-primary-700 dark:text-primary-400">Or search by name</p>
            <div class="flex items-center rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 focus-within:ring-1 focus-within:ring-primary-500">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search competitor name…"
                    inputmode="search"
                    enterkeyhint="search"
                    x-on:keydown.enter="$el.blur()"
                    class="flex-1 bg-transparent py-1.5 pl-3 pr-1 text-base text-gray-900 dark:text-white border-0 focus:outline-none focus:ring-0 min-w-0"
                />
                @if ($this->search)
                    <button
                        wire:click="$set('search', '')"
                        class="pr-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                        aria-label="Clear search"
                    >
                        <x-heroicon-m-x-mark class="h-4 w-4" />
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Confirm payment form(s) — shown inline once a person is found --}}
    @if ($this->viewingUserId)
        @php $viewCarts = $this->getViewingCarts(); @endphp
        @if ($viewCarts->isEmpty())
            <p class="text-center text-gray-400 py-8">No registrations found for this person in this organisation.</p>
        @else
            <div class="mb-2 flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    {{ $viewCarts->count() }} {{ Str::plural('registration', $viewCarts->count()) }} found
                </p>
                <button
                    type="button"
                    wire:click="closeAccount"
                    class="rounded-md p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                    <x-heroicon-m-x-mark class="w-5 h-5" />
                </button>
            </div>

            <div class="space-y-3">
                @foreach ($viewCarts as $viewCart)
                    @php
                        $vcPlatformFee = (float) ($viewCart->platform_fee_rate ?? $platformFee);
                        $vcTotal       = $viewCart->outstandingAmount($vcPlatformFee);
                    @endphp
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                            <p class="font-semibold text-gray-900 dark:text-white text-base">{{ $viewCart->enrolments->pluck('competitor.full_name')->filter()->unique()->join(', ') }}</p>
                            @if ($viewCart->competition)
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $viewCart->competition->name }} &middot; {{ tenant_date($viewCart->competition->competition_date) }}</p>
                            @endif
                        </div>

                        <div class="px-5 py-4 space-y-3">
                            <div class="divide-y divide-gray-100 dark:divide-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden text-sm">
                                @foreach ($viewCart->enrolments as $ve)
                                    <div class="flex justify-between px-4 py-2.5">
                                        <div>
                                            <p class="text-sm text-gray-800 dark:text-gray-200 font-medium">{{ $ve->competitor?->full_name ?? '—' }}</p>
                                            <p class="text-xs text-gray-400 mt-0.5">{{ $ve->activeEvents->pluck('competitionEvent.name')->join(', ') ?: '—' }}</p>
                                        </div>
                                        <span class="tabular-nums text-sm font-medium ml-4 shrink-0">
                                            {{ tenant_money((float) $ve->fee_calculated + $vcPlatformFee) }}
                                        </span>
                                    </div>
                                @endforeach
                                <div class="flex justify-between px-4 py-2.5 font-semibold bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white">
                                    <span>Total due</span>
                                    <span class="tabular-nums">{{ tenant_money($vcTotal) }}</span>
                                </div>
                            </div>
                        </div>

                        <div x-data="{ confirming: false }" class="border-t border-gray-200 dark:border-gray-700 px-5 py-4">
                            <div x-show="!confirming">
                                <x-filament::button
                                    x-on:click="confirming = true"
                                    color="success"
                                    class="w-full justify-center">
                                    Confirm payment received — {{ tenant_money($vcTotal) }}
                                </x-filament::button>
                            </div>
                            <div x-show="confirming" x-cloak class="space-y-2">
                                <p class="text-sm text-center text-gray-700 dark:text-gray-300">Mark <strong>{{ tenant_money($vcTotal) }}</strong> as received for {{ $viewCart->enrolments->pluck('competitor.full_name')->filter()->unique()->join(', ') }} ({{ $viewCart->competition?->name }})?</p>
                                <div class="flex gap-2">
                                    <x-filament::button
                                        x-on:click="confirming = false"
                                        color="gray"
                                        class="flex-1 justify-center">
                                        Cancel
                                    </x-filament::button>
                                    <x-filament::button
                                        wire:click="recordPayment({{ $viewCart->id }})"
                                        color="success"
                                        class="flex-1 justify-center">
                                        Yes, confirm
                                    </x-filament::button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @else
        {{-- Search results --}}
        @if (mb_strlen(trim($this->search)) >= 2)
            @php $results = $this->getSearchResults(); @endphp
            @if ($results->isEmpty())
                <p class="text-center text-gray-400 py-8">No competitors found with that name and an outstanding balance.</p>
            @else
                <div class="space-y-2">
                    @foreach ($results as $enrolment)
                        @php
                            $cart            = $enrolment->cart;
                            $enrolPlatformFee = (float) ($cart?->platform_fee_rate ?? $platformFee);
                            $outstanding     = $cart ? $cart->outstandingAmount($enrolPlatformFee) : 0.0;
                        @endphp
                        <button
                            type="button"
                            wire:click="viewAccount({{ $enrolment->id }})"
                            class="w-full flex items-center justify-between gap-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-4 py-3 text-left hover:border-gray-400 dark:hover:border-gray-500 transition-colors">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $enrolment->competitor?->full_name ?? '—' }}</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 truncate">
                                    {{ $enrolment->competition?->name }}
                                    @if ($enrolment->competition?->competition_date) &middot; {{ tenant_date($enrolment->competition->competition_date) }} @endif
                                    @if ($enrolment->dojo_name) &middot; {{ $enrolment->dojo_name }} @endif
                                    &middot; {{ tenant_money($outstanding) }} outstanding
                                </p>
                            </div>
                            <x-filament::badge color="warning" class="shrink-0">Collect payment</x-filament::badge>
                        </button>
                    @endforeach
                </div>
            @endif
        @endif
    @endif
</x-filament-panels::page>
