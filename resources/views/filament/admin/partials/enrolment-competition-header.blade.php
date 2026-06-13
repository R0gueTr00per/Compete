@php
    $tenant = app('tenant');
    $competitions = \App\Models\Competition::when($tenant, fn ($q) => $q->where('organisation_id', $tenant->id))
        ->where('is_template', false)
        ->orderByDesc('competition_date')
        ->pluck('name', 'id');
@endphp
<div class="rounded-t-xl border-b border-primary-200 bg-primary-50 px-4 py-3 dark:border-primary-800 dark:bg-primary-950/30">
    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-primary-700 dark:text-primary-400">Competition</p>
    <select wire:model.live="competition_id"
        class="w-full block rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 py-1.5 px-3 text-sm text-gray-900 dark:text-white shadow-sm focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500">
        <option value="">— Select competition —</option>
        @foreach ($competitions as $id => $name)
            <option value="{{ $id }}">{{ $name }}</option>
        @endforeach
    </select>
</div>
