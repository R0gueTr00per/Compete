<x-filament-panels::page>
    @php $enrolments = $this->getEnrolments(); @endphp

    @if ($enrolments->isEmpty())
        <x-filament::section>
            <p class="text-center text-gray-500 py-8">No pending refund requests.</p>
        </x-filament::section>
    @else
        <x-filament::section>
            <p class="text-sm text-gray-500 mb-4">
                These competitors withdrew after a payment had been recorded. Review each request and mark as resolved once the refund has been processed.
            </p>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700 text-left">
                            <th class="pb-3 font-semibold text-gray-600 dark:text-gray-400">Competitor</th>
                            <th class="pb-3 font-semibold text-gray-600 dark:text-gray-400">Competition</th>
                            <th class="pb-3 font-semibold text-gray-600 dark:text-gray-400">Fee paid</th>
                            <th class="pb-3 font-semibold text-gray-600 dark:text-gray-400">Withdrawn</th>
                            <th class="pb-3 font-semibold text-gray-600 dark:text-gray-400">Reason</th>
                            <th class="pb-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($enrolments as $enrolment)
                            <tr>
                                <td class="py-3 pr-4 font-medium">{{ $enrolment->competitor->full_name }}</td>
                                <td class="py-3 pr-4">{{ $enrolment->competition->name }}</td>
                                <td class="py-3 pr-4">
                                    @if ($enrolment->payment_amount)
                                        {{ tenant_money($enrolment->payment_amount) }}
                                    @else
                                        <span class="text-gray-400">{{ tenant_money($enrolment->fee_calculated) }} <span class="text-xs">(calc.)</span></span>
                                    @endif
                                </td>
                                <td class="py-3 pr-4 text-gray-500">
                                    {{ tenant_date($enrolment->withdrawn_at) }}
                                </td>
                                <td class="py-3 pr-4 text-gray-500 max-w-xs truncate">
                                    {{ $enrolment->withdrawal_reason ?: '—' }}
                                </td>
                                <td class="py-3">
                                    <x-filament::button
                                        wire:click="markResolved({{ $enrolment->id }})"
                                        size="xs"
                                        color="success"
                                        outlined
                                    >
                                        Mark resolved
                                    </x-filament::button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
