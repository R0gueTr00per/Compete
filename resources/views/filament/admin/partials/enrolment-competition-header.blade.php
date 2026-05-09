@php
    $competitions = \App\Models\Competition::orderByDesc('competition_date')->pluck('name', 'id');
@endphp
<div class="rounded-t-xl border-b border-primary-200 bg-primary-50 px-4 py-3 dark:border-primary-800 dark:bg-primary-950/30">
    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-primary-700 dark:text-primary-400">Competition</p>
    <x-filament::input.wrapper>
        <select wire:model.live="competition_id"
            class="w-full block border-0 bg-transparent py-1.5 text-sm text-gray-900 dark:text-white focus:ring-0">
            <option value="">— Select competition —</option>
            @foreach ($competitions as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>
    </x-filament::input.wrapper>
</div>
