@php $scored = $div->status === 'complete'; @endphp
<div
    class="group relative mb-2 rounded-lg border p-3 shadow-sm
           {{ $scored
               ? 'cursor-default border-success-300 bg-success-50 dark:border-success-700 dark:bg-success-900/20'
               : 'cursor-grab border-gray-200 bg-white hover:border-primary-300 hover:shadow-md active:cursor-grabbing dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-600'
           }}
           {{ $div->status === 'cancelled' ? 'opacity-50' : '' }}"
    data-id="{{ $div->id }}"
    @if($scored) data-scored="true" @endif
>
    <div class="flex items-start justify-between gap-2">
        <span class="font-mono text-sm font-bold {{ $scored ? 'text-success-800 dark:text-success-300' : 'text-gray-900 dark:text-white' }}">
            {{ $div->code }}
        </span>
        @if($scored)
            <x-heroicon-m-check-circle class="h-4 w-4 text-success-500 shrink-0 mt-0.5" />
        @endif
    </div>

    <div class="mt-1 text-xs text-gray-400 dark:text-gray-500">
        {{ $div->competitionEvent->eventType->name }}
    </div>

    <div class="mt-0.5 text-sm {{ $scored ? 'text-success-700 dark:text-success-400' : 'text-gray-700 dark:text-gray-300' }}">
        {{ $div->label }}
    </div>

    {{-- Card actions (visible on hover, only for non-scored) --}}
    @if(! $scored)
        <div class="absolute right-2 top-2 hidden gap-1 group-hover:flex">
            @if($div->status !== 'cancelled')
                <button
                    wire:click="cancelDivision({{ $div->id }})"
                    wire:confirm="Cancel this division?"
                    title="Cancel"
                    class="rounded p-0.5 text-gray-400 hover:bg-red-100 hover:text-red-600
                           dark:hover:bg-red-900/40 dark:hover:text-red-400"
                >
                    <x-heroicon-m-x-circle class="h-4 w-4" />
                </button>
            @else
                <button
                    wire:click="reinstateDivision({{ $div->id }})"
                    title="Reinstate"
                    class="rounded p-0.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600
                           dark:hover:bg-gray-700 dark:hover:text-gray-300"
                >
                    <x-heroicon-m-arrow-path class="h-4 w-4" />
                </button>
            @endif
        </div>
    @endif
</div>
