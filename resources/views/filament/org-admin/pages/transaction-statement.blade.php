<x-filament-panels::page>
    @php
        $entries      = $this->buildEntries();
        $totals       = $this->computeTotals($entries);
        $competitions = $this->getCompetitions();
    @endphp

    {{-- Filter bar --}}
    <div class="flex flex-wrap items-end gap-3 mb-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">

        {{-- Competition --}}
        <div class="flex flex-col gap-1 min-w-48">
            <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Competition</label>
            <select
                wire:model.live="competitionId"
                class="text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            >
                <option value="">All competitions</option>
                @foreach ($competitions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>

        {{-- Date from --}}
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-600 dark:text-gray-400">From</label>
            <input
                type="date"
                wire:model.live="dateFrom"
                class="text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            >
        </div>

        {{-- Date to --}}
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-600 dark:text-gray-400">To</label>
            <input
                type="date"
                wire:model.live="dateTo"
                class="text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            >
        </div>

        {{-- Type toggles --}}
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Type</label>
            <div class="flex gap-3 py-2">
                @foreach (['invoice' => 'Invoice', 'payment' => 'Payment', 'refund' => 'Refund'] as $value => $label)
                    <label class="flex items-center gap-1.5 text-sm text-gray-700 dark:text-gray-300 cursor-pointer select-none">
                        <input
                            type="checkbox"
                            wire:model.live="types"
                            value="{{ $value }}"
                            class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500"
                        >
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Search --}}
        <div class="flex flex-col gap-1 min-w-48 flex-1">
            <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Search</label>
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Name, email, competition…"
                class="text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            >
        </div>

        {{-- Export buttons --}}
        <div class="flex gap-2">
            <x-filament::button
                wire:click="downloadCsv"
                color="gray"
                size="sm"
                icon="heroicon-o-arrow-down-tray"
            >
                CSV
            </x-filament::button>
            <x-filament::button
                wire:click="downloadPdf"
                color="gray"
                size="sm"
                icon="heroicon-o-document-arrow-down"
            >
                PDF
            </x-filament::button>
        </div>
    </div>

    {{-- Ledger table --}}
    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Reference</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide hidden md:table-cell">Description</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Amount</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide hidden sm:table-cell">Balance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($entries as $entry)
                    @php
                        $typeMeta = match($entry['type']) {
                            'invoice' => ['label' => 'Invoice', 'bg' => 'bg-blue-100 dark:bg-blue-900/40',   'text' => 'text-blue-700 dark:text-blue-300'],
                            'payment' => ['label' => 'Payment', 'bg' => 'bg-green-100 dark:bg-green-900/40', 'text' => 'text-green-700 dark:text-green-300'],
                            'refund'  => ['label' => 'Refund',  'bg' => 'bg-red-100 dark:bg-red-900/40',     'text' => 'text-red-700 dark:text-red-300'],
                            default   => ['label' => ucfirst($entry['type']), 'bg' => 'bg-gray-100 dark:bg-gray-700', 'text' => 'text-gray-600 dark:text-gray-300'],
                        };
                        $amountPositive = $entry['amount'] >= 0;
                        // Positive balance = net cash received (good); negative = still outstanding (warning)
                        $balanceColor   = $entry['balance'] < -0.009
                            ? 'text-warning-600 dark:text-warning-400'
                            : ($entry['balance'] > 0.009 ? 'text-success-600 dark:text-success-400' : 'text-gray-400 dark:text-gray-500');
                    @endphp
                    <tr class="even:bg-gray-50/60 dark:even:bg-gray-800/40 hover:bg-primary-50/40 dark:hover:bg-primary-900/10 transition-colors">
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                            {{ tenant_date($entry['date']) }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $typeMeta['bg'] }} {{ $typeMeta['text'] }}">
                                {{ $typeMeta['label'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300 text-xs">
                            {{ $entry['reference'] }}
                        </td>
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs hidden md:table-cell max-w-xs truncate">
                            {{ $entry['description'] }}
                        </td>
                        <td class="px-4 py-3 text-right font-medium tabular-nums whitespace-nowrap {{ $amountPositive ? 'text-gray-900 dark:text-white' : 'text-danger-600 dark:text-danger-400' }}">
                            {{ $amountPositive ? '' : '−' }}{{ tenant_money(abs($entry['amount'])) }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap text-xs font-medium hidden sm:table-cell {{ $balanceColor }}">
                            {{ $entry['balance'] < 0 ? '−' : '' }}{{ tenant_money(abs($entry['balance'])) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-gray-400 dark:text-gray-500">
                            No transactions found for the selected filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Sticky footer totals --}}
    <div class="sticky bottom-0 z-10 -mx-4 sm:-mx-6 lg:-mx-8 mt-2 border-t border-gray-200 dark:border-gray-700 bg-white/95 dark:bg-gray-900/95 backdrop-blur-sm px-4 sm:px-6 lg:px-8 py-3">
        <div class="flex flex-wrap items-center gap-x-8 gap-y-1 text-sm">
            <div class="flex items-center gap-2">
                <span class="text-gray-500 dark:text-gray-400">Invoiced</span>
                <span class="font-semibold text-gray-900 dark:text-white">{{ tenant_money($totals['invoiced']) }}</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-gray-500 dark:text-gray-400">Payments</span>
                <span class="font-semibold text-success-600 dark:text-success-400">{{ tenant_money($totals['payments']) }}</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-gray-500 dark:text-gray-400">Refunds</span>
                <span class="font-semibold {{ $totals['refunds'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-400' }}">{{ tenant_money($totals['refunds']) }}</span>
            </div>
            <div class="h-4 w-px bg-gray-300 dark:bg-gray-600 hidden sm:block"></div>
            <div class="flex items-center gap-2">
                <span class="text-gray-500 dark:text-gray-400">Net balance</span>
                <span class="font-semibold tabular-nums
                    {{ $totals['net'] < -0.009
                        ? 'text-danger-600 dark:text-danger-400'
                        : ($totals['net'] > 0.009 ? 'text-success-600 dark:text-success-400' : 'text-gray-400 dark:text-gray-500') }}">
                    {{ $totals['net'] < 0 ? '−' : '' }}{{ tenant_money(abs($totals['net'])) }}
                </span>
            </div>
        </div>
    </div>
</x-filament-panels::page>
