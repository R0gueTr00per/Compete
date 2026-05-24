@php
    $profile    = $official->user?->selfProfile;
    $firstName  = $profile?->first_name;
    $surname    = $profile?->surname;
    $fallback   = $official->user?->getFilamentName() ?? '—';
    $officialData = json_encode([
        'id'        => $official->id,
        'firstName' => $firstName ?? $fallback,
        'surname'   => $firstName ? ($surname ?? '') : '',
        'role'      => $official->officialRole?->name ?? '—',
    ]);
@endphp
<div
    data-id="{{ $official->id }}"
    data-official="{{ $officialData }}"
    class="group relative mb-1.5 rounded-md border shadow-sm transition-colors sm:cursor-grab
           bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-600
           hover:shadow-md hover:border-gray-300 dark:hover:border-gray-500"
>
    <div class="px-2 py-1.5 sm:px-3 sm:py-2">
        <div class="flex items-start justify-between gap-1">
            <div class="min-w-0">
                @if($firstName)
                    <p class="text-xs font-semibold text-gray-900 dark:text-white truncate leading-tight">{{ $firstName }}</p>
                    @if($surname)
                        <p class="text-xs font-medium text-gray-700 dark:text-gray-200 truncate leading-tight">{{ $surname }}</p>
                    @endif
                @else
                    <p class="text-xs font-semibold text-gray-900 dark:text-white truncate leading-tight">{{ $fallback }}</p>
                @endif
                <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5">
                    {{ $official->officialRole?->name ?? '—' }}
                </p>
            </div>
            <button
                type="button"
                title="Remove official"
                x-on:click="$wire.mountAction('removeOfficial', { officialId: {{ $official->id }}, name: '{{ addslashes($official->user?->getFilamentName() ?? '') }}' })"
                class="hidden sm:flex shrink-0 rounded p-0.5 text-gray-300 dark:text-gray-600
                       hover:text-danger-500 dark:hover:text-danger-400 hover:bg-danger-50 dark:hover:bg-danger-950
                       opacity-0 group-hover:opacity-100 transition-opacity"
            >
                <x-heroicon-m-x-mark class="h-4 w-4" />
            </button>
        </div>
    </div>
</div>
