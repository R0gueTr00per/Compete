<div>
@php
    $method           = $this->getScoringMethod();
    $judges           = $this->getJudgeCount();
    $categories       = $this->getScoreCategories();
    $hasCategories    = $categories->isNotEmpty();
    $categoryMode     = $div->competitionEvent?->score_category_mode ?? 'single';
    $judgeMin         = $div->competitionEvent?->min_score;
    $judgeMax         = $div->competitionEvent?->max_score;
    $isReadOnly       = $div->status === 'complete';
    $defaultScore     = $div->competitionEvent?->default_score;
    $highLowDrop      = $div->competitionEvent->high_low_drop ?? false;
    $enabledPenalties = $this->getEnabledPenaltyTypes();

    $tiedGroups = $this->getTiedGroups();
@endphp

@if (! $this->isRoundRobin())

    {{-- Active tiebreaker scoring --}}
    @if ($tiedGroups->isNotEmpty() && ! $isReadOnly)
        @php
            $ordinals = [1 => '1st', 2 => '2nd', 3 => '3rd'];
            if ($tiedGroups->count() === 1) {
                $tg0        = $tiedGroups->first();
                $sp         = $tg0->starting_position;
                $ep         = $sp + $tg0->group->count() - 1;
                $titlePlace = ($ordinals[$sp] ?? "{$sp}th") . ($sp !== $ep ? '/' . ($ordinals[$ep] ?? "{$ep}th") : '');
            }
        @endphp
        <div class="mt-4 rounded-lg border border-warning-300 dark:border-warning-700 bg-warning-50 dark:bg-warning-900/20 p-4">
            <p class="text-sm font-semibold text-warning-800 dark:text-warning-300 mb-3">
                ⚡ Sudden Death Required
                @if ($tiedGroups->count() === 1)
                    — determine {{ $titlePlace }} place
                @else
                    — {{ $tiedGroups->count() }} ties detected
                @endif
            </p>

            @foreach ($tiedGroups as $tiedGroup)
                @php
                    $group            = $tiedGroup->group;
                    $startingPosition = $tiedGroup->starting_position;
                    $endPosition      = $startingPosition + $group->count() - 1;
                    $startLabel       = $ordinals[$startingPosition] ?? "{$startingPosition}th";
                    $endLabel         = $ordinals[$endPosition]      ?? "{$endPosition}th";
                    $placeLabel       = $startingPosition === $endPosition ? $startLabel : "{$startLabel}/{$endLabel}";
                    $tbSortedGroup    = $group->sortByDesc(fn ($r) => (float) ($r->result->tiebreaker_score ?? PHP_INT_MIN))->values();
                @endphp
                <div class="mb-4">
                    @if ($tiedGroups->count() > 1)
                        <p class="text-xs font-semibold text-warning-700 dark:text-warning-400 mb-1">
                            Determine {{ $placeLabel }} place
                        </p>
                    @endif
                    <p class="text-xs font-medium text-warning-700 dark:text-warning-400 mb-2">
                        Tied at {{ number_format((float) $group->first()->result->total_score, 1) }}:
                        {{ $group->pluck('name')->join(', ') }}
                    </p>

                    {{-- Mobile: one card per tied competitor --}}
                    <div class="sm:hidden space-y-2">
                        @foreach ($group as $row)
                            @php
                                $result    = $row->result;
                                $tbSaved   = $result->tiebreaker_score !== null || $result->placement_overridden;
                                $tbDisplay = $result->tiebreaker_score !== null ? number_format((float) $result->tiebreaker_score, 1) : '—';
                            @endphp
                            <div wire:key="tb-mobile-{{ $result->id }}"
                                 data-scoring-key="tb-{{ $result->id }}"
                                 x-data="{ open: false, saving: false, tbJ: {{ Js::from(collect(range(1,$judges))->mapWithKeys(fn($j)=>[(string)$j=>$this->tbPendingFlat[$result->id][$j] ?? ($defaultScore !== null ? number_format((float)$defaultScore,1) : null)])->all()) }} }"
                                 class="rounded-lg border border-warning-200 dark:border-warning-700 bg-white dark:bg-gray-900">

                                <div class="px-3 py-3 flex items-center gap-2">
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium text-sm text-gray-900 dark:text-white truncate">{{ $row->name }}</p>
                                        @if ($row->info)
                                            <p class="text-xs text-gray-400 dark:text-gray-500">{{ $row->info }}</p>
                                        @endif
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                            @if ($result->placement_overridden)
                                                <span class="text-warning-600 dark:text-warning-400">Place assigned (ov)</span>
                                            @elseif ($tbSaved)
                                                Total: <strong>{{ $tbDisplay }}</strong>
                                                · <span class="text-success-600 dark:text-success-400">Saved</span>
                                            @else
                                                <span class="text-gray-400 dark:text-gray-500">Not yet saved</span>
                                            @endif
                                        </p>
                                    </div>

                                    @if ($tbSaved && $result->placement)
                                        <div class="shrink-0">
                                            @switch($result->placement)
                                                @case(1) <span class="text-3xl leading-none">🥇</span> @break
                                                @case(2) <span class="text-3xl leading-none">🥈</span> @break
                                                @case(3) <span class="text-3xl leading-none">🥉</span> @break
                                                @default <span class="text-base font-bold text-gray-500 dark:text-gray-400">#{{ $result->placement }}</span>
                                            @endswitch
                                            @if ($result->placement_overridden)
                                                <span class="text-xs text-warning-600">(ov)</span>
                                            @endif
                                        </div>
                                    @elseif ($tbSaved && $result->tiebreaker_score !== null)
                                        @php $tbPosMob = $startingPosition + $tbSortedGroup->search(fn ($r) => $r->result->id === $result->id); @endphp
                                        <div class="shrink-0">
                                            <span class="text-base font-bold text-gray-500 dark:text-gray-400">#{{ $tbPosMob }}</span>
                                        </div>
                                    @endif

                                    @if (! $isReadOnly)
                                        @if (! $tbSaved && ! $result->placement_overridden)
                                            @php $tbOncePenalties = array_filter($enabledPenalties, fn ($t) => in_array($t, ['dq', 'forfeit'])); @endphp
                                            @foreach ($tbOncePenalties as $pType)
                                                <button type="button"
                                                    wire:click="openPenaltyModal({{ $result->id }}, '{{ $pType }}')"
                                                    class="shrink-0 px-2 py-1 rounded text-xs font-medium border border-danger-300 dark:border-danger-700 bg-danger-50 dark:bg-danger-900/20 text-danger-700 dark:text-danger-400 active:scale-95 transition-transform">
                                                    {{ $this->getPenaltyLabel($pType) }}
                                                </button>
                                            @endforeach
                                            @if (! in_array('dq', $enabledPenalties) && ! in_array('forfeit', $enabledPenalties))
                                                <button type="button"
                                                    wire:click="toggleDisqualify({{ $result->id }})"
                                                    class="shrink-0 px-2 py-1 rounded text-xs font-medium border border-danger-300 dark:border-danger-700 bg-danger-50 dark:bg-danger-900/20 text-danger-700 dark:text-danger-400 active:scale-95 transition-transform">
                                                    {{ $result->disqualified ? 'Un-DQ' : 'DQ' }}
                                                </button>
                                            @endif
                                        @endif
                                        <div class="shrink-0">
                                            @if ($result->placement_overridden)
                                                {{-- locked by head judge --}}
                                            @elseif ($tbSaved)
                                                <x-filament::button size="xs" color="gray"
                                                    wire:click="clearTiebreakerScore({{ $result->id }})">Undo</x-filament::button>
                                            @else
                                                <button x-on:click="open = !open"
                                                    class="flex items-center gap-1.5 rounded-lg px-3 py-2 text-base font-medium bg-warning-50 text-warning-700 dark:bg-warning-900/30 dark:text-warning-400">
                                                    <x-heroicon-m-pencil-square class="w-4 h-4" />
                                                    <span x-show="!open">Score</span>
                                                    <span x-show="open" x-cloak>Close</span>
                                                </button>
                                            @endif
                                        </div>
                                    @endif
                                </div>

                                @if (! $isReadOnly && ! $tbSaved)
                                    <div x-show="open" x-transition
                                         class="border-t border-warning-100 dark:border-warning-900/40 px-3 pb-3 pt-3 space-y-3">
                                        @if ($hasCategories)
                                            @for ($j = 1; $j <= $judges; $j++)
                                                <div>
                                                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1.5">Judge {{ $j }}</p>
                                                    <div class="space-y-1.5">
                                                        @foreach ($categories as $cat)
                                                            @php $catMin = $judgeMin ?? 0; $catMax = $judgeMax; @endphp
                                                            <div class="flex items-center gap-2">
                                                                <span class="text-xs text-gray-500 dark:text-gray-400 w-28 shrink-0">{{ $cat->name }}@if ($categoryMode === 'weighted') <span class="text-gray-400">({{ $cat->weight }}%)</span>@endif</span>
                                                                <div class="flex items-center gap-1 flex-1" x-data="{}">
                                                                    <button type="button"
                                                                        x-on:click="const i=$el.nextElementSibling; const v=Math.round((parseFloat(i.value||{{ $catMin }})-0.1)*10)/10; i.value=Math.max({{ $catMin }},v).toFixed(1); i.dispatchEvent(new Event('input',{bubbles:true}));"
                                                                        class="w-11 h-11 flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-xl font-medium active:scale-95 transition-transform">−</button>
                                                                    <input type="number" step="0.1"
                                                                        min="{{ $catMin }}"
                                                                        @if ($catMax !== null) max="{{ $catMax }}" @endif
                                                                        value="{{ $this->tbPendingCat[$result->id][$j][$cat->id] ?? ($defaultScore !== null ? number_format((float)$defaultScore, 1) : '') }}"
                                                                        data-cat-j="{{ $j }}" data-cat-id="{{ $cat->id }}"
                                                                        class="flex-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-base py-2.5 px-3 text-center"
                                                                        placeholder="{{ number_format($catMin, 1) }}" />
                                                                    <button type="button"
                                                                        x-on:click="const i=$el.previousElementSibling; const v=Math.round((parseFloat(i.value||{{ $catMin }})+0.1)*10)/10; i.value={{ $catMax !== null ? 'Math.min('.$catMax.',v)' : 'v' }}.toFixed(1); i.dispatchEvent(new Event('input',{bubbles:true}));"
                                                                        class="w-11 h-11 flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-xl font-medium active:scale-95 transition-transform">+</button>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endfor
                                        @else
                                            @for ($j = 1; $j <= $judges; $j++)
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Judge {{ $j }}</label>
                                                    <div class="flex items-center gap-2">
                                                        <input type="number" step="0.1" min="0" max="10"
                                                            x-model="tbJ['{{ $j }}']"
                                                            class="flex-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-base py-2.5 px-3 text-center"
                                                            placeholder="0.0" />
                                                        <div class="flex gap-1 shrink-0">
                                                            <button type="button"
                                                                x-on:click="tbJ['{{ $j }}'] = Math.max(0, Math.round((parseFloat(tbJ['{{ $j }}']||0)-0.1)*10)/10).toFixed(1)"
                                                                class="w-11 h-11 flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-xl font-medium active:scale-95 transition-transform">−</button>
                                                            <button type="button"
                                                                x-on:click="tbJ['{{ $j }}'] = Math.min(10, Math.round((parseFloat(tbJ['{{ $j }}']||0)+0.1)*10)/10).toFixed(1)"
                                                                class="w-11 h-11 flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-xl font-medium active:scale-95 transition-transform">+</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endfor
                                        @endif

                                        <div class="pt-1">
                                            @if ($hasCategories)
                                                <x-filament::button color="success" class="w-full"
                                                    x-bind:disabled="saving"
                                                    x-on:click="saving=true; const r=$el.closest('[data-scoring-key=tb-{{ $result->id }}]'); const d={}; (r?.querySelectorAll('input[data-cat-j]')||[]).forEach(i=>{if(!d[i.dataset.catJ])d[i.dataset.catJ]={};d[i.dataset.catJ][i.dataset.catId]=i.value;}); $wire.saveTiebreakerScores({{ $result->id }},d).then(()=>open=false).finally(()=>saving=false)">
                                                    <span x-show="saving" class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                                                    Save scores
                                                </x-filament::button>
                                            @else
                                                <x-filament::button color="success" class="w-full"
                                                    x-bind:disabled="saving"
                                                    x-on:click="saving=true; $wire.saveTiebreakerScores({{ $result->id }}, tbJ).then(()=>open=false).finally(()=>saving=false)">
                                                    <span x-show="saving" class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                                                    Save scores
                                                </x-filament::button>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    {{-- Desktop: table --}}
                    <div class="hidden sm:block overflow-x-auto">
                        <table class="w-full text-base">
                            <thead>
                                <tr class="border-b border-warning-200 dark:border-warning-700 text-left text-xs font-medium text-warning-700 dark:text-warning-400 uppercase tracking-wide">
                                    <th class="pb-2 pr-4">Competitor</th>
                                    @for ($j = 1; $j <= $judges; $j++)
                                        <th class="pb-2 pr-1">J{{ $j }}</th>
                                    @endfor
                                    <th class="pb-2 pr-4">Total</th>
                                    <th class="pb-2 pr-4">Place</th>
                                    <th class="pb-2"></th>
                                </tr>
                            </thead>
                            @foreach ($group as $row)
                                @php
                                    $result    = $row->result;
                                    $tbSaved   = $result->tiebreaker_score !== null || $result->placement_overridden;
                                    $tbDisplay = $result->tiebreaker_score !== null
                                        ? number_format((float) $result->tiebreaker_score, 1)
                                        : '—';
                                    $tbDroppedMinJ = null; $tbDroppedMaxJ = null;
                                    if ($highLowDrop && $tbSaved) {
                                        $tbJTotals = $result->judgeScores
                                            ->where('is_tiebreaker', true)
                                            ->pluck('score', 'judge_number')
                                            ->map(fn ($s) => (float) $s)
                                            ->all();
                                        if (count($tbJTotals) >= 3) {
                                            $tbMinV = min($tbJTotals); $tbMaxV = max($tbJTotals);
                                            foreach ($tbJTotals as $jj => $v) { if ($tbDroppedMinJ === null && $v == $tbMinV) { $tbDroppedMinJ = $jj; break; } }
                                            foreach ($tbJTotals as $jj => $v) { if ($tbDroppedMaxJ === null && $v == $tbMaxV && $jj !== $tbDroppedMinJ) { $tbDroppedMaxJ = $jj; break; } }
                                        }
                                    }
                                @endphp
                                <tbody class="border-b border-warning-100 dark:border-warning-900/40 last:border-b-0"
                                    x-data="{ saving: false, tbJ: {{ Js::from(collect(range(1,$judges))->mapWithKeys(fn($j)=>[(string)$j=>$this->tbPendingFlat[$result->id][$j] ?? ($defaultScore !== null ? number_format((float)$defaultScore,1) : null)])->all()) }} }">
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <div class="font-medium text-gray-900 dark:text-white">{{ $row->name }}</div>
                                            @if ($row->info)
                                                <div class="text-xs text-gray-400 dark:text-gray-500">{{ $row->info }}</div>
                                            @endif
                                            @if (! $isReadOnly && ! $tbSaved)
                                                @php $tbDtPenalties = array_filter($enabledPenalties, fn ($t) => in_array($t, ['dq', 'forfeit'])); @endphp
                                                @if (! empty($tbDtPenalties))
                                                    <div class="mt-1 flex flex-wrap gap-1">
                                                        @foreach ($tbDtPenalties as $pType)
                                                            <button type="button"
                                                                wire:click="openPenaltyModal({{ $result->id }}, '{{ $pType }}')"
                                                                class="px-1.5 py-0.5 rounded text-xs font-medium border border-danger-300 dark:border-danger-700 bg-danger-50 dark:bg-danger-900/20 text-danger-700 dark:text-danger-400 active:scale-95 transition-transform">
                                                                {{ $this->getPenaltyLabel($pType) }}
                                                            </button>
                                                        @endforeach
                                                    </div>
                                                @elseif (! in_array('dq', $enabledPenalties) && ! in_array('forfeit', $enabledPenalties))
                                                    <div class="mt-1">
                                                        <button type="button"
                                                            wire:click="toggleDisqualify({{ $result->id }})"
                                                            class="px-1.5 py-0.5 rounded text-xs font-medium border border-danger-300 dark:border-danger-700 bg-danger-50 dark:bg-danger-900/20 text-danger-700 dark:text-danger-400 active:scale-95 transition-transform">
                                                            {{ $result->disqualified ? 'Un-DQ' : 'DQ' }}
                                                        </button>
                                                    </div>
                                                @endif
                                            @endif
                                        </td>
                                        @for ($j = 1; $j <= $judges; $j++)
                                            @php $tbIsDropped = $highLowDrop && ($j === $tbDroppedMinJ || $j === $tbDroppedMaxJ); @endphp
                                            <td class="py-2 pr-2">
                                                @if ($isReadOnly || $tbSaved)
                                                    @php $tbJScore = $result->judgeScores->where('is_tiebreaker', true)->where('judge_number', $j)->first(); @endphp
                                                    <span class="text-base {{ $tbIsDropped ? 'line-through text-gray-400 dark:text-gray-500' : 'text-gray-700 dark:text-gray-300' }} {{ ($tbSaved && ! $tbIsDropped) ? 'opacity-50' : '' }}">
                                                        {{ $tbJScore ? number_format((float) $tbJScore->score, 1) : '—' }}
                                                    </span>
                                                @elseif ($hasCategories)
                                                    <span class="text-base text-gray-500 dark:text-gray-400">—</span>
                                                @else
                                                    <div class="flex items-center gap-1">
                                                        <button type="button"
                                                            x-on:click="tbJ['{{ $j }}'] = Math.max(0, Math.round((parseFloat(tbJ['{{ $j }}']||0)-0.1)*10)/10).toFixed(1)"
                                                            class="w-7 h-7 flex items-center justify-center rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-200 font-medium active:scale-95 transition-transform">−</button>
                                                        <input type="number" step="0.1" min="0" max="10"
                                                            x-model="tbJ['{{ $j }}']"
                                                            class="w-[3.25rem] text-center rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-base py-0.5 px-1"
                                                            placeholder="0.0" />
                                                        <button type="button"
                                                            x-on:click="tbJ['{{ $j }}'] = Math.min(10, Math.round((parseFloat(tbJ['{{ $j }}']||0)+0.1)*10)/10).toFixed(1)"
                                                            class="w-7 h-7 flex items-center justify-center rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-200 font-medium active:scale-95 transition-transform">+</button>
                                                    </div>
                                                @endif
                                            </td>
                                        @endfor
                                        <td class="py-2 pr-4">
                                            <span class="font-semibold">{{ $tbDisplay }}</span>
                                        </td>
                                        <td class="py-2 pr-4">
                                            @if ($result->placement)
                                                @switch($result->placement)
                                                    @case(1) <span class="text-2xl leading-none">🥇</span> @break
                                                    @case(2) <span class="text-2xl leading-none">🥈</span> @break
                                                    @case(3) <span class="text-2xl leading-none">🥉</span> @break
                                                    @default <span class="{{ $result->placement_overridden ? 'text-warning-600' : '' }}">{{ $result->placement }}</span>
                                                @endswitch
                                                @if ($result->placement_overridden)
                                                    <span class="text-xs font-normal text-warning-600">(ov)</span>
                                                @endif
                                            @elseif ($tbSaved && $result->tiebreaker_score !== null)
                                                @php $tbPos = $startingPosition + $tbSortedGroup->search(fn ($r) => $r->result->id === $result->id); @endphp
                                                <span class="text-gray-500 dark:text-gray-400">{{ $tbPos }}</span>
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                        @if (! $isReadOnly)
                                            <td class="py-2">
                                                @if ($result->placement_overridden)
                                                    <x-filament::button size="xs" color="gray" disabled>Undo</x-filament::button>
                                                @elseif ($tbSaved)
                                                    <x-filament::button size="xs" color="gray"
                                                        wire:click="clearTiebreakerScore({{ $result->id }})">Undo</x-filament::button>
                                                @else
                                                    @if ($hasCategories)
                                                        <x-filament::button size="xs" color="success"
                                                            x-bind:disabled="saving"
                                                            x-on:click="saving=true; const r=$el.closest('tbody'); const d={}; (r?.querySelectorAll('input[data-cat-j]')||[]).forEach(i=>{if(!d[i.dataset.catJ])d[i.dataset.catJ]={};d[i.dataset.catJ][i.dataset.catId]=i.value;}); $wire.saveTiebreakerScores({{ $result->id }},d).finally(()=>saving=false)">
                                                            <span x-show="saving" class="inline-block w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                                                            Save
                                                        </x-filament::button>
                                                    @else
                                                        <x-filament::button size="xs" color="success"
                                                            x-bind:disabled="saving"
                                                            x-on:click="saving=true; $wire.saveTiebreakerScores({{ $result->id }}, tbJ).finally(()=>saving=false)">
                                                            <span x-show="saving" class="inline-block w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                                                            Save
                                                        </x-filament::button>
                                                    @endif
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                    @if ($hasCategories && ! $isReadOnly && ! $tbSaved)
                                        @php $catMin = $judgeMin ?? 0; $catMax = $judgeMax; @endphp
                                        @foreach ($categories as $cat)
                                            <tr>
                                                <td class="py-1 pl-4 pr-4 text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $cat->name }}@if ($categoryMode === 'weighted') <span class="text-gray-400 dark:text-gray-500">({{ number_format((float) $cat->weight, 0) }}%)</span>@endif
                                                </td>
                                                @for ($j = 1; $j <= $judges; $j++)
                                                    <td class="py-1 pr-1">
                                                        <div x-data="{}" class="flex items-center gap-0.5">
                                                            <button type="button"
                                                                x-on:click="const i=$el.nextElementSibling; const v=Math.round((parseFloat(i.value||{{ $catMin }})-0.1)*10)/10; i.value=Math.max({{ $catMin }},v).toFixed(1); i.dispatchEvent(new Event('input',{bubbles:true}));"
                                                                class="w-6 h-7 flex items-center justify-center rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-200 font-medium active:scale-95 transition-transform">−</button>
                                                            <input type="number" step="0.1"
                                                                min="{{ $catMin }}"
                                                                @if ($catMax !== null) max="{{ $catMax }}" @endif
                                                                value="{{ $this->tbPendingCat[$result->id][$j][$cat->id] ?? ($defaultScore !== null ? number_format((float)$defaultScore, 1) : '') }}"
                                                                data-cat-j="{{ $j }}" data-cat-id="{{ $cat->id }}"
                                                                class="w-9 text-center rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-base py-0.5 px-0.5"
                                                                placeholder="{{ number_format($catMin, 1) }}" />
                                                            <button type="button"
                                                                x-on:click="const i=$el.previousElementSibling; const v=Math.round((parseFloat(i.value||{{ $catMin }})+0.1)*10)/10; i.value={{ $catMax !== null ? 'Math.min('.$catMax.',v)' : 'v' }}.toFixed(1); i.dispatchEvent(new Event('input',{bubbles:true}));"
                                                                class="w-6 h-7 flex items-center justify-center rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-200 font-medium active:scale-95 transition-transform">+</button>
                                                        </div>
                                                    </td>
                                                @endfor
                                                <td class="py-1"></td>
                                                <td class="py-1"></td>
                                                @if (! $isReadOnly)<td class="py-1"></td>@endif
                                            </tr>
                                        @endforeach
                                    @endif
                                </tbody>
                            @endforeach
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Read-only: tiebreaker scores already recorded --}}
    @php
        $withTiebreaker = $this->competitorRows
            ->filter(fn ($row) => $row->result->tiebreaker_score !== null);
        $stillTied = $this->getStillTiedAfterTiebreaker();
    @endphp
    @if ($withTiebreaker->isNotEmpty() && $isReadOnly)
        @php
            $tbReadOnlyGroups = $withTiebreaker
                ->groupBy(fn ($row) => (string) $row->result->total_score)
                ->sortByDesc(fn ($group, $key) => (float) $key);
            $tbOrdinals = [1 => '1st', 2 => '2nd', 3 => '3rd'];
        @endphp
        @foreach ($tbReadOnlyGroups as $tbTotalScore => $tbGroup)
            @php
                $tbMinPlace  = $tbGroup->min(fn ($row) => $row->result->placement ?? 99);
                $tbMaxPlace  = $tbGroup->max(fn ($row) => $row->result->placement ?? 99);
                $tbPlaceStr  = ($tbOrdinals[$tbMinPlace] ?? "{$tbMinPlace}th")
                    . ($tbMinPlace !== $tbMaxPlace ? '/' . ($tbOrdinals[$tbMaxPlace] ?? "{$tbMaxPlace}th") : '');
            @endphp
            <div class="mt-4 rounded-lg border border-warning-300 dark:border-warning-700 bg-warning-50 dark:bg-warning-900/20 p-4">
                <p class="text-sm font-semibold text-warning-800 dark:text-warning-300 mb-1">
                    ⚡ Sudden Death — determine {{ $tbPlaceStr }} place
                </p>
                <p class="text-xs font-medium text-warning-700 dark:text-warning-400 mb-3">
                    Tied at {{ number_format((float) $tbTotalScore, 1) }}:
                    {{ $tbGroup->pluck('name')->join(', ') }}
                </p>
                <div class="overflow-x-auto">
                    <table class="w-full text-base">
                        <thead>
                            <tr class="border-b border-warning-200 dark:border-warning-700 text-left text-xs font-medium text-warning-700 dark:text-warning-400 uppercase tracking-wide">
                                <th class="pb-2 pr-4">Competitor</th>
                                @for ($j = 1; $j <= $judges; $j++)
                                    <th class="pb-2 pr-1">J{{ $j }}</th>
                                @endfor
                                <th class="pb-2 pr-4">Total</th>
                                <th class="pb-2 pr-4">Place</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-warning-100 dark:divide-warning-900/40">
                            @foreach ($tbGroup->sortByDesc(fn ($row) => (float) $row->result->tiebreaker_score) as $tbRow)
                                @php
                                    $roDroppedMinJ = null; $roDroppedMaxJ = null;
                                    if ($highLowDrop) {
                                        $roJTotals = $tbRow->result->judgeScores
                                            ->where('is_tiebreaker', true)
                                            ->pluck('score', 'judge_number')
                                            ->map(fn ($s) => (float) $s)
                                            ->all();
                                        if (count($roJTotals) >= 3) {
                                            $roMinV = min($roJTotals); $roMaxV = max($roJTotals);
                                            foreach ($roJTotals as $jj => $v) { if ($roDroppedMinJ === null && $v == $roMinV) { $roDroppedMinJ = $jj; break; } }
                                            foreach ($roJTotals as $jj => $v) { if ($roDroppedMaxJ === null && $v == $roMaxV && $jj !== $roDroppedMinJ) { $roDroppedMaxJ = $jj; break; } }
                                        }
                                    }
                                @endphp
                                <tr>
                                    <td class="py-2 pr-4">
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $tbRow->name }}</div>
                                        @if ($tbRow->info)
                                            <div class="text-xs text-gray-400 dark:text-gray-500">{{ $tbRow->info }}</div>
                                        @endif
                                    </td>
                                    @for ($j = 1; $j <= $judges; $j++)
                                        @php
                                            $roIsDropped = $highLowDrop && ($j === $roDroppedMinJ || $j === $roDroppedMaxJ);
                                            $tbJScore    = $tbRow->result->judgeScores->where('is_tiebreaker', true)->where('judge_number', $j)->first();
                                        @endphp
                                        <td class="py-2 pr-2">
                                            <span class="text-base {{ $roIsDropped ? 'line-through text-gray-400 dark:text-gray-500' : 'text-gray-700 dark:text-gray-300' }}">
                                                {{ $tbJScore ? number_format((float) $tbJScore->score, 1) : '—' }}
                                            </span>
                                        </td>
                                    @endfor
                                    <td class="py-2 pr-4">
                                        <span class="font-semibold">{{ number_format((float) $tbRow->result->tiebreaker_score, 1) }}</span>
                                    </td>
                                    <td class="py-2 pr-4">
                                        @if ($tbRow->result->placement)
                                            @switch($tbRow->result->placement)
                                                @case(1) <span class="text-2xl leading-none">🥇</span> @break
                                                @case(2) <span class="text-2xl leading-none">🥈</span> @break
                                                @case(3) <span class="text-2xl leading-none">🥉</span> @break
                                                @default {{ $tbRow->result->placement }}
                                            @endswitch
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    @endif

    {{-- Head judge override when tiebreaker also ties --}}
    @if ($stillTied->isNotEmpty())
        <div class="mt-3 rounded-lg border border-danger-300 dark:border-danger-700 bg-danger-50 dark:bg-danger-900/20 p-4">
            <p class="text-sm font-semibold text-danger-800 dark:text-danger-300 mb-1">
                Still tied after sudden death — head judge decides
            </p>
            @if (! $isReadOnly)
            <p class="text-xs text-danger-600 dark:text-danger-400 mb-3">
                Select a place for each competitor, then press <strong>Save</strong>. All places must be saved before the division can be marked complete.
            </p>
            @endif
            @foreach ($stillTied as $group)
                @php
                    $groupTotalScore = (float) $group->first()->result->total_score;
                    $groupTbScore    = (float) $group->first()->result->tiebreaker_score;
                    $groupIds        = $group->map(fn ($r) => $r->result->id)->all();
                    $startPos        = $this->competitorRows
                        ->filter(fn ($r) => ! $r->result->disqualified && ! in_array($r->result->id, $groupIds))
                        ->filter(fn ($r) => $r->result->total_score !== null && (
                            (float) $r->result->total_score > $groupTotalScore
                            || ((float) $r->result->total_score === $groupTotalScore
                                && (float) ($r->result->tiebreaker_score ?? PHP_INT_MIN) > $groupTbScore)
                        ))
                        ->count() + 1;
                    $endPos = $startPos + $group->count() - 1;
                @endphp
                <p class="text-xs font-medium text-danger-700 dark:text-danger-400 mb-2">
                    Tied: {{ $group->pluck('name')->join(' vs ') }}
                </p>
                <div class="space-y-2">
                    @foreach ($group as $row)
                        @php
                            $isOverridden         = $row->result->placement_overridden;
                            $otherGroupPlacements = $group
                                ->filter(fn ($r) => $r->result->id !== $row->result->id && $r->result->placement_overridden)
                                ->map(fn ($r) => $r->result->placement)
                                ->filter()
                                ->values()
                                ->all();
                            $currentVal = isset($this->placementInput[$row->result->id]) && $this->placementInput[$row->result->id] !== ''
                                ? (int) $this->placementInput[$row->result->id] : null;
                        @endphp
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-900 dark:text-white min-w-0 flex-1 truncate">{{ $row->name }}</span>
                            @if ($isOverridden)
                                <span class="shrink-0 flex items-center gap-1">
                                    @switch($row->result->placement)
                                        @case(1) <span class="text-2xl leading-none">🥇</span> @break
                                        @case(2) <span class="text-2xl leading-none">🥈</span> @break
                                        @case(3) <span class="text-2xl leading-none">🥉</span> @break
                                        @default <span class="text-base font-bold text-gray-500 dark:text-gray-400">#{{ $row->result->placement }}</span>
                                    @endswitch
                                    <span class="text-xs text-warning-600 dark:text-warning-400">(ov)</span>
                                </span>
                                @if (! $isReadOnly)
                                    <x-filament::button size="xs" color="gray"
                                        wire:click="headJudgeUndoPlacement({{ $row->result->id }})">
                                        Undo
                                    </x-filament::button>
                                @endif
                            @else
                                <select wire:model="placementInput.{{ $row->result->id }}"
                                    class="shrink-0 rounded border border-warning-300 dark:border-warning-600 bg-white dark:bg-gray-900 text-base text-gray-900 dark:text-white pl-2 pr-7 py-1.5">
                                    <option value="">— Place</option>
                                    @for ($p = $startPos; $p <= $endPos; $p++)
                                        @if (! in_array($p, $otherGroupPlacements) || $currentVal === $p)
                                            <option value="{{ $p }}" {{ $currentVal === $p ? 'selected' : '' }}>{{ $p }}</option>
                                        @endif
                                    @endfor
                                </select>
                                <x-filament::button size="xs" color="warning"
                                    wire:click="headJudgeSavePlacement({{ $row->result->id }})">
                                    Save
                                </x-filament::button>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif

@endif
</div>
