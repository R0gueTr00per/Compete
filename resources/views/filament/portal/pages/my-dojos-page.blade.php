<x-filament-panels::page>
    @php
        $dojos        = $this->getDojos();
        $competitions = $this->getCompetitions();
        $org          = app('tenant');
        $platformFee  = (float) ($org?->platform_fee ?? 0);
    @endphp

    @forelse ($competitions as $competition)
        @php
            $dojoNames = $dojos->pluck('name');

            $enrolmentsByDojo = $competition->enrolments
                ->whereIn('dojo_name', $dojoNames)
                ->groupBy('dojo_name')
                ->map(fn ($group) => $group->sortBy(
                    fn ($e) => ($e->competitor?->first_name ?? '') . ' ' . ($e->competitor?->surname ?? '')
                ));

            $totalCompetitors = $enrolmentsByDojo->flatten()->count();

            // Unique unpaid carts for this competition (sum per cart, not per enrolment)
            $competitionOutstanding = $enrolmentsByDojo->flatten()
                ->unique('cart_id')
                ->filter(fn ($e) => $e->cart && ! $e->cart->isPaid())
                ->sum(fn ($e) => $e->cart->outstandingAmount(
                    (float) ($e->cart->platform_fee_rate ?? $platformFee)
                ));

            $statusClass = match($competition->status) {
                'open'              => 'bg-success-100 dark:bg-success-900/30 text-success-700 dark:text-success-400',
                'enrolments_closed' => 'bg-warning-100 dark:bg-warning-900/30 text-warning-700 dark:text-warning-400',
                'check_in'          => 'bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-400',
                'running'           => 'bg-danger-100 dark:bg-danger-900/30 text-danger-700 dark:text-danger-400',
                default             => 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300',
            };
        @endphp

        <div class="mb-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">

            {{-- Competition header --}}
            <div class="flex w-full items-center justify-between gap-3 px-4 py-3 border-b border-gray-100 dark:border-gray-800">
                <div class="min-w-0">
                    <span class="font-semibold text-sm text-gray-900 dark:text-white">{{ $competition->name }}</span>
                    <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">{{ tenant_date($competition->competition_date) }}</span>
                    <span class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $statusClass }}">
                        {{ match($competition->status) {
                            'enrolments_closed' => 'Registrations Closed',
                            'check_in'          => 'Check-in',
                            default             => ucfirst($competition->status),
                        } }}
                    </span>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    @if ($competitionOutstanding > 0)
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold bg-warning-100 dark:bg-warning-900/30 text-warning-700 dark:text-warning-400">
                            {{ tenant_money($competitionOutstanding) }} outstanding
                        </span>
                    @endif
                    <span class="text-xs text-gray-400 dark:text-gray-500">{{ $totalCompetitors }} {{ Str::plural('competitor', $totalCompetitors) }}</span>
                </div>
            </div>

            @foreach ($enrolmentsByDojo as $dojoName => $enrolments)
                {{-- Dojo section — expanded by default --}}
                <div x-data="{ open: true }" class="border-b border-gray-100 dark:border-gray-800 last:border-b-0">

                    <button
                        type="button"
                        x-on:click="open = !open"
                        class="flex w-full items-center justify-between gap-2 px-4 py-2.5 text-left bg-gray-50 dark:bg-gray-800/60 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $dojoName }}</span>
                        <div class="flex items-center gap-3 shrink-0">
                            @php
                                $paidCount = $enrolments->filter(fn ($e) => $e->cart?->isPaid())->count();
                                $total     = $enrolments->count();
                            @endphp
                            <span class="text-xs text-gray-400 dark:text-gray-500">
                                {{ $paidCount }}/{{ $total }} paid
                            </span>
                            <x-heroicon-m-chevron-down x-bind:class="open ? 'rotate-180' : ''" class="h-3.5 w-3.5 text-gray-400 transition-transform" />
                        </div>
                    </button>

                    <div x-show="open" x-collapse>
                        <div class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($enrolments as $enrolment)
                                @php
                                    $name                  = $enrolment->competitor?->full_name ?? '—';
                                    $eventCount            = $enrolment->activeEvents->count();
                                    $isPaid                = $enrolment->cart?->isPaid();
                                    $isOfficial            = $enrolment->is_official_discount;
                                    $enrolmentCart         = $enrolment->cart;
                                    $enrolmentPlatformFee  = (float) ($enrolmentCart?->platform_fee_rate ?? $platformFee);
                                    $cartOutstanding       = $enrolmentCart ? $enrolmentCart->outstandingAmount($enrolmentPlatformFee) : 0.0;
                                    $cartEnrolmentCount    = $enrolmentCart ? $enrolmentCart->enrolments->whereNotIn('status', ['withdrawn', 'draft'])->count() : 1;
                                    $firstRate             = $isOfficial && ($enrolmentCart?->fee_official_first_rate ?? $competition->fee_official_first_event) !== null
                                        ? (float) ($enrolmentCart?->fee_official_first_rate ?? $competition->fee_official_first_event)
                                        : (float) ($enrolmentCart?->fee_first_rate ?? $competition->fee_first_event);
                                    $addRate               = $isOfficial && ($enrolmentCart?->fee_official_additional_rate ?? $competition->fee_official_additional_event) !== null
                                        ? (float) ($enrolmentCart?->fee_official_additional_rate ?? $competition->fee_official_additional_event)
                                        : (float) ($enrolmentCart?->fee_additional_rate ?? $competition->fee_additional_event);
                                    $lateSurchargeRate     = (float) ($enrolmentCart?->late_surcharge_rate ?? $competition->late_surcharge ?? 0);
                                @endphp

                                <div x-data="{ open: false }" class="px-4 py-3">

                                    {{-- Summary row — always visible --}}
                                    <div class="flex w-full items-center justify-between gap-3">
                                        <button
                                            type="button"
                                            x-on:click="open = !open"
                                            class="flex flex-1 min-w-0 items-center gap-3 text-left">
                                            <span class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate">{{ $name }}</span>
                                            <div class="flex items-center gap-2 shrink-0">
                                                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $eventCount }} {{ Str::plural('event', $eventCount) }}</span>
                                                <x-heroicon-m-chevron-down x-bind:class="open ? 'rotate-180' : ''" class="h-4 w-4 text-gray-400 transition-transform" />
                                            </div>
                                        </button>
                                        @if ($isPaid)
                                            <x-filament::badge color="success" class="shrink-0">Paid</x-filament::badge>
                                        @elseif (app('tenant')?->instructorsCanAcceptPayments())
                                            <x-filament::button
                                                href="{{ \App\Filament\Portal\Pages\AcceptPaymentPage::getUrl(['code' => $enrolment->checkin_code]) }}"
                                                tag="a" color="warning" size="xs"
                                                class="shrink-0">
                                                Accept payment
                                            </x-filament::button>
                                        @endif
                                    </div>

                                    {{-- Expandable detail --}}
                                    <div x-show="open" x-collapse>
                                        <div class="mt-3 space-y-1">

                                            {{-- Per-event lines --}}
                                            @foreach ($enrolment->activeEvents as $ee)
                                                <div class="flex justify-between text-xs text-gray-600 dark:text-gray-400">
                                                    <span>
                                                        {{ $ee->competitionEvent->name }}@if ($ee->division)<span class="text-gray-400"> &middot; {{ $ee->division->code }} &mdash; {{ $ee->division->label }}</span>@endif
                                                        @if ($loop->first && $isOfficial)
                                                            <span class="ml-1 text-gray-400">(official rate)</span>
                                                        @endif
                                                    </span>
                                                    <span class="tabular-nums ml-4">{{ tenant_money($loop->first ? $firstRate : $addRate) }}</span>
                                                </div>
                                            @endforeach

                                            {{-- Late surcharge --}}
                                            @if ($enrolment->is_late && $lateSurchargeRate)
                                                <div class="flex justify-between text-xs text-warning-600">
                                                    <span>Late surcharge</span>
                                                    <span class="tabular-nums ml-4">{{ tenant_money($lateSurchargeRate) }}</span>
                                                </div>
                                            @endif

                                            {{-- Platform fee --}}
                                            @if ($enrolmentPlatformFee > 0)
                                                <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                                                    <span>Platform fee</span>
                                                    <span class="tabular-nums ml-4">{{ tenant_money($enrolmentPlatformFee) }}</span>
                                                </div>
                                            @endif

                                            {{-- Total line --}}
                                            <div class="flex justify-between text-xs pt-2 mt-1 border-t border-gray-100 dark:border-gray-800 font-semibold text-gray-900 dark:text-white">
                                                <span>This competitor</span>
                                                <span class="tabular-nums ml-4">{{ tenant_money((float) $enrolment->fee_calculated + $enrolmentPlatformFee) }}</span>
                                            </div>

                                            {{-- Payment status --}}
                                            @if ($isPaid)
                                                <p class="text-xs text-success-600 pt-1">
                                                    ✓ Paid {{ tenant_money($enrolment->cart?->payment_amount ?? $cartOutstanding) }}
                                                    @if ($enrolment->cart?->payment_received_at)
                                                        on {{ tenant_date($enrolment->cart->payment_received_at) }}
                                                    @endif
                                                </p>
                                            @endif

                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach

        </div>
    @empty
        <x-filament::section>
            <p class="text-sm text-center text-gray-500 py-4">No active competitions with registrations from your {{ strtolower($dojos->count() > 1 ? tenant_group_name_plural() : tenant_group_name()) }}.</p>
        </x-filament::section>
    @endforelse
</x-filament-panels::page>
