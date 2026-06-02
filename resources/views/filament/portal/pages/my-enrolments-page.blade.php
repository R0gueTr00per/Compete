<x-filament-panels::page>
    @php
        $enrolments = $this->getEnrolments();
        $draftCart  = $this->getDraftCart();
    @endphp

    {{-- Draft cart resume banner --}}
    @if ($draftCart)
        <x-filament::section class="mb-6 border border-primary-200 dark:border-primary-700 bg-primary-50 dark:bg-primary-950">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="font-semibold text-sm text-primary-800 dark:text-primary-200">Incomplete enrolment</p>
                    <p class="text-sm text-primary-700 dark:text-primary-300 mt-0.5">
                        You have an unfinished enrolment for <strong>{{ $draftCart->competition->name }}</strong>.
                    </p>
                </div>
                <x-filament::button href="{{ route('filament.portal.pages.enrol') }}" tag="a" size="sm">
                    Resume
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif

    @if ($enrolments->isEmpty())
        <x-filament::section>
            <p class="text-center text-gray-500 py-8">You have not enrolled in any competitions yet.</p>
            <div class="flex justify-center mt-2">
                <x-filament::button href="{{ route('filament.portal.pages.enrol') }}" tag="a">
                    Enrol now
                </x-filament::button>
            </div>
        </x-filament::section>
    @else
        @foreach ($enrolments as $enrolment)
            <x-filament::section class="mb-6">
                <x-slot name="heading">
                    {{ $enrolment->competition->name }}
                    @if ($enrolment->status === 'withdrawn')
                        <span class="ml-2 text-xs font-normal text-danger-600">(Withdrawn)</span>
                    @endif
                </x-slot>

                <x-slot name="description">
                    {{ tenant_date($enrolment->competition->competition_date) }}
                    @if ($enrolment->competition->location_name)
                        &mdash; {{ $enrolment->competition->location_name }}
                    @endif
                    @if ($enrolment->status !== 'withdrawn')
                        &nbsp;&bull;&nbsp;
                        Fee: <strong>{{ tenant_money($enrolment->fee_calculated) }}</strong>
                        @if ($enrolment->is_late)
                            <span class="text-warning-600">(includes late surcharge)</span>
                        @endif
                        @if ($enrolment->is_official_discount)
                            <span class="text-primary-600">(official rate)</span>
                        @endif
                    @endif
                </x-slot>

                @if ($enrolment->status !== 'withdrawn')
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($enrolment->activeEvents as $ee)
                            <div class="py-3">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="font-medium text-sm">
                                            {{ $ee->competitionEvent->name }}
                                            @if ($ee->competitionEvent->location_label)
                                                <span class="text-gray-400 font-normal">({{ $ee->competitionEvent->location_label }})</span>
                                            @endif
                                        </p>
                                        @if ($ee->division)
                                            <p class="text-xs text-gray-500 mt-0.5">{{ $ee->division->full_label }}</p>
                                        @endif
                                        @if ($ee->competitionEvent->requires_partner)
                                            <p class="text-xs mt-0.5 {{ $ee->yakusuko_complete ? 'text-success-600' : 'text-warning-600' }}">
                                                Partner: {{ $ee->yakusuko_complete ? 'Confirmed' : 'Pending partner enrolment' }}
                                            </p>
                                        @endif
                                    </div>

                                    <div class="text-right text-sm shrink-0">
                                        @if ($ee->result)
                                            @if ($ee->result->placement)
                                                <span class="font-bold text-primary-600">
                                                    @switch($ee->result->placement)
                                                        @case(1) 🥇 1st @break
                                                        @case(2) 🥈 2nd @break
                                                        @case(3) 🥉 3rd @break
                                                        @default {{ $ee->result->placement }}th
                                                    @endswitch
                                                </span>
                                            @endif
                                            @if ($ee->result->total_score)
                                                <p class="text-gray-500 text-xs">Score: {{ number_format((float) $ee->result->total_score, 2) }}</p>
                                            @endif
                                            @if ($ee->result->win_loss)
                                                <p class="text-xs {{ $ee->result->win_loss === 'win' ? 'text-success-600' : 'text-danger-600' }}">
                                                    {{ ucfirst($ee->result->win_loss) }}
                                                </p>
                                            @endif
                                        @else
                                            <span class="text-gray-400 text-xs">Result pending</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Actions --}}
                    @if ($this->canEditEvents($enrolment) || $this->canWithdraw($enrolment))
                        <div class="mt-4 flex gap-2 border-t border-gray-100 dark:border-gray-800 pt-4">
                            @if ($this->canEditEvents($enrolment))
                                <x-filament::button
                                    wire:click="startEdit({{ $enrolment->id }})"
                                    size="sm"
                                    color="gray"
                                    outlined
                                >
                                    Edit Events
                                </x-filament::button>
                            @endif
                            @if ($this->canWithdraw($enrolment))
                                <x-filament::button
                                    wire:click="startWithdraw({{ $enrolment->id }})"
                                    size="sm"
                                    color="danger"
                                    outlined
                                >
                                    Withdraw
                                </x-filament::button>
                            @endif
                        </div>
                    @endif
                @else
                    {{-- Withdrawn state --}}
                    <p class="text-sm text-gray-400 py-2">
                        Withdrawn {{ tenant_date($enrolment->withdrawn_at) }}
                        @if ($enrolment->withdrawal_reason)
                            &mdash; {{ $enrolment->withdrawal_reason }}
                        @endif
                        @if ($enrolment->refund_requested)
                            <span class="text-warning-600">&bull; Refund requested &mdash; contact the organiser.</span>
                        @endif
                    </p>
                @endif
            </x-filament::section>
        @endforeach
    @endif

    {{-- ── Withdrawal confirmation modal ──────────────────────────────────── --}}
    @if ($this->withdrawingId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
            <div class="w-full max-w-md rounded-xl bg-white dark:bg-gray-900 shadow-xl p-6 space-y-4">
                <h3 class="text-lg font-semibold">Confirm Withdrawal</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Are you sure you want to withdraw from this competition? This cannot be undone.
                    @php $we = $enrolments->firstWhere('id', $this->withdrawingId); @endphp
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
                    <x-filament::button wire:click="cancelWithdraw" color="gray">
                        Cancel
                    </x-filament::button>
                    <x-filament::button wire:click="confirmWithdraw" color="danger">
                        Confirm Withdrawal
                    </x-filament::button>
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
                    <x-filament::button wire:click="cancelEdit" color="gray">
                        Cancel
                    </x-filament::button>
                    <x-filament::button wire:click="saveEdit" :disabled="empty($editOptions)">
                        Save Changes
                    </x-filament::button>
                </div>
            </div>
        </div>
    @endif

</x-filament-panels::page>
