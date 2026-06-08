<x-filament-panels::page>
    {{-- Navigate-away loading overlay --}}
    <div wire:loading.flex wire:target="leavePage"
         class="fixed inset-0 z-50 items-center justify-center bg-white/80 dark:bg-slate-900/80">
        <svg class="animate-spin h-8 w-8 text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
        </svg>
    </div>
    {{-- Back to list header --}}
    <div class="flex items-center gap-3 mb-4">
        <x-filament::button
            size="sm"
            color="gray"
            icon="heroicon-m-arrow-left"
            wire:click="leavePage">
            Back to scoring list
        </x-filament::button>
    </div>

    @if ($this->division_id)
        <livewire:org-admin.scoring-panel
            :division-id="$this->division_id"
            :competition-id="$this->competition_id"
            :key="'entry-panel-' . $this->division_id"
        />
    @endif
</x-filament-panels::page>
