<x-filament-panels::page>
    {{-- Navigate-away loading overlay --}}
    <div wire:loading.flex wire:target="leavePage"
         class="fixed inset-0 z-50 items-center justify-center bg-white/80 dark:bg-gray-900/80">
        <svg class="animate-spin h-8 w-8 text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
        </svg>
    </div>
    {{-- Back to list header --}}
    <div class="flex items-center gap-3 mb-3">
        <x-filament::button
            size="sm"
            color="gray"
            icon="heroicon-m-arrow-left"
            wire:click="leavePage">
            Back to scoring list
        </x-filament::button>
    </div>

    @php
        $ctxDiv = $this->division_id
            ? \App\Models\Division::with('competitionEvent')->find($this->division_id)
            : null;
    @endphp
    @if ($ctxDiv)
        <div class="mb-3 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 px-3 py-2.5">
            <span class="font-mono text-sm font-bold text-primary-600 dark:text-primary-400">{{ $ctxDiv->code }}</span>
            <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $ctxDiv->competitionEvent?->name }}</span>
            @if ($ctxDiv->label)
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $ctxDiv->label }}</span>
            @endif
        </div>
    @endif

    @if ($this->division_id)
        <livewire:org-admin.scoring-panel
            :division-id="$this->division_id"
            :competition-id="$this->competition_id"
            :key="'entry-panel-' . $this->division_id"
        />
    @endif
</x-filament-panels::page>
