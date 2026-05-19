<div
    data-id="{{ $official->id }}"
    class="group relative mb-1.5 rounded-md border shadow-sm transition-colors cursor-grab
           bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-600
           hover:shadow-md hover:border-gray-300 dark:hover:border-gray-500"
>
    <div class="px-3 py-2">
        <div class="flex items-center justify-between gap-2">
            <div class="min-w-0">
                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                    {{ $official->user?->getFilamentName() ?? '—' }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                    {{ $official->officialRole?->name ?? '—' }}
                </p>
            </div>
            <button
                type="button"
                title="Remove official"
                wire:click="removeOfficial({{ $official->id }})"
                wire:confirm="Remove {{ $official->user?->getFilamentName() }} as an official?"
                class="shrink-0 rounded p-0.5 text-gray-300 dark:text-gray-600
                       hover:text-danger-500 dark:hover:text-danger-400 hover:bg-danger-50 dark:hover:bg-danger-950
                       opacity-0 group-hover:opacity-100 transition-opacity"
            >
                <x-heroicon-m-x-mark class="h-4 w-4" />
            </button>
        </div>
    </div>
</div>
