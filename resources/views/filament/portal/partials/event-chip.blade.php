<div class="inline-flex items-stretch rounded-md border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-900 text-xs shadow-sm overflow-hidden">
    @if ($ee->result)
        @if ($ee->result->disqualified)
            <div class="flex items-center px-2 bg-danger-50 dark:bg-danger-900/20 border-r border-gray-200 dark:border-slate-600 shrink-0">
                <span class="font-semibold text-danger-600 dark:text-danger-400">DQ</span>
            </div>
        @elseif ($ee->result->placement)
            @php $placeEmoji = match($ee->result->placement) { 1 => '🥇', 2 => '🥈', 3 => '🥉', default => $ee->result->placement . 'th' }; @endphp
            <div class="flex items-center px-2 bg-gray-50 dark:bg-slate-700/50 border-r border-gray-200 dark:border-slate-600 shrink-0">
                <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $placeEmoji }}</span>
            </div>
        @elseif ($ee->result->win_loss)
            <div class="flex items-center px-2 bg-gray-50 dark:bg-slate-700/50 border-r border-gray-200 dark:border-slate-600 shrink-0">
                <span class="font-semibold {{ $ee->result->win_loss === 'win' ? 'text-success-600 dark:text-success-400' : ($ee->result->win_loss === 'loss' ? 'text-danger-600 dark:text-danger-400' : 'text-gray-500') }}">{{ ucfirst($ee->result->win_loss) }}</span>
            </div>
        @endif
    @endif
    @if ($ee->division)
        <div class="flex items-center justify-center px-2.5 bg-gray-100 dark:bg-gray-700 border-r border-gray-200 dark:border-gray-600 shrink-0">
            <span class="font-mono font-bold text-gray-600 dark:text-gray-300">{{ $ee->division->code }}</span>
        </div>
    @endif
    <div class="flex flex-col px-2.5 py-1.5">
        <span class="font-medium text-gray-700 dark:text-gray-300 leading-snug">
            {{ $ee->competitionEvent->name }}@if ($ee->competitionEvent->requires_partner) <span class="ml-1 {{ $ee->yakusuko_complete ? 'text-success-500' : 'text-warning-500' }}">{{ $ee->yakusuko_complete ? '✓' : '?' }} Partner</span>@endif
        </span>
        @if ($ee->division)
            <span class="text-[0.65rem] text-gray-400 dark:text-gray-500 mt-0.5 leading-snug">{{ $ee->division->label }}</span>
        @else
            <span class="text-[0.65rem] italic text-gray-400 dark:text-gray-500 mt-0.5">TBC</span>
        @endif
    </div>
</div>
