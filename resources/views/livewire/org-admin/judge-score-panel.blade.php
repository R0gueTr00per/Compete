<div x-data="{}">
    @php
        $rows             = $this->competitorRows;
        $method           = $this->getScoringMethod();
        $judges           = $this->getJudgeCount();
        $categories       = $this->getScoreCategories();
        $hasCategories    = $categories->isNotEmpty();
        $categoryMode     = $div->competitionEvent?->score_category_mode ?? 'single';
        $judgeMin         = $div->competitionEvent?->min_score;
        $judgeMax         = $div->competitionEvent?->max_score;
        $isReadOnly       = $div->status === 'complete';
        $targetScore      = $method === 'first_to_n' ? $this->getTargetScore() : null;
        $incrementButtons = in_array($method, ['first_to_n', 'timed_points']) ? $this->getIncrementButtons() : [];
        $usedPlacements   = $rows->pluck('result.placement')->filter()->values()->all();
        $enabledPenalties = $this->getEnabledPenaltyTypes();
        $awardedCap       = match (true) {
            $rows->count() <= 2  => $div->competitionEvent?->awarded_places_2    ?? 2,
            $rows->count() === 3 => $div->competitionEvent?->awarded_places_3    ?? 3,
            default              => $div->competitionEvent?->awarded_places_4plus ?? 3,
        };
        $dqViaPenalties   = in_array('dq', $enabledPenalties);
        $highLowDrop      = $div->competitionEvent->high_low_drop ?? false;
    @endphp

    @php
        $progressTotal = $rows->count();
        $progressDone  = $rows->filter(fn ($r) => in_array($r->result->id, $this->savedResultIds))->count();
    @endphp

    @if (! $isReadOnly)
        <div class="flex items-center justify-between gap-2 mb-3">
            @if ($progressTotal > 0)
                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs
                    {{ $progressDone >= $progressTotal
                        ? 'bg-success-50 dark:bg-success-900/30 text-success-600 dark:text-success-400'
                        : 'bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400' }}">
                    <x-heroicon-m-check-circle class="w-3 h-3 shrink-0" />
                    {{ $progressDone }} / {{ $progressTotal }} scored
                </span>
            @else
                <div></div>
            @endif
            <div class="flex items-center gap-2">
                @if (in_array($method, ['judges_total', 'judges_average']))
                    <x-filament::button size="xs" color="gray"
                        x-on:click="$dispatch('open-modal', { id: 'confirm-reset-scores' })">
                        Reset scores
                    </x-filament::button>
                @endif
                <x-filament::button size="xs"
                    color="{{ $this->placementOverrideMode ? 'warning' : 'gray' }}"
                    wire:click="togglePlacementOverrideMode">
                    {{ $this->placementOverrideMode ? 'Auto (clear overrides)' : 'Override placements' }}
                </x-filament::button>
            </div>
        </div>
    @endif

    @if ($rows->isEmpty())
        <p class="text-center text-sm text-gray-400 py-4">No checked-in competitors in this division.</p>
    @else

    {{-- Mobile: one card per competitor --}}
    <div class="sm:hidden space-y-2">
        @foreach ($rows as $row)
            @php
                $result           = $row->result;
                $isSaved          = in_array($result->id, $this->savedResultIds);
                $inTiebreakerFlow = $result->tiebreaker_score !== null || $result->placement_overridden;
                $rawScores        = array_filter(array_values($this->judgeScores[$result->id] ?? []), fn ($v) => $v !== null && $v !== '');
                $scoreCount = count($rawScores);
                $liveTotal  = in_array($method, ['judges_total', 'judges_average']) && $scoreCount > 0
                    ? ($method === 'judges_average'
                        ? round(array_sum($rawScores) / $scoreCount, 1)
                        : round(array_sum($rawScores), 1))
                    : null;
                $droppedMinJ = null; $droppedMaxJ = null;
                if ($highLowDrop && $isSaved) {
                    $jTotals = [];
                    for ($jj = 1; $jj <= $judges; $jj++) {
                        if ($hasCategories) {
                            $allF = $categories->every(fn ($cat) => isset($this->categoryScores[$result->id][$jj][$cat->id]) && (string) $this->categoryScores[$result->id][$jj][$cat->id] !== '');
                            if ($allF) {
                                $jTotals[$jj] = $categoryMode === 'weighted'
                                    ? $categories->sum(fn ($cat) => (float) ($this->categoryScores[$result->id][$jj][$cat->id] ?? 0) * ((float) $cat->weight / 100))
                                    : $categories->sum(fn ($cat) => (float) ($this->categoryScores[$result->id][$jj][$cat->id] ?? 0));
                            }
                        } else {
                            $s = $this->judgeScores[$result->id][$jj] ?? null;
                            if ($s !== null && $s !== '') $jTotals[$jj] = (float) $s;
                        }
                    }
                    if (count($jTotals) >= 3) {
                        $minV = min($jTotals); $maxV = max($jTotals);
                        foreach ($jTotals as $jj => $v) { if ($droppedMinJ === null && $v == $minV) { $droppedMinJ = $jj; break; } }
                        foreach ($jTotals as $jj => $v) { if ($droppedMaxJ === null && $v == $maxV && $jj !== $droppedMinJ) { $droppedMaxJ = $jj; break; } }
                    }
                }
            @endphp
            <div wire:key="mobile-row-{{ $result->id }}"
                 data-scoring-key="row-{{ $result->id }}"
                 x-data="{ open: false, saving: false }"
                 class="rounded-lg border {{ $result->disqualified ? 'opacity-60' : '' }} border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">

                {{-- Card header --}}
                <div class="px-3 py-3">
                <div class="flex items-center gap-2">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1 font-medium text-sm text-gray-900 dark:text-white">
                            <span class="truncate min-w-0">{{ $row->name }}</span>
                            @if ($result->disqualified || $result->forfeited)
                                <span class="shrink-0 text-xs text-danger-600">{{ $result->forfeited ? '[Forfeit]' : '[DQ]' }}</span>
                            @endif
                            @if (! $isReadOnly || $result->note)
                                <button type="button"
                                    data-result-id="{{ $result->id }}"
                                    data-note="{{ $result->note ?? '' }}"
                                    x-on:click="$dispatch('open-note-modal', { resultId: parseInt($el.dataset.resultId), note: $el.dataset.note })"
                                    class="shrink-0 {{ $result->note ? 'text-primary-500 animate-pulse' : 'text-gray-400 hover:text-primary-500 dark:hover:text-primary-400' }} transition-colors">
                                    <x-heroicon-o-document-text class="w-4 h-4" />
                                </button>
                            @endif
                        </div>
                        @if ($row->info)
                            <p class="text-xs text-gray-400 dark:text-gray-500">{{ $row->info }}</p>
                        @endif
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                            @if (in_array($method, ['judges_total', 'judges_average']))
                                @if ($isSaved)
                                    @php
                                        $mobileTotal = $result->total_score !== null
                                            ? ($hasCategories ? rtrim(rtrim(number_format((float) $result->total_score, 3), '0'), '.') : number_format((float) $result->total_score, 1))
                                            : '—';
                                    @endphp
                                    Total: <strong>{{ $mobileTotal }}</strong>
                                    · <span class="text-success-600 dark:text-success-400">Saved</span>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">Not yet saved</span>
                                @endif
                            @elseif ($method === 'win_loss')
                                {{ ucfirst($result->win_loss ?? 'No result') }}
                            @elseif (in_array($method, ['first_to_n', 'timed_points']))
                                @if ($result->total_score !== null)
                                    Points: <strong>{{ (int) $result->total_score }}</strong>
                                    · <span class="text-success-600 dark:text-success-400">Saved</span>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">Not yet saved</span>
                                @endif
                            @endif
                        </p>
                        @if (in_array($method, ['judges_total', 'judges_average']))
                            @php
                                $penaltyLog    = $this->getPenaltyLog($result->id);
                                $oncePenalties = array_filter($enabledPenalties, fn ($t) => ! in_array($t, ['deduction', 'opponent_point']));
                            @endphp
                            @if (! $isReadOnly && ! $isSaved && ! empty($oncePenalties))
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @foreach ($oncePenalties as $pType)
                                        <button type="button"
                                            wire:click="openPenaltyModal({{ $result->id }}, '{{ $pType }}')"
                                            class="px-2 py-1 rounded text-xs font-medium border border-danger-300 dark:border-danger-700 bg-danger-50 dark:bg-danger-900/20 text-danger-700 dark:text-danger-400 active:scale-95 transition-transform">
                                            {{ $this->getPenaltyLabel($pType) }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                            @if (! empty($penaltyLog))
                                <ul class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 space-y-0.5">
                                    @foreach ($penaltyLog as $entry)<li>{{ $entry['label'] }}</li>@endforeach
                                </ul>
                            @endif
                        @endif
                    </div>

                    @if ($result->placement && (
                        $method === 'win_loss' ||
                        (in_array($method, ['judges_total', 'judges_average']) && ($result->total_score !== null || $result->placement_overridden)) ||
                        (in_array($method, ['first_to_n', 'timed_points']) && $result->total_score !== null)
                    ))
                        <div class="shrink-0">
                            @if ($result->placement <= $awardedCap)
                                @switch($result->placement)
                                    @case(1) <span class="text-3xl leading-none">🥇</span> @break
                                    @case(2) <span class="text-3xl leading-none">🥈</span> @break
                                    @case(3) <span class="text-3xl leading-none">🥉</span> @break
                                    @default <span class="text-base font-bold text-gray-500 dark:text-gray-400">#{{ $result->placement }}</span>
                                @endswitch
                            @else
                                <span class="text-sm font-medium text-gray-400 dark:text-gray-500">#{{ $result->placement }}</span>
                            @endif
                        </div>
                    @endif

                    @if (! $isReadOnly)
                        <div class="shrink-0">
                            @if ($method === 'win_loss')
                                <div class="flex gap-1">
                                    <x-filament::button size="xs"
                                        color="{{ $result->win_loss === 'win' ? 'success' : 'gray' }}"
                                        wire:click="saveWinLoss({{ $result->id }}, 'win')"
                                        wire:loading.attr="disabled" wire:target="saveWinLoss({{ $result->id }}, 'win')">W</x-filament::button>
                                    <x-filament::button size="xs"
                                        color="{{ $result->win_loss === 'loss' ? 'danger' : 'gray' }}"
                                        wire:click="saveWinLoss({{ $result->id }}, 'loss')"
                                        wire:loading.attr="disabled" wire:target="saveWinLoss({{ $result->id }}, 'loss')">L</x-filament::button>
                                    <x-filament::button size="xs"
                                        color="{{ $result->win_loss === 'draw' ? 'warning' : 'gray' }}"
                                        wire:click="saveWinLoss({{ $result->id }}, 'draw')"
                                        wire:loading.attr="disabled" wire:target="saveWinLoss({{ $result->id }}, 'draw')">D</x-filament::button>
                                    @if (! $dqViaPenalties)
                                        <x-filament::button size="xs"
                                            color="{{ $result->disqualified ? 'gray' : 'danger' }}"
                                            wire:click="toggleDisqualify({{ $result->id }})">
                                            {{ $result->disqualified ? 'Un-DQ' : 'DQ' }}
                                        </x-filament::button>
                                    @endif
                                </div>
                            @elseif (in_array($method, ['first_to_n', 'timed_points']))
                                @php $ftnSaved = $result->total_score !== null; @endphp
                                <div class="flex items-center gap-1">
                                    @if (! $dqViaPenalties)
                                        <x-filament::button size="xs"
                                            color="{{ $result->disqualified ? 'gray' : 'danger' }}"
                                            wire:click="toggleDisqualify({{ $result->id }})">
                                            {{ $result->disqualified ? 'Un-DQ' : 'DQ' }}
                                        </x-filament::button>
                                    @endif
                                    <button x-on:click="open = !open"
                                        class="flex items-center gap-1.5 rounded-lg px-3 py-2 text-base font-medium {{ $ftnSaved ? 'bg-success-50 text-success-700 dark:bg-success-900/30 dark:text-success-400' : 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400' }}">
                                        <x-heroicon-m-pencil-square class="w-4 h-4" />
                                        <span x-show="!open">{{ $ftnSaved ? 'Edit' : 'Score' }}</span>
                                        <span x-show="open" x-cloak>Close</span>
                                    </button>
                                </div>
                            @else
                                <button x-on:click="open = !open"
                                    class="flex items-center gap-1.5 rounded-lg px-3 py-2 text-base font-medium {{ $isSaved ? 'bg-success-50 text-success-700 dark:bg-success-900/30 dark:text-success-400' : 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400' }}">
                                    <x-heroicon-m-pencil-square class="w-4 h-4" />
                                    <span x-show="!open">{{ $isSaved ? 'Edit' : 'Score' }}</span>
                                    <span x-show="open" x-cloak>Close</span>
                                </button>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Non-judged: penalty buttons + undo + log --}}
                @if (! in_array($method, ['judges_total', 'judges_average']))
                    @php
                        $penaltyLog     = $this->getPenaltyLog($result->id);
                        $warnCount      = $this->getWarnCount($result->id);
                        $canUndoPenalty = $this->hasUndoablePenalty($result->id);
                        $oncePenalties  = array_filter($enabledPenalties, fn ($t) => ! in_array($t, ['deduction', 'opponent_point']));
                    @endphp
                    @if (! $isReadOnly && ! empty($oncePenalties))
                        <div class="mt-1.5 flex flex-wrap gap-1 items-center">
                            @foreach ($oncePenalties as $pType)
                                <button type="button"
                                    wire:click="openPenaltyModal({{ $result->id }}, '{{ $pType }}')"
                                    class="px-2 py-1 rounded text-xs font-medium border {{ in_array($pType, ['dq','forfeit']) ? 'border-danger-300 dark:border-danger-700 bg-danger-50 dark:bg-danger-900/20 text-danger-700 dark:text-danger-400' : 'border-warning-300 dark:border-warning-700 bg-warning-50 dark:bg-warning-900/20 text-warning-700 dark:text-warning-400' }} active:scale-95 transition-transform">
                                    @if ($pType === 'warn' && $warnCount > 0)Warn {{ $warnCount }}@else{{ $this->getPenaltyLabel($pType) }}@endif
                                </button>
                            @endforeach
                            @if ($canUndoPenalty)
                                <button type="button" wire:click="undoPenalty({{ $result->id }})"
                                    class="px-2 py-1 rounded text-xs border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-400 active:scale-95 transition-transform">
                                    <x-heroicon-m-arrow-uturn-left class="inline w-3 h-3" /> Undo
                                </button>
                            @endif
                        </div>
                    @endif
                    @if (! empty($penaltyLog))
                        <ul class="mt-1 text-xs text-gray-500 dark:text-gray-400 space-y-0.5">
                            @foreach ($penaltyLog as $entry)<li>{{ $entry['label'] }}</li>@endforeach
                        </ul>
                    @endif
                @endif
                </div>

                {{-- Expandable judge score sheet --}}
                @if (! $isReadOnly && in_array($method, ['judges_total', 'judges_average']))
                    <div x-show="open" x-transition
                         class="border-t border-gray-100 dark:border-gray-700 px-3 pb-3 pt-3 space-y-3">
                        @for ($j = 1; $j <= $judges; $j++)
                            @php $isDropped = $highLowDrop && ($j === $droppedMinJ || $j === $droppedMaxJ); @endphp
                            @if ($hasCategories)
                                <div>
                                    <p class="text-xs font-semibold mb-1.5 {{ $isDropped ? 'line-through text-gray-400 dark:text-gray-500' : 'text-gray-500 dark:text-gray-400' }}">Judge {{ $j }}</p>
                                    <div class="space-y-1.5 {{ ($isSaved && $isDropped) ? 'opacity-40' : ($isSaved ? 'opacity-50' : '') }}">
                                        @foreach ($categories as $cat)
                                            @php $catMin = $judgeMin ?? 0; $catMax = $judgeMax; @endphp
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs text-gray-500 dark:text-gray-400 w-28 shrink-0">{{ $cat->name }}@if ($categoryMode === 'weighted') <span class="text-gray-400">({{ $cat->weight }}%)</span>@endif</span>
                                                <div class="flex items-center gap-1 flex-1" x-data="{}">
                                                    <button type="button"
                                                        x-on:click="const i=$el.nextElementSibling; const v=Math.round((parseFloat(i.value||{{ $catMin }})-0.1)*10)/10; i.value=Math.max({{ $catMin }},v).toFixed(1); i.dispatchEvent(new Event('input',{bubbles:true}));"
                                                        class="w-11 h-11 flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-xl font-medium active:scale-95 transition-transform"
                                                        @if ($isSaved) disabled @endif>−</button>
                                                    <input type="number" step="0.1"
                                                        min="{{ $catMin }}"
                                                        @if ($catMax !== null) max="{{ $catMax }}" @endif
                                                        wire:model.blur="categoryScores.{{ $result->id }}.{{ $j }}.{{ $cat->id }}"
                                                        data-cat-j="{{ $j }}" data-cat-id="{{ $cat->id }}"
                                                        class="flex-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-base py-2.5 px-3 text-center"
                                                        placeholder="{{ number_format($catMin, 1) }}"
                                                        @if ($isSaved) disabled @endif />
                                                    <button type="button"
                                                        x-on:click="const i=$el.previousElementSibling; const v=Math.round((parseFloat(i.value||{{ $catMin }})+0.1)*10)/10; i.value={{ $catMax !== null ? 'Math.min('.$catMax.',v)' : 'v' }}.toFixed(1); i.dispatchEvent(new Event('input',{bubbles:true}));"
                                                        class="w-11 h-11 flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-xl font-medium active:scale-95 transition-transform"
                                                        @if ($isSaved) disabled @endif>+</button>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                            <div x-data="{}">
                                <label class="block text-xs font-medium mb-1 {{ $isDropped ? 'line-through text-gray-400 dark:text-gray-500' : 'text-gray-500 dark:text-gray-400' }}">Judge {{ $j }}</label>
                                <div class="flex items-center gap-2">
                                    <input x-ref="inp" type="number" step="0.1"
                                        @if ($judgeMin !== null) min="{{ $judgeMin }}" @else min="0" @endif
                                        @if ($judgeMax !== null) max="{{ $judgeMax }}" @endif
                                        wire:model="judgeScores.{{ $result->id }}.{{ $j }}"
                                        class="flex-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-base py-2.5 px-3 text-center {{ ($isSaved && $isDropped) ? 'opacity-40' : ($isSaved ? 'opacity-50' : '') }}"
                                        placeholder="{{ $judgeMin ?? '0.0' }}"
                                        @if ($isSaved) disabled @endif />
                                    @if (! $isSaved)
                                        <div class="flex gap-1 shrink-0">
                                            <button type="button"
                                                x-on:click="let v = Math.round((parseFloat($refs.inp.value || 0) - 0.1) * 10) / 10; $refs.inp.value = Math.max(0, v).toFixed(1); $refs.inp.dispatchEvent(new Event('input', {bubbles: true}));"
                                                class="w-11 h-11 flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-xl font-medium active:scale-95 transition-transform">−</button>
                                            <button type="button"
                                                x-on:click="let v = Math.round((parseFloat($refs.inp.value || 0) + 0.1) * 10) / 10; $refs.inp.value = Math.min(10, v).toFixed(1); $refs.inp.dispatchEvent(new Event('input', {bubbles: true}));"
                                                class="w-11 h-11 flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-xl font-medium active:scale-95 transition-transform">+</button>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            @endif
                        @endfor

                        @if ($this->placementOverrideMode)
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Override placement</label>
                                <select wire:model="placementInput.{{ $result->id }}"
                                    wire:change="overridePlacement({{ $result->id }})"
                                    class="w-full rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-base text-gray-900 dark:text-white px-2 py-2">
                                    <option value="">—</option>
                                    @for ($p = 1; $p <= $rows->count(); $p++)
                                        @if (! in_array($p, $usedPlacements) || $result->placement == $p)
                                            <option value="{{ $p }}" {{ $result->placement == $p ? 'selected' : '' }}>{{ $p }}</option>
                                        @endif
                                    @endfor
                                </select>
                            </div>
                        @endif

                        <div class="flex flex-col gap-2 pt-1">
                            @if ($isSaved)
                                <x-filament::button color="gray" class="flex-1"
                                    wire:click="undoJudgeScores({{ $result->id }})"
                                    :disabled="$inTiebreakerFlow">Undo</x-filament::button>
                            @else
                                <x-filament::button color="success" class="flex-1"
                                    x-bind:disabled="saving"
                                    x-on:click="saving=true; const d={}; ($el.closest('[data-scoring-key=row-{{ $result->id }}]')?.querySelectorAll('input[data-cat-j]')||[]).forEach(i=>{if(!d[i.dataset.catJ])d[i.dataset.catJ]={};d[i.dataset.catJ][i.dataset.catId]=i.value;}); $wire.saveJudgeScores({{ $result->id }},d).then(()=>open=false).finally(()=>saving=false)">
                                    <span x-show="saving" class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                                    Save scores
                                </x-filament::button>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Expandable: first_to_n / timed_points --}}
                @if (! $isReadOnly && in_array($method, ['first_to_n', 'timed_points']))
                    @php
                        $ftnSaved  = $result->total_score !== null;
                        $atTarget  = $method === 'first_to_n' && $targetScore !== null && (int) ($result->total_score ?? 0) >= $targetScore;
                        $hasEvents = $result->scoreEvents()->exists();
                    @endphp
                    <div x-show="open" x-transition
                         class="border-t border-gray-100 dark:border-gray-700 px-3 pb-3 pt-3 space-y-3">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium {{ $atTarget ? 'inline-block bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300 rounded px-1 winner-halo' : 'text-gray-700 dark:text-gray-300' }}">
                                    Points: <strong>{{ (int) ($result->total_score ?? 0) }}</strong>
                                    @if ($targetScore) <span class="{{ $atTarget ? '' : 'text-gray-400' }}">/ {{ $targetScore }}</span> @endif
                                </span>
                                <button type="button" wire:click="undoPoints({{ $result->id }})"
                                    @if (! $hasEvents) disabled @endif
                                    class="flex items-center gap-1 text-xs px-2.5 py-1.5 rounded border {{ $hasEvents ? 'border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-200 active:scale-95' : 'border-gray-200 dark:border-gray-700 text-gray-300 dark:text-gray-600 cursor-not-allowed' }} transition-transform">
                                    <x-heroicon-m-arrow-uturn-left class="w-3.5 h-3.5" /> Undo
                                </button>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($incrementButtons as $btn)
                                    <button type="button"
                                        wire:click="addPoints({{ $result->id }}, {{ $btn }})"
                                        @if ($atTarget) disabled @endif
                                        class="flex-1 min-w-[3rem] h-14 flex items-center justify-center rounded-lg text-xl font-semibold shadow-sm transition-transform {{ $atTarget ? 'bg-gray-100 dark:bg-gray-800 text-gray-300 dark:text-gray-600 cursor-not-allowed' : 'bg-primary-600 dark:bg-primary-500 text-white active:scale-95' }}">
                                        +{{ $btn }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        <details class="text-xs text-gray-400">
                            <summary class="cursor-pointer select-none">Manual entry</summary>
                            <div class="flex items-center gap-2 mt-2">
                                <input type="number" min="0"
                                    wire:model="pointsInput.{{ $result->id }}"
                                    class="flex-1 text-center rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-base py-2 px-3"
                                    placeholder="0" />
                                <x-filament::button size="sm" color="gray"
                                    wire:click="savePoints({{ $result->id }})"
                                    wire:loading.attr="disabled" wire:target="savePoints({{ $result->id }})"
                                    x-on:click="open = false">Set</x-filament::button>
                            </div>
                        </details>
                    </div>
                @endif

            </div>
        @endforeach
    </div>

    {{-- Desktop: original table --}}
    <div class="hidden sm:block overflow-x-auto">
        <table class="w-full text-base">
            <thead class="bg-gray-50 dark:bg-gray-800/60">
                <tr class="border-b border-gray-200 dark:border-gray-700 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">
                    <th class="pb-2 pr-4">Competitor</th>
                    @if (in_array($method, ['judges_total', 'judges_average']))
                        @for ($j = 1; $j <= $judges; $j++)
                            <th class="pb-2 pr-1">J{{ $j }}</th>
                        @endfor
                        <th class="pb-2 pr-4">Total</th>
                    @elseif ($method === 'win_loss')
                        <th class="pb-2 pr-4">Result</th>
                    @elseif (in_array($method, ['first_to_n', 'timed_points']))
                        <th class="pb-2 pr-4">Points</th>
                    @endif
                    <th class="pb-2 pr-4">Place</th>
                    @if (! $isReadOnly)
                        <th class="pb-2">Actions</th>
                    @endif
                </tr>
            </thead>
            @foreach ($rows as $row)
                @php
                    $result  = $row->result;
                    $isSaved = in_array($result->id, $this->savedResultIds);
                    $inTiebreakerFlow = $result->tiebreaker_score !== null || $result->placement_overridden;
                @endphp
                <tbody wire:key="dtrow-{{ $result->id }}"
                    data-scoring-key="row-{{ $result->id }}"
                    class="border-b border-gray-100 dark:border-gray-800 last:border-b-0 border-l-4 {{ $isSaved ? 'border-l-success-400 dark:border-l-success-500' : 'border-l-blue-400 dark:border-l-blue-500' }}">
                <tr class="{{ $result->disqualified ? 'opacity-50' : '' }}">
                    <td class="py-2 pr-4 pl-3">
                        <div class="flex items-center gap-1 font-medium text-gray-900 dark:text-white">
                            <span class="truncate min-w-0">{{ $row->name }}</span>
                            @if ($result->disqualified || $result->forfeited)
                                <span class="shrink-0 text-xs text-danger-600">{{ $result->forfeited ? '[Forfeit]' : '[DQ]' }}</span>
                            @endif
                            @if (! $isReadOnly || $result->note)
                                <button type="button"
                                    data-result-id="{{ $result->id }}"
                                    data-note="{{ $result->note ?? '' }}"
                                    x-on:click="$dispatch('open-note-modal', { resultId: parseInt($el.dataset.resultId), note: $el.dataset.note })"
                                    class="shrink-0 {{ $result->note ? 'text-primary-500 animate-pulse' : 'text-gray-400 hover:text-primary-500 dark:hover:text-primary-400' }} transition-colors">
                                    <x-heroicon-o-document-text class="w-4 h-4" />
                                </button>
                            @endif
                        </div>
                        @if ($row->info)
                            <div class="text-xs text-gray-400 dark:text-gray-500">{{ $row->info }}</div>
                        @endif
                        @if (in_array($method, ['judges_total', 'judges_average']))
                            @php
                                $dtPenaltyLog    = $this->getPenaltyLog($result->id);
                                $dtOncePenalties = array_filter($enabledPenalties, fn ($t) => ! in_array($t, ['deduction', 'opponent_point']));
                            @endphp
                            @if (! $isReadOnly && ! $isSaved && ! empty($dtOncePenalties))
                                <div class="mt-1 flex flex-wrap gap-1 items-center">
                                    @foreach ($dtOncePenalties as $pType)
                                        <button type="button"
                                            wire:click="openPenaltyModal({{ $result->id }}, '{{ $pType }}')"
                                            class="px-1.5 py-0.5 rounded text-xs font-medium border border-danger-300 dark:border-danger-700 bg-danger-50 dark:bg-danger-900/20 text-danger-700 dark:text-danger-400 active:scale-95 transition-transform">
                                            {{ $this->getPenaltyLabel($pType) }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                            @if (! empty($dtPenaltyLog))
                                <ul class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 space-y-0.5">
                                    @foreach ($dtPenaltyLog as $entry)<li>{{ $entry['label'] }}</li>@endforeach
                                </ul>
                            @endif
                        @else
                            @php $dtPenaltyLog = $this->getPenaltyLog($result->id); @endphp
                            @if (! empty($dtPenaltyLog))
                                <ul class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 space-y-0.5">
                                    @foreach ($dtPenaltyLog as $entry)<li>{{ $entry['label'] }}</li>@endforeach
                                </ul>
                            @endif
                        @endif
                    </td>

                    @if (in_array($method, ['judges_total', 'judges_average']))
                        @php
                            $rawScores  = array_filter(array_values($this->judgeScores[$result->id] ?? []), fn ($v) => $v !== null && $v !== '');
                            $scoreCount = count($rawScores);
                            $liveTotal  = $scoreCount > 0
                                ? ($method === 'judges_average'
                                    ? round(array_sum($rawScores) / $scoreCount, 1)
                                    : round(array_sum($rawScores), 1))
                                : null;
                            $droppedMinJ = null; $droppedMaxJ = null;
                            if ($highLowDrop && ($isSaved || $isReadOnly)) {
                                $jTotals = [];
                                for ($jj = 1; $jj <= $judges; $jj++) {
                                    if ($hasCategories) {
                                        $allF = $categories->every(fn ($cat) => isset($this->categoryScores[$result->id][$jj][$cat->id]) && (string) $this->categoryScores[$result->id][$jj][$cat->id] !== '');
                                        if ($allF) {
                                            $jTotals[$jj] = $categoryMode === 'weighted'
                                                ? $categories->sum(fn ($cat) => (float) ($this->categoryScores[$result->id][$jj][$cat->id] ?? 0) * ((float) $cat->weight / 100))
                                                : $categories->sum(fn ($cat) => (float) ($this->categoryScores[$result->id][$jj][$cat->id] ?? 0));
                                        }
                                    } else {
                                        $s = $this->judgeScores[$result->id][$jj] ?? null;
                                        if ($s !== null && $s !== '') $jTotals[$jj] = (float) $s;
                                    }
                                }
                                if (count($jTotals) >= 3) {
                                    $minV = min($jTotals); $maxV = max($jTotals);
                                    foreach ($jTotals as $jj => $v) { if ($droppedMinJ === null && $v == $minV) { $droppedMinJ = $jj; break; } }
                                    foreach ($jTotals as $jj => $v) { if ($droppedMaxJ === null && $v == $maxV && $jj !== $droppedMinJ) { $droppedMaxJ = $jj; break; } }
                                }
                            }
                        @endphp
                        @for ($j = 1; $j <= $judges; $j++)
                            @php $isDropped = $highLowDrop && ($j === $droppedMinJ || $j === $droppedMaxJ); @endphp
                            <td class="py-2 pr-1">
                                @if ($isReadOnly || $hasCategories)
                                    @if ($hasCategories)
                                        @if ($isSaved || $isReadOnly)
                                            @php
                                                $allFilled = $categories->every(fn ($cat) => isset($this->categoryScores[$result->id][$j][$cat->id]) && (string) $this->categoryScores[$result->id][$j][$cat->id] !== '');
                                                if ($allFilled) {
                                                    $raw = $categoryMode === 'weighted'
                                                        ? $categories->sum(fn ($cat) => (float) ($this->categoryScores[$result->id][$j][$cat->id] ?? 0) * ((float) $cat->weight / 100))
                                                        : $categories->sum(fn ($cat) => (float) ($this->categoryScores[$result->id][$j][$cat->id] ?? 0));
                                                    $judgeTotal = rtrim(rtrim(number_format($raw, 3), '0'), '.');
                                                } else {
                                                    $judgeTotal = null;
                                                }
                                            @endphp
                                            <span class="text-base font-medium {{ $isDropped ? 'line-through text-gray-400 dark:text-gray-500' : ($judgeTotal ? 'text-gray-900 dark:text-white' : 'text-gray-400') }}">
                                                {{ $judgeTotal ?? '—' }}
                                            </span>
                                        @else
                                            <span class="text-base text-gray-400">—</span>
                                        @endif
                                    @else
                                        <span class="text-base font-medium {{ $isDropped ? 'line-through text-gray-400 dark:text-gray-500' : (($this->judgeScores[$result->id][$j] ?? null) ? 'text-gray-900 dark:text-white' : 'text-gray-400') }}">
                                            {{ $this->judgeScores[$result->id][$j] ?? '—' }}
                                        </span>
                                    @endif
                                @else
                                    <div class="flex items-center gap-0.5 {{ ($isSaved && $isDropped) ? 'opacity-40' : ($isSaved ? 'opacity-50' : '') }}" x-data="{}">
                                        <button type="button"
                                            x-on:click="const i=$el.nextElementSibling; const v=Math.round((parseFloat(i.value||0)-0.1)*10)/10; i.value=Math.max(0,v).toFixed(1); i.dispatchEvent(new Event('input',{bubbles:true}));"
                                            class="w-6 h-7 flex items-center justify-center rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-200 font-medium active:scale-95 transition-transform"
                                            @if ($isSaved) disabled @endif>−</button>
                                        <input type="number" step="0.1"
                                            @if ($judgeMin !== null) min="{{ $judgeMin }}" @else min="0" @endif
                                            @if ($judgeMax !== null) max="{{ $judgeMax }}" @endif
                                            wire:model="judgeScores.{{ $result->id }}.{{ $j }}"
                                            class="w-12 text-center rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-base py-0.5 px-0.5"
                                            placeholder="{{ $judgeMin ?? '0.0' }}"
                                            @if ($isSaved) disabled @endif />
                                        <button type="button"
                                            x-on:click="const i=$el.previousElementSibling; const v=Math.round((parseFloat(i.value||0)+0.1)*10)/10; i.value=Math.min(10,v).toFixed(1); i.dispatchEvent(new Event('input',{bubbles:true}));"
                                            class="w-6 h-7 flex items-center justify-center rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-200 font-medium active:scale-95 transition-transform"
                                            @if ($isSaved) disabled @endif>+</button>
                                    </div>
                                @endif
                            </td>
                        @endfor
                        <td class="py-2 pr-4">
                            <span class="font-semibold">
                                @if ($isSaved && $result->total_score !== null)
                                    {{ $hasCategories ? rtrim(rtrim(number_format((float) $result->total_score, 3), '0'), '.') : number_format((float) $result->total_score, 1) }}
                                @else
                                    —
                                @endif
                            </span>
                        </td>

                    @elseif ($method === 'win_loss')
                        <td class="py-2 pr-4">
                            @if ($isReadOnly)
                                <span class="text-base font-medium text-gray-700 dark:text-gray-300">
                                    {{ ucfirst($result->win_loss ?? '—') }}
                                </span>
                            @else
                                <div class="flex gap-1">
                                    <x-filament::button size="xs"
                                        color="{{ $result->win_loss === 'win' ? 'success' : 'gray' }}"
                                        wire:click="saveWinLoss({{ $result->id }}, 'win')"
                                        wire:loading.attr="disabled" wire:target="saveWinLoss({{ $result->id }}, 'win')">W</x-filament::button>
                                    <x-filament::button size="xs"
                                        color="{{ $result->win_loss === 'loss' ? 'danger' : 'gray' }}"
                                        wire:click="saveWinLoss({{ $result->id }}, 'loss')"
                                        wire:loading.attr="disabled" wire:target="saveWinLoss({{ $result->id }}, 'loss')">L</x-filament::button>
                                    <x-filament::button size="xs"
                                        color="{{ $result->win_loss === 'draw' ? 'warning' : 'gray' }}"
                                        wire:click="saveWinLoss({{ $result->id }}, 'draw')"
                                        wire:loading.attr="disabled" wire:target="saveWinLoss({{ $result->id }}, 'draw')">D</x-filament::button>
                                </div>
                            @endif
                        </td>

                    @elseif (in_array($method, ['first_to_n', 'timed_points']))
                        @php
                            $atTarget  = $method === 'first_to_n' && $targetScore !== null && (int) ($result->total_score ?? 0) >= $targetScore;
                            $hasEvents = $result->scoreEvents()->exists();
                        @endphp
                        <td class="py-2 pr-4">
                            @if ($isReadOnly)
                                <span class="text-base font-medium text-gray-700 dark:text-gray-300">
                                    {{ $result->total_score !== null ? (int) $result->total_score : '—' }}
                                </span>
                            @else
                                <div class="flex items-center gap-1 flex-wrap">
                                    <span class="text-sm font-semibold {{ $atTarget ? 'inline-block bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300 rounded px-1 winner-halo' : 'text-gray-700 dark:text-gray-200' }} w-8 text-right tabular-nums">
                                        {{ (int) ($result->total_score ?? 0) }}
                                    </span>
                                    @foreach ($incrementButtons as $btn)
                                        <button type="button"
                                            wire:click="addPoints({{ $result->id }}, {{ $btn }})"
                                            @if ($atTarget) disabled @endif
                                            class="h-7 px-2 flex items-center justify-center rounded text-sm font-semibold shadow-sm transition-transform {{ $atTarget ? 'bg-gray-100 dark:bg-gray-800 text-gray-300 dark:text-gray-600 cursor-not-allowed' : 'bg-primary-600 dark:bg-primary-500 text-white active:scale-95' }}">
                                            +{{ $btn }}
                                        </button>
                                    @endforeach
                                    <button type="button"
                                        wire:click="undoPoints({{ $result->id }})"
                                        @if (! $hasEvents) disabled @endif
                                        class="h-7 px-2 flex items-center justify-center rounded border text-xs transition-transform {{ $hasEvents ? 'border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300 active:scale-95' : 'border-gray-200 dark:border-gray-700 text-gray-300 dark:text-gray-600 cursor-not-allowed' }}">
                                        <x-heroicon-m-arrow-uturn-left class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            @endif
                        </td>
                    @endif

                    <td class="py-2 pr-4">
                        @if (! $isReadOnly && $this->placementOverrideMode)
                            <select wire:model="placementInput.{{ $result->id }}"
                                wire:change="overridePlacement({{ $result->id }})"
                                class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-base text-gray-900 dark:text-white px-1 py-0.5 w-14">
                                <option value="">—</option>
                                @for ($p = 1; $p <= $rows->count(); $p++)
                                    @if (! in_array($p, $usedPlacements) || $result->placement == $p)
                                        <option value="{{ $p }}" {{ $result->placement == $p ? 'selected' : '' }}>{{ $p }}</option>
                                    @endif
                                @endfor
                            </select>
                        @else
                            @if ($result->placement && (
                                $method === 'win_loss' ||
                                (in_array($method, ['judges_total', 'judges_average']) && ($result->total_score !== null || $result->placement_overridden)) ||
                                (in_array($method, ['first_to_n', 'timed_points']) && $result->total_score !== null)
                            ))
                                <span class="font-bold {{ $result->placement_overridden ? 'text-warning-600' : '' }}">
                                    @if ($result->placement <= $awardedCap)
                                        @switch($result->placement)
                                            @case(1) <span class="text-2xl leading-none">🥇</span> @break
                                            @case(2) <span class="text-2xl leading-none">🥈</span> @break
                                            @case(3) <span class="text-2xl leading-none">🥉</span> @break
                                            @default {{ $result->placement }}
                                        @endswitch
                                    @else
                                        <span class="text-sm font-medium text-gray-400 dark:text-gray-500">#{{ $result->placement }}</span>
                                    @endif
                                    @if ($result->placement_overridden)
                                        <span class="text-xs font-normal">(ov)</span>
                                    @endif
                                </span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        @endif
                    </td>

                    @if (! $isReadOnly)
                        <td class="py-2">
                            <div class="flex flex-col gap-1 items-start" x-data="{ saving: false }">
                                @if (! in_array($method, ['judges_total', 'judges_average']))
                                    @php
                                        $dtOncePenalties = array_filter($enabledPenalties, fn ($t) => ! in_array($t, ['deduction', 'opponent_point']));
                                        $dtWarnCount     = $this->getWarnCount($result->id);
                                        $dtCanUndo       = $this->hasUndoablePenalty($result->id);
                                    @endphp
                                    @foreach ($dtOncePenalties as $pType)
                                        <button type="button"
                                            wire:click="openPenaltyModal({{ $result->id }}, '{{ $pType }}')"
                                            class="px-1.5 py-0.5 rounded text-xs font-medium border {{ in_array($pType, ['dq','forfeit']) ? 'border-danger-300 dark:border-danger-700 bg-danger-50 dark:bg-danger-900/20 text-danger-700 dark:text-danger-400' : 'border-warning-300 dark:border-warning-700 bg-warning-50 dark:bg-warning-900/20 text-warning-700 dark:text-warning-400' }} active:scale-95 transition-transform">
                                            @if ($pType === 'warn' && $dtWarnCount > 0)Warn {{ $dtWarnCount }}@else{{ $this->getPenaltyLabel($pType) }}@endif
                                        </button>
                                    @endforeach
                                    @if ($dtCanUndo)
                                        <button type="button" wire:click="undoPenalty({{ $result->id }})"
                                            class="px-1.5 py-0.5 rounded text-xs border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 active:scale-95 transition-transform">
                                            <x-heroicon-m-arrow-uturn-left class="inline w-3 h-3" />
                                        </button>
                                    @endif
                                @endif
                                @if (! $dqViaPenalties && ! in_array($method, ['judges_total', 'judges_average']))
                                    <x-filament::button size="xs"
                                        color="{{ $result->disqualified ? 'gray' : 'danger' }}"
                                        wire:click="toggleDisqualify({{ $result->id }})">
                                        {{ $result->disqualified ? 'Un-DQ' : 'DQ' }}
                                    </x-filament::button>
                                @endif
                                @if (in_array($method, ['judges_total', 'judges_average']))
                                    @if ($isSaved)
                                        <span class="text-xs font-medium text-success-600 dark:text-success-400">Saved</span>
                                        <x-filament::button size="xs" color="gray"
                                            wire:click="undoJudgeScores({{ $result->id }})"
                                            :disabled="$inTiebreakerFlow">
                                            Undo
                                        </x-filament::button>
                                    @else
                                        <x-filament::button size="xs" color="success"
                                            x-bind:disabled="saving"
                                            x-on:click="saving=true; const d={}; ($el.closest('[data-scoring-key=row-{{ $result->id }}]')?.querySelectorAll('input[data-cat-j]')||[]).forEach(i=>{if(!d[i.dataset.catJ])d[i.dataset.catJ]={};d[i.dataset.catJ][i.dataset.catId]=i.value;}); $wire.saveJudgeScores({{ $result->id }},d).finally(()=>saving=false)">
                                            <span x-show="saving" class="inline-block w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                                            Save
                                        </x-filament::button>
                                    @endif
                                @endif
                            </div>
                        </td>
                    @endif
                </tr>
                @if ($hasCategories)
                    @php $catMin = $judgeMin ?? 0; $catMax = $judgeMax; @endphp
                    @foreach ($categories as $cat)
                    <tr class="{{ $result->disqualified ? 'opacity-50' : '' }}">
                        <td class="py-1 pl-6 pr-4 text-xs text-gray-500 dark:text-gray-400">
                            {{ $cat->name }}@if ($categoryMode === 'weighted') <span class="text-gray-400 dark:text-gray-500">({{ number_format((float) $cat->weight, 0) }}%)</span>@endif
                        </td>
                        @for ($j = 1; $j <= $judges; $j++)
                            <td class="py-1 pr-1">
                                @if ($isReadOnly || $isSaved)
                                    <span class="text-sm {{ ($this->categoryScores[$result->id][$j][$cat->id] ?? null) ? 'text-gray-900 dark:text-white' : 'text-gray-400' }}">
                                        {{ $this->categoryScores[$result->id][$j][$cat->id] ?? '—' }}
                                    </span>
                                @else
                                    <div x-data="{}" class="flex items-center gap-0.5">
                                        <button type="button"
                                            x-on:click="const i=$el.nextElementSibling; const v=Math.round((parseFloat(i.value||{{ $catMin }})-0.1)*10)/10; i.value=Math.max({{ $catMin }},v).toFixed(1); i.dispatchEvent(new Event('input',{bubbles:true}));"
                                            class="w-6 h-7 flex items-center justify-center rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-200 font-medium active:scale-95 transition-transform"
                                            @if ($isSaved) disabled @endif>−</button>
                                        <input type="number" step="0.1"
                                            min="{{ $catMin }}"
                                            @if ($catMax !== null) max="{{ $catMax }}" @endif
                                            wire:model.blur="categoryScores.{{ $result->id }}.{{ $j }}.{{ $cat->id }}"
                                            data-cat-j="{{ $j }}" data-cat-id="{{ $cat->id }}"
                                            class="w-12 text-center rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-base py-0.5 px-0.5"
                                            placeholder="{{ number_format($catMin, 1) }}"
                                            @if ($isSaved) disabled @endif />
                                        <button type="button"
                                            x-on:click="const i=$el.previousElementSibling; const v=Math.round((parseFloat(i.value||{{ $catMin }})+0.1)*10)/10; i.value={{ $catMax !== null ? 'Math.min('.$catMax.',v)' : 'v' }}.toFixed(1); i.dispatchEvent(new Event('input',{bubbles:true}));"
                                            class="w-6 h-7 flex items-center justify-center rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-200 font-medium active:scale-95 transition-transform"
                                            @if ($isSaved) disabled @endif>+</button>
                                    </div>
                                @endif
                            </td>
                        @endfor
                        <td class="py-1"></td>{{-- total --}}
                        <td class="py-1"></td>{{-- place --}}
                        @if (! $isReadOnly)<td class="py-1"></td>@endif{{-- actions --}}
                    </tr>
                    @endforeach
                @endif
                </tbody>
            @endforeach
        </table>
    </div>

    @endif

    @php
        $isAllScored = in_array($method, ['judges_total', 'judges_average'])
            && $rows->isNotEmpty()
            && $rows->every(fn ($r) => $r->result->disqualified || $r->result->forfeited || $r->result->total_score !== null)
            && $rows->filter(fn ($r) => ! $r->result->disqualified && ! $r->result->forfeited)->isNotEmpty();

        $tiebreakerPending = false;
        if ($isAllScored) {
            $cumPos = 0;
            foreach (
                $rows->filter(fn ($r) => ! $r->result->disqualified && ! $r->result->forfeited && $r->result->total_score !== null)
                     ->sortByDesc(fn ($r) => (float) $r->result->total_score)
                     ->groupBy(fn ($r) => (string) $r->result->total_score)
                as $tg
            ) {
                $startPos = $cumPos + 1;
                if ($tg->count() > 1 && $startPos <= $awardedCap) {
                    $notOverridden = $tg->filter(fn ($r) => ! $r->result->placement_overridden);
                    if ($notOverridden->some(fn ($r) => $r->result->tiebreaker_score === null)
                        || $notOverridden->pluck('result.tiebreaker_score')->unique()->count() < $notOverridden->count()
                    ) {
                        $tiebreakerPending = true;
                        break;
                    }
                }
                $cumPos += $tg->count();
            }
        }

        $standingsRows = $rows
            ->filter(fn ($r) => $r->result->placement !== null && $r->result->placement <= $awardedCap)
            ->sortBy(fn ($r) => $r->result->placement)
            ->values();
    @endphp
    @if ($isAllScored && ! $tiebreakerPending && $standingsRows->isNotEmpty())
        <div class="mt-4 rounded-lg border border-success-300 dark:border-success-700 bg-success-50 dark:bg-success-900/20 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wider text-success-700 dark:text-success-400 mb-2">Results</p>
            @foreach ($standingsRows as $row)
                @php $p = $row->result->placement; @endphp
                <p class="flex items-center gap-2 {{ $loop->first ? 'text-sm font-semibold text-gray-900 dark:text-white' : 'text-base text-gray-700 dark:text-gray-300 mt-1' }}">
                    @switch($p)
                        @case(1) <span class="text-2xl leading-none">🥇</span> @break
                        @case(2) <span class="text-2xl leading-none">🥈</span> @break
                        @case(3) <span class="text-2xl leading-none">🥉</span> @break
                        @default <span class="text-base font-bold text-gray-500 dark:text-gray-400">#{{ $p }}</span>
                    @endswitch
                    {{ $row->name }}
                </p>
            @endforeach
        </div>
    @endif

    <div class="h-0 overflow-hidden">
        <x-filament::modal id="confirm-reset-scores" width="sm">
            <x-slot name="heading">Reset scores?</x-slot>
            <x-slot name="description">All judge scores and placements will be cleared.</x-slot>
            <x-slot name="footerActions">
                <x-filament::button color="danger"
                    wire:click="resetJudgeScores"
                    x-on:click="$dispatch('close-modal', { id: 'confirm-reset-scores' })">
                    Yes, reset
                </x-filament::button>
                <x-filament::button color="gray"
                    x-on:click="$dispatch('close-modal', { id: 'confirm-reset-scores' })">
                    Cancel
                </x-filament::button>
            </x-slot>
        </x-filament::modal>
    </div>
</div>
