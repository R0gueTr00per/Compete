<div class="space-y-3 max-h-96 overflow-y-auto p-1">
    @forelse ($activities as $activity)
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3 text-sm">
            <div class="flex items-center justify-between mb-1">
                <span class="font-medium text-gray-900 dark:text-white capitalize">{{ $activity->description }}</span>
                <span class="text-xs text-gray-400">{{ $activity->created_at->format('d M Y H:i') }}</span>
            </div>
            @if ($activity->causer)
                <p class="text-xs text-gray-500 mb-2">By: {{ $activity->causer->name ?? $activity->causer_id }}</p>
            @endif
            @php
                $old  = $activity->properties['old'] ?? [];
                $new  = $activity->properties['attributes'] ?? [];
                $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
            @endphp
            @if ($keys)
                <table class="w-full text-xs">
                    <thead>
                        <tr class="text-gray-400">
                            <th class="text-left pr-2 pb-1">Field</th>
                            <th class="text-left pr-2 pb-1">Before</th>
                            <th class="text-left pb-1">After</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($keys as $key)
                            @php
                                $oldVal = $old[$key] ?? null;
                                $newVal = $new[$key] ?? null;
                                $oldStr = is_array($oldVal) ? json_encode($oldVal) : ($oldVal ?? '—');
                                $newStr = is_array($newVal) ? json_encode($newVal) : ($newVal ?? '—');
                            @endphp
                            <tr class="border-t border-gray-100 dark:border-gray-800">
                                <td class="pr-2 py-0.5 text-gray-600 dark:text-gray-400">{{ $key }}</td>
                                <td class="pr-2 py-0.5 text-red-600 dark:text-red-400">{{ $oldStr }}</td>
                                <td class="py-0.5 text-green-600 dark:text-green-400">{{ $newStr }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-500 text-center py-4">No history recorded yet.</p>
    @endforelse
</div>
