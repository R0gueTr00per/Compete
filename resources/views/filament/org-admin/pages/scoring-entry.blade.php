<x-filament-panels::page>
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
