@php
    $bgStyle = match(true) {
        $div->status === 'complete'                       => 'background-color:#bbf7d0;border-color:#9ca3af;',
        $div->active_enrolment_events_count >= 2          => 'background-color:#c7d2fe;border-color:#9ca3af;',
        $div->location_label !== null                     => 'background-color:#fde68a;border-color:#9ca3af;',
        default                                           => 'background-color:#ffffff;border-color:#9ca3af;',
    };
    $checkedIn  = $div->checked_in_count ?? 0;
    $enrolled   = $div->active_enrolment_events_count ?? 0;
    $noneShowed = $enrolled > 0 && $checkedIn === 0 && $div->status !== 'complete';
@endphp
<div
    data-id="{{ $div->id }}"
    @if($div->status === 'complete') data-scored="true" @endif
    style="{{ $bgStyle }}"
    class="group relative mb-1.5 rounded-md border px-3 py-2 shadow-sm
        {{ $div->status === 'cancelled' ? 'opacity-50' : '' }}
        {{ $div->status === 'complete' ? 'cursor-default' : 'cursor-grab hover:shadow-md' }}
        border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900"
>
    <div class="flex items-center justify-between gap-2">
        <div class="flex items-center gap-2 min-w-0">
            <span class="font-mono text-xs font-bold shrink-0 text-gray-900 dark:text-white">
                {{ $div->code }}
            </span>
            <span class="text-xs truncate text-gray-500 dark:text-gray-400">
                {{ $div->competitionEvent->name }}
            </span>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            @if($div->status === 'complete')
                <x-heroicon-m-check-circle class="h-4 w-4 text-success-500" />
            @else
                @if($enrolled > 0)
                    <span class="flex items-center gap-0.5 text-xs text-gray-500 tabular-nums">
                        {{ $enrolled }}<x-heroicon-m-user class="h-3 w-3 text-gray-400" />
                    </span>
                @endif
                @if($checkedIn > 0)
                    <span class="flex items-center gap-0.5 text-xs text-success-600 tabular-nums">
                        {{ $checkedIn }}<x-heroicon-m-check class="h-3 w-3 text-success-500" />
                    </span>
                @elseif($noneShowed)
                    <span class="flex items-center gap-0.5 text-xs text-warning-600 tabular-nums">
                        0<x-heroicon-m-check class="h-3 w-3 text-warning-500" />
                    </span>
                @endif
            @endif
        </div>
    </div>

    <div class="text-xs mt-0.5 truncate text-gray-600 dark:text-gray-400">
        {{ $div->label }}
    </div>

    @if($div->status !== 'complete')
        <div class="absolute right-1.5 top-1.5 hidden gap-1 group-hover:flex">
            @if($div->status !== 'cancelled')
                <button
                    wire:click="cancelDivision({{ $div->id }})"
                    wire:confirm="Cancel this division?"
                    title="Cancel"
                    class="rounded p-0.5 text-gray-400 hover:bg-red-100 hover:text-red-600 dark:hover:bg-red-900/40 dark:hover:text-red-400"
                >
                    <x-heroicon-m-x-circle class="h-4 w-4" />
                </button>
            @else
                <button
                    wire:click="reinstateDivision({{ $div->id }})"
                    title="Reinstate"
                    class="rounded p-0.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                >
                    <x-heroicon-m-arrow-path class="h-4 w-4" />
                </button>
            @endif
        </div>
    @endif
</div>
