<x-filament-panels::page>
    @php
        $transactions = $this->getTransactions();
        $draftCart    = $this->getDraftCart();
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
                <x-filament::button href="{{ route('filament.portal.pages.enrol') }}" tag="a" size="sm">
                    Resume
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif

    @if ($transactions->isEmpty())
        <x-filament::section>
            <p class="text-center text-gray-500 py-8">You have not registered in any competitions yet.</p>
            <div class="flex justify-center mt-2">
                <x-filament::button href="{{ route('filament.portal.pages.enrol') }}" tag="a">
                    Register now
                </x-filament::button>
            </div>
        </x-filament::section>
    @else
        @foreach ($transactions as $cartKey => $enrolments)
            @php
                $first      = $enrolments->first();
                $comp       = $first->competition;
                $cartTotal  = $enrolments->whereNotIn('status', ['withdrawn'])->sum('fee_calculated');
                $allStatuses = $enrolments->pluck('payment_status')->unique();
                $paidCount   = $enrolments->where('payment_status', 'received')->count();
                $totalCount  = $enrolments->whereNotIn('status', ['withdrawn'])->count();
                $paymentLabel = $totalCount === 0 ? null
                    : ($paidCount === $totalCount ? 'Paid' : ($paidCount > 0 ? 'Partial' : 'Outstanding'));
                $paymentColor = match($paymentLabel) {
                    'Paid'        => 'success',
                    'Partial'     => 'warning',
                    'Outstanding' => 'warning',
                    default       => 'gray',
                };
            @endphp
            <x-filament::section class="mb-6">
                <x-slot name="heading">
                    {{ $comp->name }}
                </x-slot>
                <x-slot name="description">
                    {{ tenant_date($comp->competition_date) }}
                    @if ($comp->location_name)
                        &mdash; {{ $comp->location_name }}
                    @endif
                </x-slot>

                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($enrolments as $enrolment)
                        <div class="py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-semibold text-sm">{{ $enrolment->competitor?->full_name }}</p>

                                    @if ($enrolment->status === 'withdrawn')
                                        <p class="text-xs text-danger-600 mt-0.5">
                                            Withdrawn{{ $enrolment->withdrawn_at ? ' ' . tenant_date($enrolment->withdrawn_at) : '' }}
                                            @if ($enrolment->withdrawal_reason)
                                                &mdash; {{ $enrolment->withdrawal_reason }}
                                            @endif
                                            @if ($enrolment->refund_requested)
                                                &bull; <span class="text-warning-600">Refund requested</span>
                                            @endif
                                        </p>
                                    @else
                                        <div class="mt-1 space-y-0.5">
                                            @foreach ($enrolment->activeEvents as $ee)
                                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $ee->competitionEvent->name }}
                                                    @if ($ee->division)
                                                        <span class="text-gray-400">({{ $ee->division->full_label }})</span>
                                                    @endif
                                                    @if ($ee->result?->placement)
                                                        &mdash;
                                                        <span class="font-medium text-primary-600">
                                                            @switch($ee->result->placement)
                                                                @case(1) 1st @break
                                                                @case(2) 2nd @break
                                                                @case(3) 3rd @break
                                                                @default {{ $ee->result->placement }}th
                                                            @endswitch
                                                        </span>
                                                    @endif
                                                </p>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                @if ($enrolment->status !== 'withdrawn')
                                    <div class="text-right shrink-0 space-y-1">
                                        <p class="text-sm font-semibold">{{ tenant_money($enrolment->fee_calculated) }}</p>
                                        @if ($enrolment->is_late)
                                            <p class="text-xs text-warning-600">incl. late surcharge</p>
                                        @endif
                                        <x-filament::badge :color="$enrolment->payment_status === 'received' ? 'success' : 'warning'" size="sm">
                                            {{ $enrolment->payment_status === 'received' ? 'Paid' : 'Outstanding' }}
                                        </x-filament::badge>
                                    </div>
                                @endif
                            </div>

                            {{-- Per-enrolment actions --}}
                            @if ($enrolment->status !== 'withdrawn' && ($this->canEditEvents($enrolment) || $this->canWithdraw($enrolment)))
                                <div class="mt-2 flex gap-2">
                                    @if ($this->canEditEvents($enrolment))
                                        <x-filament::button wire:click="startEdit({{ $enrolment->id }})" size="xs" color="gray" outlined>
                                            Edit Events
                                        </x-filament::button>
                                    @endif
                                    @if ($this->canWithdraw($enrolment))
                                        <x-filament::button wire:click="startWithdraw({{ $enrolment->id }})" size="xs" color="danger" outlined>
                                            Withdraw
                                        </x-filament::button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if ($cartTotal > 0)
                    <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Total</span>
                        <span class="text-sm font-bold">{{ tenant_money($cartTotal) }}</span>
                    </div>
                @endif
            </x-filament::section>
        @endforeach
    @endif

    {{-- ── Withdrawal confirmation modal ──────────────────────────────────── --}}
    @if ($this->withdrawingId)
        @php $allEnrolments = $transactions->flatten(); @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
            <div class="w-full max-w-md rounded-xl bg-white dark:bg-gray-900 shadow-xl p-6 space-y-4">
                <h3 class="text-lg font-semibold">Confirm Withdrawal</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Are you sure you want to withdraw from this competition? This cannot be undone.
                    @php $we = $allEnrolments->firstWhere('id', $this->withdrawingId); @endphp
                    @if ($we && $we->payment_status === 'received')
                        <br><span class="text-warning-600 font-medium">You have a payment recorded. A refund request will be raised for the organiser.</span>
                    @endif
                </p>
                <div>
                    <label class="block text-sm font-medium mb-1">Reason (optional)</label>
                    <textarea
                        wire:model.live="withdrawalReason"
                        rows="3"
                        placeholder="Reason for withdrawing..."
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                    ></textarea>
                </div>
                <div class="flex gap-3 justify-end">
                    <x-filament::button wire:click="cancelWithdraw" color="gray">Cancel</x-filament::button>
                    <x-filament::button wire:click="confirmWithdraw" color="danger">Confirm Withdrawal</x-filament::button>
                </div>
            </div>
        </div>
    @endif

    {{-- ── Edit events modal ────────────────────────────────────────────────── --}}
    @if ($this->editingId)
        @php $editOptions = $this->getAvailableEventsForEdit(); @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
            <div class="w-full max-w-lg rounded-xl bg-white dark:bg-gray-900 shadow-xl p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <h3 class="text-lg font-semibold">Edit Events</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">Select the events you want to enter. Your fee will be recalculated.</p>

                @if (empty($editOptions))
                    <p class="text-sm text-warning-600">No eligible divisions found.</p>
                @else
                    <div class="space-y-2">
                        @foreach ($editOptions as $key => $label)
                            <label class="flex items-start gap-3 rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2.5 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800">
                                <input
                                    type="checkbox"
                                    value="{{ $key }}"
                                    wire:model.live="editingEntries"
                                    class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                />
                                <span class="text-sm">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif

                <div class="flex gap-3 justify-end pt-2 border-t border-gray-100 dark:border-gray-800">
                    <x-filament::button wire:click="cancelEdit" color="gray">Cancel</x-filament::button>
                    <x-filament::button wire:click="saveEdit" :disabled="empty($editOptions)">Save Changes</x-filament::button>
                </div>
            </div>
        </div>
    @endif

</x-filament-panels::page>
