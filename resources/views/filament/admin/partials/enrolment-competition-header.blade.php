@php
    $tenant = app('tenant');
    $competitions = \App\Models\Competition::when($tenant, fn ($q) => $q->where('organisation_id', $tenant->id))
        ->where('is_template', false)
        ->orderByDesc('competition_date')
        ->pluck('name', 'id');

    $enrolledCount = $competition?->enrolled_count ?? 0;
    $target = $competition?->target_competitors;
    $pct = ($target && $target > 0) ? min(100, round($enrolledCount / $target * 100)) : null;
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

    @if ($competition)
        <div class="mt-3">
            @if ($target)
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs text-primary-700 dark:text-primary-400">Competitors</span>
                    <span class="text-xs font-semibold text-primary-800 dark:text-primary-300">{{ $enrolledCount }} / {{ $target }}
                        <span class="font-normal text-primary-600 dark:text-primary-500">({{ $pct }}%)</span>
                    </span>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                    <div class="h-1.5 rounded-full {{ $pct >= 100 ? 'bg-success-500' : 'bg-primary-500' }}"
                         style="width: {{ $pct }}%"></div>
                </div>
            @else
                <p class="text-xs text-primary-700 dark:text-primary-400">
                    {{ $enrolledCount }} competitor{{ $enrolledCount !== 1 ? 's' : '' }} registered
                </p>
            @endif
        </div>
    @endif
</div>
