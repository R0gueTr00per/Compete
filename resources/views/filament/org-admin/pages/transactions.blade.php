<x-filament-panels::page>
    {{ $this->table }}

    @php $totals = $this->getTotals(); @endphp
    <div class="sticky bottom-0 z-10 -mx-4 sm:-mx-6 lg:-mx-8 mt-2 border-t border-gray-200 dark:border-gray-700 bg-white/95 dark:bg-gray-900/95 backdrop-blur-sm px-4 sm:px-6 lg:px-8 py-3">
        <div class="flex flex-wrap items-center gap-x-8 gap-y-1 text-sm">
            <div class="flex items-center gap-2">
                <span class="text-gray-500 dark:text-gray-400">Total fees</span>
                <span class="font-semibold text-gray-900 dark:text-white">{{ tenant_money($totals['total_fees']) }}</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-gray-500 dark:text-gray-400">Paid</span>
                <span class="font-semibold text-success-600 dark:text-success-400">{{ tenant_money($totals['total_paid']) }}</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-gray-500 dark:text-gray-400">Outstanding</span>
                <span class="font-semibold {{ $totals['outstanding'] > 0 ? 'text-warning-600 dark:text-warning-400' : 'text-gray-400' }}">
                    {{ tenant_money($totals['outstanding']) }}
                </span>
            </div>
        </div>
    </div>
</x-filament-panels::page>
