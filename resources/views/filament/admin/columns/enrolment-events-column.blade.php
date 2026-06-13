@php
    $record       = $getRecord();
    $count        = $record->activeEvents->count();
    $removedCount = ($record->enrolmentEvents ?? collect())->count();
@endphp

<button
    wire:click="mountTableAction('viewEvents', {{ $record->getKey() }})"
    type="button"
    class="flex items-center gap-1.5 text-left group"
>
    <x-heroicon-o-chevron-right class="h-3.5 w-3.5 text-gray-400 group-hover:text-primary-500 transition-colors flex-shrink-0" />
    <span class="text-sm text-gray-700 dark:text-gray-300 group-hover:text-primary-600 group-hover:underline">
        {{ $count }} {{ Str::plural('event', $count) }}
        @if ($removedCount > 0)
            <span class="text-xs text-gray-400 no-underline">(+{{ $removedCount }} removed)</span>
        @endif
    </span>
</button>
