@php
    $colorClass = match(true) {
        $div->status === 'complete'              => 'sched-complete',
        $div->active_enrolment_events_count >= 2 => 'sched-full',
        $div->location_label !== null            => 'sched-assigned',
        default                                  => 'sched-pending',
    };
    $checkedIn  = $div->checked_in_count ?? 0;
    $enrolled   = $div->active_enrolment_events_count ?? 0;
    $noneShowed = $enrolled > 0 && $checkedIn === 0 && $div->status !== 'complete';
    $divisionData = json_encode([
        'id'         => $div->id,
        'code'       => $div->code,
        'label'      => $div->label,
        'event'      => $div->competitionEvent->name,
        'status'     => $div->status,
        'enrolled'   => $enrolled,
        'checkedIn'  => $checkedIn,
        'noneShowed' => $noneShowed,
    ]);
@endphp
<div
    data-id="{{ $div->id }}"
    data-division="{{ $divisionData }}"
    @if($div->status === 'complete') data-scored="true" @endif
    class="group relative mb-1.5 rounded-md border shadow-sm transition-colors
        {{ $colorClass }}
        {{ $div->status === 'complete' ? 'cursor-default' : 'cursor-grab hover:shadow-md' }}"
>
    {{-- Mobile: code only --}}
    <div class="sm:hidden px-1 py-1.5 text-center">
        <span class="font-mono text-xs font-bold text-gray-900 dark:text-white">{{ $div->code ?: '—' }}</span>
    </div>

    {{-- Desktop: full card --}}
    <div class="hidden sm:block px-3 py-2">
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2 min-w-0">
                <span class="font-mono text-xs font-bold shrink-0 text-gray-900 dark:text-white">
                    {{ $div->code }}
                </span>
                <span class="text-xs truncate sched-text-meta">
                    {{ $div->competitionEvent->name }}
                </span>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                @if($div->status === 'complete')
                    <x-heroicon-m-check-circle class="h-4 w-4 text-success-500" />
                @else
                    @if($enrolled > 0)
                        <span title="Enrolments" class="flex items-center gap-0.5 text-xs sched-text-meta tabular-nums">
                            {{ $enrolled }}<x-heroicon-m-user class="h-3 w-3 sched-text-meta" />
                        </span>
                    @endif
                    @if($checkedIn > 0)
                        <span title="Checked in" class="flex items-center gap-0.5 text-xs text-success-600 dark:text-success-400 tabular-nums">
                            {{ $checkedIn }}<x-heroicon-m-check class="h-3 w-3 text-success-500" />
                        </span>
                    @elseif($noneShowed)
                        <span title="Checked in" class="flex items-center gap-0.5 text-xs text-warning-600 dark:text-warning-400 tabular-nums">
                            0<x-heroicon-m-check class="h-3 w-3 text-warning-500" />
                        </span>
                    @endif
                @endif
            </div>
        </div>

        <div class="text-xs mt-0.5 truncate sched-text-meta">
            {{ $div->label }}
        </div>
    </div>
</div>
