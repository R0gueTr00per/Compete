<x-filament-panels::page>
    {{-- Top bar: competition + location --}}
    <div class="mb-5 rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 dark:border-primary-800 dark:bg-primary-950/30">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-primary-700 dark:text-primary-400">Competition</p>
        <div class="flex flex-wrap gap-3">
            <x-filament::input.wrapper class="flex-1 min-w-48">
                <select wire:model.live="competition_id"
                    class="w-full block border-0 bg-transparent py-1.5 text-sm text-gray-900 dark:text-white focus:ring-0">
                    <option value="">— Select competition —</option>
                    @foreach ($this->getCompetitions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </x-filament::input.wrapper>

            @php $locations = $this->getLocations(); @endphp
            @if (! empty($locations))
                <x-filament::input.wrapper class="min-w-40 dark:bg-gray-900">
                    <select wire:model.live="filter_location"
                        class="w-full block border-0 bg-transparent py-1.5 text-sm text-gray-900 dark:text-white focus:ring-0 dark:bg-gray-900">
                        <option value="">— All locations —</option>
                        @foreach ($locations as $loc)
                            <option value="{{ $loc }}">{{ $loc }}</option>
                        @endforeach
                    </select>
                </x-filament::input.wrapper>
            @endif
        </div>
    </div>

    @php $divisionList = $this->getDivisionList(); @endphp

    @php
        $selectedComp = $this->competition_id ? \App\Models\Competition::find($this->competition_id) : null;
    @endphp

    @if (! $this->competition_id)
        <p class="text-center text-gray-400 py-12">Select a competition to begin scoring.</p>
    @elseif ($selectedComp?->status !== 'running')
        <p class="text-center text-gray-400 py-12">Competition is not running yet. Start the competition to begin scoring.</p>
    @elseif ($divisionList->isEmpty())
        <p class="text-center text-gray-400 py-12">No divisions assigned to {{ $this->filter_location }}.</p>
    @else
        {{-- Division list --}}
        <div class="space-y-1 mb-4">
            @foreach ($divisionList as $item)
                @php
                    $div      = $item->division;
                    $selected = $this->division_id === $div->id && $this->panelOpen;
                    $rowClass = match ($div->status) {
                        'complete'  => 'bg-success-50 border-success-300 dark:bg-success-900/20 dark:border-success-700',
                        'running'   => 'bg-warning-50 border-warning-300 dark:bg-warning-900/20 dark:border-warning-700',
                        default     => 'bg-white border-gray-200 dark:bg-gray-900 dark:border-gray-700',
                    };
                    $textClass = match ($div->status) {
                        'complete'  => 'text-success-800 dark:text-success-300',
                        'running'   => 'text-warning-800 dark:text-warning-300',
                        default     => 'text-gray-900 dark:text-white',
                    };
                @endphp
                <div
                    wire:key="division-{{ $div->id }}"
                    wire:click="selectDivision({{ $div->id }})"
                    class="flex items-center justify-between gap-3 rounded-lg border px-4 py-3 transition-all cursor-pointer
                        {{ $rowClass }}
                        {{ $selected
                            ? 'ring-2 ring-primary-500 hover:ring-primary-600'
                            : 'hover:border-primary-300 dark:hover:border-primary-600' }}"
                >
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="font-mono text-sm font-bold shrink-0 {{ $textClass }}">{{ $div->code }}</span>
                        <div class="min-w-0">
                            <p class="text-sm font-medium {{ $textClass }} truncate">
                                {{ $div->competitionEvent->name }}
                                @if ($div->competitionEvent->location_label)
                                    <span class="font-normal text-gray-500 dark:text-gray-400">— {{ $div->competitionEvent->location_label }}</span>
                                @endif
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $div->label }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 shrink-0">
                        <span class="text-xs text-gray-500">
                            {{ $item->checked_in_count }} checked in
                            @if (in_array($div->id, $this->completedRollcallDivisions) || $div->status === 'complete')
                                &middot; {{ $item->competitors_count }} competing
                            @endif
                        </span>

                        @if ($div->status === 'complete')
                            <x-heroicon-m-check-circle class="w-5 h-5 text-success-500" />
                        @elseif ($div->status === 'running')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-warning-100 text-warning-800 dark:bg-warning-900 dark:text-warning-200">Running</span>
                        @else
                            <x-heroicon-m-chevron-right class="w-4 h-4 text-gray-400 {{ $selected ? 'rotate-90' : '' }}" />
                        @endif
                    </div>
                </div>

                {{-- Inline scoring panel --}}
                @if ($selected)
                    @php
                        $rows           = $this->getCompetitorRows();
                        $method         = $this->getScoringMethod();
                        $judges         = $this->getJudgeCount();
                        $isReadOnly     = $div->status === 'complete';
                        $totalCheckedIn = \App\Models\EnrolmentEvent::where('division_id', $this->division_id)
                            ->whereHas('enrolment', fn ($q) => $q->where('status', 'checked_in'))
                            ->count();
                        $competitorCount = $rows->count();
                    @endphp
                    <div class="ml-4 mb-2 rounded-lg border border-primary-200 dark:border-primary-700 bg-primary-50/50 dark:bg-primary-900/10 p-4">

                        {{-- Panel header: step indicator (hidden for completed read-only view) --}}
                        @if (! $isReadOnly)
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2 text-xs font-medium">
                                <span class="flex items-center gap-1.5 {{ $this->rollcallMode ? 'text-primary-700 dark:text-primary-300' : 'text-gray-400 dark:text-gray-600' }}">
                                    <span class="flex h-5 w-5 items-center justify-center rounded-full text-xs font-bold
                                        {{ $this->rollcallMode ? 'bg-primary-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-500' }}">1</span>
                                    Rollcall
                                </span>
                                <x-heroicon-m-arrow-right class="h-3 w-3 text-gray-300 dark:text-gray-600" />
                                <span class="flex items-center gap-1.5 {{ ! $this->rollcallMode ? 'text-primary-700 dark:text-primary-300' : 'text-gray-400 dark:text-gray-600' }}">
                                    <span class="flex h-5 w-5 items-center justify-center rounded-full text-xs font-bold
                                        {{ ! $this->rollcallMode ? 'bg-primary-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-500' }}">2</span>
                                    Scoring
                                </span>
                            </div>
                            @if (! $this->rollcallMode && ! $this->isTournament())
                                <div class="flex items-center gap-1">
                                    @if (in_array($this->getScoringMethod(), ['judges_total', 'judges_average']))
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
                            @endif
                        </div>
                        @endif

                        @if ($this->rollcallMode)
                            {{-- Step 1: Rollcall --}}
                            @php
                                $rollcall = $this->getRollcallRows();
                            @endphp
                            @if ($rollcall->isEmpty())
                                <p class="text-center text-sm text-gray-400 py-4">No checked-in competitors in this division.</p>
                            @else
                                <p class="text-xs text-gray-400 mb-3">Tap each competitor to confirm they are present.</p>
                                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach ($rollcall->where('absent', false) as $rc)
                                        @php $confirmed = in_array($rc->ee_id, $this->rollcallPresent); @endphp
                                        <li wire:click="toggleRollcallPresent({{ $rc->ee_id }})"
                                            class="flex items-center gap-3 py-2.5 cursor-pointer select-none">
                                            @if ($confirmed)
                                                <x-heroicon-m-check-circle class="w-6 h-6 text-success-500 shrink-0" />
                                            @else
                                                <div class="w-6 h-6 rounded-full border-2 border-gray-300 dark:border-gray-600 shrink-0"></div>
                                            @endif
                                            <span class="text-sm {{ $confirmed ? 'font-medium text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400' }}">
                                                {{ $rc->name }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>

                                <div class="mt-4 flex justify-end">
                                    <x-filament::button color="primary" wire:click="toggleRollcall" icon="heroicon-m-arrow-right" icon-position="after">
                                        Begin Scoring
                                    </x-filament::button>
                                </div>
                            @endif

                        @else
                            {{-- Step 2: Scoring --}}
                            @if ($rows->isEmpty())
                                <p class="text-center text-sm text-gray-400 py-4">No checked-in competitors in this division.</p>
                            @elseif ($this->isTournament())
                                {{-- Tournament bracket scoring --}}
                                @php
                                    $bracketData   = $this->getBracketData();
                                    $format        = $this->getTournamentFormat();
                                    $hasBracket    = $this->bracketExists;
                                    $scoringMethod = $this->getScoringMethod();
                                    $isScored      = in_array($scoringMethod, ['judges_total', 'judges_average', 'first_to_n']);
                                    $targetScore   = $scoringMethod === 'first_to_n' ? $this->getTargetScore() : null;
                                @endphp

                                {{-- wire:key changes when bracketExists flips, forcing full replacement instead of morphing --}}
                                <div wire:key="bracket-{{ $this->division_id }}-{{ $hasBracket ? 'has' : 'empty' }}">

                                @if (! $hasBracket)
                                    <div class="text-center py-4">
                                        <p class="text-sm text-gray-500 mb-1">{{ $competitorCount }} competitor(s) competing.</p>
                                        <p class="text-xs text-gray-400 mb-3">
                                            {{ match($format) { 'double_elimination' => 'Double elimination bracket', 'round_robin' => 'Round robin', 'repechage' => 'Single elimination with repechage', 'se_3rd_place' => 'Single elimination with 3rd place playoff', default => 'Single elimination bracket' } }}
                                        </p>
                                        <x-filament::button color="primary" wire:click="generateBracket">
                                            Generate bracket
                                        </x-filament::button>
                                    </div>
                                @else
                                    {{-- Bracket header --}}
                                    <div class="flex items-center justify-between mb-3">
                                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">
                                            {{ match($format) { 'double_elimination' => 'Double elimination', 'round_robin' => 'Round robin', 'repechage' => 'Repechage', 'se_3rd_place' => 'SE + 3rd place playoff', default => 'Single elimination' } }} bracket
                                        </p>
                                        <x-filament::button size="xs" color="gray"
                                            x-on:click="$dispatch('open-modal', { id: 'confirm-reset-bracket' })">
                                            Reset bracket
                                        </x-filament::button>
                                    </div>

                                    @php
                                        $wbAll      = $bracketData['winners'] ?? [];
                                        $maxWbRound = ! empty($wbAll) ? max(array_keys($wbAll)) : 0;

                                        if ($format === 'se_3rd_place') {
                                            // Compute the expected final round from R1 count (same formula as BracketService),
                                            // not from max(existing rounds) which is always 1 until the final match is created.
                                            $wbR1Count    = count($bracketData['winners'][1] ?? []);
                                            $wbFinalRound = $wbR1Count > 1 ? (int) ceil(log($wbR1Count, 2)) + 1 : 1;

                                            // Interleave: early WB rounds → 3rd place → WB final
                                            $displaySections = [];
                                            foreach ($wbAll as $r => $matches) {
                                                if ($r < $wbFinalRound) {
                                                    $displaySections[] = ['label' => null, 'rounds' => [$r => $matches], 'key' => 'winners'];
                                                }
                                            }
                                            $repAll = $bracketData['repechage'] ?? [];
                                            if (! empty($repAll)) {
                                                $displaySections[] = ['label' => '3rd Place Playoff', 'rounds' => $repAll, 'key' => 'repechage'];
                                            }
                                            if (isset($wbAll[$wbFinalRound])) {
                                                $displaySections[] = ['label' => 'Final', 'rounds' => [$wbFinalRound => $wbAll[$wbFinalRound]], 'key' => 'winners'];
                                            }
                                        } else {
                                            $sectionDefs = [
                                                'winners'     => ['label' => in_array($format, ['double_elimination', 'repechage']) ? 'Winners bracket' : null, 'key' => 'winners'],
                                                'losers'      => ['label' => 'Losers bracket',    'key' => 'losers'],
                                                'repechage'   => ['label' => 'Repechage bracket', 'key' => 'repechage'],
                                                'grand_final' => ['label' => 'Grand Final',       'key' => 'grand_final'],
                                            ];
                                            $displaySections = [];
                                            foreach ($sectionDefs as $bk => $meta) {
                                                $bkRounds = $bracketData[$bk] ?? [];
                                                if (! empty($bkRounds)) {
                                                    $displaySections[] = ['label' => $meta['label'], 'rounds' => $bkRounds, 'key' => $bk];
                                                }
                                            }
                                        }
                                    @endphp

                                    @foreach ($displaySections as $displaySection)
                                        @php
                                            $displayBracketKey = $displaySection['key'];
                                            $rounds            = $displaySection['rounds'];
                                            $sectionLabel      = $displaySection['label'];
                                        @endphp

                                        @if ($sectionLabel)
                                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mt-4 mb-1">{{ $sectionLabel }}</p>
                                        @endif

                                        @foreach ($rounds as $roundNum => $matches)
                                            @php $visibleMatches = collect($matches)->filter(fn($m) => ! $m->is_bye); @endphp
                                            @if ($visibleMatches->isEmpty()) @continue @endif
                                            <div class="mb-3">
                                                {{-- Show round label when: multiple rounds in section, OR no section label and not grand_final --}}
                                                @if (count($rounds) > 1 || ($sectionLabel === null && $displayBracketKey !== 'grand_final'))
                                                    <p class="text-xs text-gray-400 mb-1.5">
                                                        @if ($displayBracketKey === 'grand_final') Grand Final
                                                        @else Round {{ $roundNum }}
                                                        @endif
                                                    </p>
                                                @endif

                                                <div class="space-y-1.5">
                                                    @foreach ($visibleMatches as $match)

                                                        @php
                                                            $pending   = $match->is_pending;
                                                            $homeWon   = $match->home_result === 'win';
                                                            $awayWon   = $match->home_result === 'loss';
                                                        @endphp
                                                        <div class="rounded-lg border px-3 py-2 text-sm
                                                            {{ ! $pending ? 'border-success-200 dark:border-success-800 bg-success-50 dark:bg-success-900/20' : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900' }}">

                                                            {{-- Names row --}}
                                                            <div class="flex items-center gap-2">
                                                                <span class="flex-1 font-medium truncate
                                                                    {{ $homeWon ? 'text-success-700 dark:text-success-400' : ($awayWon ? 'text-gray-400 line-through' : 'text-gray-900 dark:text-white') }}">
                                                                    @if ($homeWon)🏆 @endif{{ $match->home_name }}
                                                                </span>
                                                                <span class="text-xs text-gray-400 shrink-0">vs</span>
                                                                <span class="flex-1 text-right font-medium truncate
                                                                    {{ $awayWon ? 'text-success-700 dark:text-success-400' : ($homeWon ? 'text-gray-400 line-through' : 'text-gray-900 dark:text-white') }}">
                                                                    {{ $match->away_name }}@if ($awayWon) 🏆@endif
                                                                </span>
                                                            </div>

                                                            {{-- Controls row --}}
                                                            @if ($pending)
                                                                @if ($isScored)
                                                                    @if ($match->home_id && $match->away_id)
                                                                        <div class="mt-2 flex items-center justify-center gap-2 flex-nowrap">
                                                                            <input type="number" step="any" min="0"
                                                                                @if ($targetScore) max="{{ $targetScore }}" @endif
                                                                                wire:model="bracketScoreInput.{{ $match->id }}.home"
                                                                                @if ($targetScore)
                                                                                    x-on:change="const v = parseFloat($el.value); if (!isNaN(v) && v !== {{ $targetScore }}) $wire.set('bracketScoreInput.{{ $match->id }}.away', '{{ $targetScore }}')"
                                                                                @endif
                                                                                class="w-10 text-center rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm py-1 px-1"
                                                                                placeholder="0" />
                                                                            <span class="text-xs text-gray-400 shrink-0">—</span>
                                                                            <input type="number" step="any" min="0"
                                                                                @if ($targetScore) max="{{ $targetScore }}" @endif
                                                                                wire:model="bracketScoreInput.{{ $match->id }}.away"
                                                                                class="w-10 text-center rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm py-1 px-1"
                                                                                placeholder="0" />
                                                                            <x-filament::button size="xs" color="success" class="shrink-0"
                                                                                wire:click="recordBracketScore({{ $match->id }})">
                                                                                Save
                                                                            </x-filament::button>
                                                                        </div>
                                                                    @endif
                                                                @else
                                                                    @if ($match->home_id && $match->away_id)
                                                                        <div class="mt-2 flex justify-center gap-2">
                                                                            <x-filament::button size="xs" color="success"
                                                                                wire:click="recordBracketWinner({{ $match->id }}, {{ $match->home_id }})">
                                                                                ← Wins
                                                                            </x-filament::button>
                                                                            <x-filament::button size="xs" color="success"
                                                                                wire:click="recordBracketWinner({{ $match->id }}, {{ $match->away_id }})">
                                                                                Wins →
                                                                            </x-filament::button>
                                                                        </div>
                                                                    @endif
                                                                @endif
                                                            @else
                                                                <div class="mt-1 flex items-center justify-center gap-1">
                                                                    @if ($isScored && $match->home_score !== null)
                                                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                                                            {{ (float)$match->home_score + 0 }} — {{ (float)$match->away_score + 0 }}
                                                                        </span>
                                                                    @endif
                                                                    <button wire:click="clearBracketResult({{ $match->id }})"
                                                                        class="text-gray-300 hover:text-danger-400 transition-colors" title="Clear result">
                                                                        <x-heroicon-m-x-mark class="w-3 h-3" />
                                                                    </button>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    @endforeach

                                    {{-- Bracket results summary --}}
                                    @php
                                        $allMatches = collect($bracketData)->flatten(2);
                                        $pendingCount = $allMatches->filter(fn($m) => $m->is_pending)->count();
                                        $isComplete   = $pendingCount === 0
                                            && $allMatches->filter(fn($m) => ! $m->is_bye && $m->winner_id)->isNotEmpty();
                                        $bracketPlacements  = [];
                                        $onlyTwoCompetitors = false;

                                        if ($isComplete) {
                                            $wbRounds     = $bracketData['winners'] ?? [];
                                            $wbFinalRound = ! empty($wbRounds) ? max(array_keys($wbRounds)) : null;
                                            $onlyTwoCompetitors = ($wbFinalRound === 1);

                                            if ($format === 'double_elimination') {
                                                $gf = collect($bracketData['grand_final'] ?? [])->flatten(1)->first();
                                                if ($gf && $gf->winner_id) {
                                                    $bracketPlacements[1] = $gf->winner_id === $gf->home_id ? $gf->home_name : $gf->away_name;
                                                    if ($gf->loser_id)
                                                        $bracketPlacements[2] = $gf->loser_id === $gf->home_id ? $gf->home_name : $gf->away_name;
                                                }
                                            } elseif ($format === 'repechage' && $wbFinalRound) {
                                                $wbFinal = ($wbRounds[$wbFinalRound] ?? [])[0] ?? null;
                                                if ($wbFinal && $wbFinal->winner_id) {
                                                    $bracketPlacements[1] = $wbFinal->winner_id === $wbFinal->home_id ? $wbFinal->home_name : $wbFinal->away_name;
                                                    if ($wbFinal->loser_id)
                                                        $bracketPlacements[2] = $wbFinal->loser_id === $wbFinal->home_id ? $wbFinal->home_name : $wbFinal->away_name;
                                                }
                                                $repRounds = $bracketData['repechage'] ?? [];
                                                if (! empty($repRounds)) {
                                                    $maxRepRound = max(array_keys($repRounds));
                                                    $repFinal    = ($repRounds[$maxRepRound] ?? [])[0] ?? null;
                                                    if ($repFinal && $repFinal->winner_id)
                                                        $bracketPlacements[3] = $repFinal->winner_id === $repFinal->home_id ? $repFinal->home_name : $repFinal->away_name;
                                                }
                                            } elseif ($format === 'se_3rd_place' && $wbFinalRound) {
                                                $wbFinal = ($wbRounds[$wbFinalRound] ?? [])[0] ?? null;
                                                if ($wbFinal && $wbFinal->winner_id) {
                                                    $bracketPlacements[1] = $wbFinal->winner_id === $wbFinal->home_id ? $wbFinal->home_name : $wbFinal->away_name;
                                                    if ($wbFinal->loser_id)
                                                        $bracketPlacements[2] = $wbFinal->loser_id === $wbFinal->home_id ? $wbFinal->home_name : $wbFinal->away_name;
                                                }
                                                $repRounds = $bracketData['repechage'] ?? [];
                                                if (! empty($repRounds)) {
                                                    $thirdFinal = ($repRounds[max(array_keys($repRounds))] ?? [])[0] ?? null;
                                                    if ($thirdFinal && $thirdFinal->winner_id)
                                                        $bracketPlacements[3] = $thirdFinal->winner_id === $thirdFinal->home_id ? $thirdFinal->home_name : $thirdFinal->away_name;
                                                } elseif ($wbFinalRound > 1) {
                                                    $thirdNames = [];
                                                    foreach ($wbRounds[$wbFinalRound - 1] ?? [] as $semi) {
                                                        if ($semi->loser_id && ! $semi->is_bye)
                                                            $thirdNames[] = $semi->loser_id === $semi->home_id ? $semi->home_name : $semi->away_name;
                                                    }
                                                    if (! empty($thirdNames))
                                                        $bracketPlacements[3] = implode(' / ', $thirdNames);
                                                }
                                            } elseif ($format === 'round_robin') {
                                                $onlyTwoCompetitors = false;
                                                $rrWinCounts = [];
                                                $rrNameMap   = [];
                                                foreach (collect($wbRounds)->flatten(1) as $rrM) {
                                                    if ($rrM->home_id) $rrNameMap[$rrM->home_id] = $rrM->home_name;
                                                    if ($rrM->away_id) $rrNameMap[$rrM->away_id] = $rrM->away_name;
                                                    if ($rrM->winner_id) {
                                                        $rrWinCounts[$rrM->winner_id] = ($rrWinCounts[$rrM->winner_id] ?? 0) + 1;
                                                    }
                                                }
                                                foreach (array_keys($rrNameMap) as $rrEeId) {
                                                    if (! isset($rrWinCounts[$rrEeId])) $rrWinCounts[$rrEeId] = 0;
                                                }
                                                arsort($rrWinCounts);
                                                $rrRank = 1; $rrPrevWins = null; $rrCnt = 0; $rrRankNames = [];
                                                foreach ($rrWinCounts as $rrEeId => $rrWins) {
                                                    if ($rrPrevWins !== null && $rrWins < $rrPrevWins) {
                                                        $rrRank += $rrCnt;
                                                        $rrCnt = 0;
                                                    }
                                                    $rrRankNames[$rrRank][] = $rrNameMap[$rrEeId] ?? '?';
                                                    $rrPrevWins = $rrWins; $rrCnt++;
                                                }
                                                foreach ([1, 2, 3] as $rrP) {
                                                    if (isset($rrRankNames[$rrP]))
                                                        $bracketPlacements[$rrP] = implode(' / ', $rrRankNames[$rrP]);
                                                }
                                            } elseif ($wbFinalRound) {
                                                $wbFinal = ($wbRounds[$wbFinalRound] ?? [])[0] ?? null;
                                                if ($wbFinal && $wbFinal->winner_id) {
                                                    $bracketPlacements[1] = $wbFinal->winner_id === $wbFinal->home_id ? $wbFinal->home_name : $wbFinal->away_name;
                                                    if ($wbFinal->loser_id)
                                                        $bracketPlacements[2] = $wbFinal->loser_id === $wbFinal->home_id ? $wbFinal->home_name : $wbFinal->away_name;
                                                }

                                                if ($wbFinalRound > 1) {
                                                    $thirdNames = [];
                                                    foreach ($wbRounds[$wbFinalRound - 1] ?? [] as $semi) {
                                                        if ($semi->loser_id && ! $semi->is_bye)
                                                            $thirdNames[] = $semi->loser_id === $semi->home_id ? $semi->home_name : $semi->away_name;
                                                    }
                                                    if (! empty($thirdNames))
                                                        $bracketPlacements[3] = implode(' / ', $thirdNames);
                                                }
                                            }
                                        }
                                    @endphp

                                    @if ($isComplete && ! empty($bracketPlacements))
                                        <div class="mt-4 rounded-lg border border-success-300 dark:border-success-700 bg-success-50 dark:bg-success-900/20 px-4 py-3">
                                            <p class="text-xs font-semibold uppercase tracking-wider text-success-700 dark:text-success-400 mb-2">Results</p>
                                            @if (isset($bracketPlacements[1]))
                                                <p class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white"><span class="text-2xl leading-none">🥇</span> {{ $bracketPlacements[1] }}</p>
                                            @endif
                                            @if (! $onlyTwoCompetitors && isset($bracketPlacements[2]))
                                                <p class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 mt-1"><span class="text-2xl leading-none">🥈</span> {{ $bracketPlacements[2] }}</p>
                                            @endif
                                            @if (! $onlyTwoCompetitors && isset($bracketPlacements[3]))
                                                <p class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 mt-1"><span class="text-2xl leading-none">🥉</span> {{ $bracketPlacements[3] }}</p>
                                            @endif
                                        </div>
                                    @endif
                                @endif
                                </div>{{-- end wire:key bracket wrapper --}}
                            @else
                                {{-- Standard scoring (judges / win-loss / first-to-n) --}}
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="border-b border-gray-200 dark:border-gray-700 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">
                                                <th class="pb-2 pr-4">Competitor</th>
                                                @if (in_array($method, ['judges_total', 'judges_average']))
                                                    @for ($j = 1; $j <= $judges; $j++)
                                                        <th class="pb-2 pr-2">J{{ $j }}</th>
                                                    @endfor
                                                    <th class="pb-2 pr-4">Total</th>
                                                @elseif ($method === 'win_loss')
                                                    <th class="pb-2 pr-4">Result</th>
                                                @elseif ($method === 'first_to_n')
                                                    <th class="pb-2 pr-4">Points</th>
                                                @endif
                                                <th class="pb-2 pr-4">Place</th>
                                                @if (! $isReadOnly)
                                                    <th class="pb-2">Actions</th>
                                                @endif
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                            @foreach ($rows as $row)
                                                @php $result = $row->result; @endphp
                                                <tr class="{{ $result->disqualified ? 'opacity-50' : '' }}">
                                                    <td class="py-2 pr-4 font-medium text-gray-900 dark:text-white">
                                                        {{ $row->name }}
                                                        @if ($result->disqualified)
                                                            <span class="ml-1 text-xs text-danger-600">DQ</span>
                                                        @endif
                                                    </td>

                                                    @if (in_array($method, ['judges_total', 'judges_average']))
                                                        @php
                                                            $isSaved    = in_array($result->id, $this->savedResultIds);
                                                            $rawScores  = array_filter(array_values($this->judgeScores[$result->id] ?? []), fn ($v) => $v !== null && $v !== '');
                                                            $scoreCount = count($rawScores);
                                                            $liveTotal  = $scoreCount > 0
                                                                ? ($method === 'judges_average'
                                                                    ? round(array_sum($rawScores) / $scoreCount, 1)
                                                                    : round(array_sum($rawScores), 1))
                                                                : null;
                                                        @endphp
                                                        @for ($j = 1; $j <= $judges; $j++)
                                                            <td class="py-2 pr-2">
                                                                @if ($isReadOnly)
                                                                    <span class="text-sm text-gray-700 dark:text-gray-300">
                                                                        {{ number_format((float) ($this->judgeScores[$result->id][$j] ?? 0), 1) }}
                                                                    </span>
                                                                @else
                                                                    <input type="number" step="0.1" min="0" max="10"
                                                                        wire:model="judgeScores.{{ $result->id }}.{{ $j }}"
                                                                        style="width:3.25rem"
                                                                        class="text-center rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm py-0.5 px-1 {{ $isSaved ? 'opacity-50' : '' }}"
                                                                        placeholder="0.0"
                                                                        @if ($isSaved) disabled @endif />
                                                                @endif
                                                            </td>
                                                        @endfor
                                                        <td class="py-2 pr-4">
                                                            <span class="font-semibold">
                                                                {{ $liveTotal !== null ? number_format($liveTotal, 1) : '—' }}
                                                            </span>
                                                        </td>

                                                    @elseif ($method === 'win_loss')
                                                        <td class="py-2 pr-4">
                                                            @if ($isReadOnly)
                                                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                                    {{ ucfirst($result->win_loss ?? '—') }}
                                                                </span>
                                                            @else
                                                                <div class="flex gap-1">
                                                                    <x-filament::button size="xs"
                                                                        color="{{ $result->win_loss === 'win' ? 'success' : 'gray' }}"
                                                                        wire:click="saveWinLoss({{ $result->id }}, 'win')">W</x-filament::button>
                                                                    <x-filament::button size="xs"
                                                                        color="{{ $result->win_loss === 'loss' ? 'danger' : 'gray' }}"
                                                                        wire:click="saveWinLoss({{ $result->id }}, 'loss')">L</x-filament::button>
                                                                    <x-filament::button size="xs"
                                                                        color="{{ $result->win_loss === 'draw' ? 'warning' : 'gray' }}"
                                                                        wire:click="saveWinLoss({{ $result->id }}, 'draw')">D</x-filament::button>
                                                                </div>
                                                            @endif
                                                        </td>

                                                    @elseif ($method === 'first_to_n')
                                                        <td class="py-2 pr-4">
                                                            @if ($isReadOnly)
                                                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                                    {{ $result->total_score ?? '—' }}
                                                                </span>
                                                            @else
                                                                <div class="flex items-center gap-1">
                                                                    <x-filament::input type="number" min="0"
                                                                        wire:model="pointsInput.{{ $result->id }}"
                                                                        class="w-16" placeholder="0" />
                                                                    <x-filament::button size="xs" color="primary"
                                                                        wire:click="savePoints({{ $result->id }})">
                                                                        Save
                                                                    </x-filament::button>
                                                                </div>
                                                            @endif
                                                        </td>
                                                    @endif

                                                    <td class="py-2 pr-4">
                                                        @if (! $isReadOnly && $this->placementOverrideMode)
                                                            <select wire:model="placementInput.{{ $result->id }}"
                                                                wire:change="overridePlacement({{ $result->id }})"
                                                                class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-white px-1 py-0.5 w-14">
                                                                <option value="">—</option>
                                                                @for ($p = 1; $p <= $rows->count(); $p++)
                                                                    <option value="{{ $p }}" {{ ($result->placement == $p) ? 'selected' : '' }}>{{ $p }}</option>
                                                                @endfor
                                                            </select>
                                                        @else
                                                            @if ($result->placement)
                                                                <span class="font-bold {{ $result->placement_overridden ? 'text-warning-600' : '' }}">
                                                                    @switch($result->placement)
                                                                        @case(1) <span class="text-2xl leading-none">🥇</span> @break
                                                                        @case(2) <span class="text-2xl leading-none">🥈</span> @break
                                                                        @case(3) <span class="text-2xl leading-none">🥉</span> @break
                                                                        @default {{ $result->placement }}
                                                                    @endswitch
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
                                                            <div class="flex flex-wrap gap-1">
                                                                @if (in_array($method, ['judges_total', 'judges_average']))
                                                                    @if ($isSaved)
                                                                        <x-filament::button size="xs" color="gray"
                                                                            wire:click="undoJudgeScores({{ $result->id }})">
                                                                            Undo
                                                                        </x-filament::button>
                                                                    @else
                                                                        <x-filament::button size="xs" color="primary"
                                                                            wire:click="saveJudgeScores({{ $result->id }})">
                                                                            Save
                                                                        </x-filament::button>
                                                                    @endif
                                                                @endif
                                                                <x-filament::button size="xs"
                                                                    color="{{ $result->disqualified ? 'gray' : 'danger' }}"
                                                                    wire:click="toggleDisqualify({{ $result->id }})">
                                                                    {{ $result->disqualified ? 'Un-DQ' : 'DQ' }}
                                                                </x-filament::button>
                                                            </div>
                                                        </td>
                                                    @endif
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        @endif

                        {{-- Sudden death tiebreaker (hidden when placement override mode is active) --}}
                        @if (! $this->rollcallMode && ! $this->isRoundRobin() && ! $this->placementOverrideMode)
                            @php $tiedGroups = $this->getTiedGroups(); @endphp
                            @if ($tiedGroups->isNotEmpty())
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

                                            <div class="overflow-x-auto">
                                                <table class="w-full text-sm">
                                                    <thead>
                                                        <tr class="border-b border-warning-200 dark:border-warning-700 text-left text-xs font-medium text-warning-700 dark:text-warning-400 uppercase tracking-wide">
                                                            <th class="pb-2 pr-4">Competitor</th>
                                                            @for ($j = 1; $j <= $judges; $j++)
                                                                <th class="pb-2 pr-2">J{{ $j }}</th>
                                                            @endfor
                                                            <th class="pb-2 pr-4">Total</th>
                                                            <th class="pb-2 pr-4">Place</th>
                                                            <th class="pb-2"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-warning-100 dark:divide-warning-900/40">
                                                        @foreach ($group as $row)
                                                            @php
                                                                $result    = $row->result;
                                                                $tbSaved   = $result->tiebreaker_score !== null;
                                                                $tbRaw     = array_filter(array_values($this->tiebreakerJudgeInputs[$result->id] ?? []), fn ($v) => $v !== null && $v !== '');
                                                                $tbCount   = count($tbRaw);
                                                                $tbLive    = $tbCount > 0
                                                                    ? ($method === 'judges_average'
                                                                        ? round(array_sum($tbRaw) / $tbCount, 1)
                                                                        : round(array_sum($tbRaw), 1))
                                                                    : null;
                                                                $tbDisplay = $tbSaved
                                                                    ? number_format((float) $result->tiebreaker_score, 1)
                                                                    : ($tbLive !== null ? number_format($tbLive, 1) : '—');
                                                            @endphp
                                                            <tr class="{{ ($tbSaved && ! $isReadOnly) ? 'opacity-60' : '' }}">
                                                                <td class="py-2 pr-4 font-medium text-gray-900 dark:text-white">{{ $row->name }}</td>
                                                                @for ($j = 1; $j <= $judges; $j++)
                                                                    <td class="py-2 pr-2">
                                                                        @if ($isReadOnly)
                                                                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                                                                {{ number_format((float) ($this->tiebreakerJudgeInputs[$result->id][$j] ?? 0), 1) }}
                                                                            </span>
                                                                        @else
                                                                            <input type="number" step="0.1" min="0" max="10"
                                                                                wire:model="tiebreakerJudgeInputs.{{ $result->id }}.{{ $j }}"
                                                                                class="w-16 text-center rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm py-0.5 px-1"
                                                                                placeholder="0.0"
                                                                                @if ($tbSaved) disabled @endif />
                                                                        @endif
                                                                    </td>
                                                                @endfor
                                                                <td class="py-2 pr-4">
                                                                    <span class="font-semibold">{{ $tbDisplay }}</span>
                                                                </td>
                                                                <td class="py-2 pr-4 text-gray-400">—</td>
                                                                @if (! $isReadOnly)
                                                                    <td class="py-2">
                                                                        @if ($tbSaved)
                                                                            <div class="flex items-center gap-1.5">
                                                                                <span class="text-xs text-success-600 dark:text-success-400 font-medium">✓ Saved</span>
                                                                                <x-filament::button size="xs" color="gray"
                                                                                    wire:click="clearTiebreakerScore({{ $result->id }})">
                                                                                    Clear
                                                                                </x-filament::button>
                                                                            </div>
                                                                        @else
                                                                            <x-filament::button size="xs" color="warning"
                                                                                wire:click="saveTiebreakerScores({{ $result->id }})">
                                                                                Save
                                                                            </x-filament::button>
                                                                        @endif
                                                                    </td>
                                                                @endif
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Show tiebreaker scores already recorded --}}
                            @php
                                $withTiebreaker = $this->getCompetitorRows()
                                    ->filter(fn ($row) => $row->result->tiebreaker_score !== null);
                                $stillTied = $this->getStillTiedAfterTiebreaker();
                            @endphp
                            @if ($withTiebreaker->isNotEmpty())
                                @if ($isReadOnly)
                                    {{-- Read-only: full J1/J2/J3 tiebreaker display --}}
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
                                                <table class="w-full text-sm">
                                                    <thead>
                                                        <tr class="border-b border-warning-200 dark:border-warning-700 text-left text-xs font-medium text-warning-700 dark:text-warning-400 uppercase tracking-wide">
                                                            <th class="pb-2 pr-4">Competitor</th>
                                                            @for ($j = 1; $j <= $judges; $j++)
                                                                <th class="pb-2 pr-2">J{{ $j }}</th>
                                                            @endfor
                                                            <th class="pb-2 pr-4">Total</th>
                                                            <th class="pb-2 pr-4">Place</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-warning-100 dark:divide-warning-900/40">
                                                        @foreach ($tbGroup->sortByDesc(fn ($row) => (float) $row->result->tiebreaker_score) as $tbRow)
                                                            <tr>
                                                                <td class="py-2 pr-4 font-medium text-gray-900 dark:text-white">{{ $tbRow->name }}</td>
                                                                @for ($j = 1; $j <= $judges; $j++)
                                                                    <td class="py-2 pr-2">
                                                                        <span class="text-sm text-gray-700 dark:text-gray-300">
                                                                            {{ number_format((float) ($this->tiebreakerJudgeInputs[$tbRow->result->id][$j] ?? 0), 1) }}
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
                            @endif

                            {{-- Head judge override when tiebreaker also ties --}}
                            @if ($stillTied->isNotEmpty() && ! $isReadOnly)
                                <div class="mt-3 rounded-lg border border-danger-300 dark:border-danger-700 bg-danger-50 dark:bg-danger-900/20 p-4">
                                    <p class="text-sm font-semibold text-danger-800 dark:text-danger-300 mb-1">
                                        Still tied after sudden death — head judge decides
                                    </p>
                                    <p class="text-xs text-danger-600 dark:text-danger-400 mb-3">
                                        Use the placement override below for each tied competitor to manually assign the final ranking.
                                    </p>
                                    @foreach ($stillTied as $group)
                                        <p class="text-xs font-medium text-danger-700 dark:text-danger-400 mb-2">
                                            Tied: {{ $group->pluck('name')->join(' vs ') }}
                                        </p>
                                        <div class="space-y-1.5">
                                            @foreach ($group as $row)
                                                <div class="flex items-center gap-2">
                                                    <span class="text-sm text-gray-900 dark:text-white w-36 shrink-0">{{ $row->name }}</span>
                                                    <x-filament::input type="number" min="1"
                                                        wire:model="placementInput.{{ $row->result->id }}"
                                                        class="w-12" placeholder="#" />
                                                    <x-filament::button size="xs" color="danger"
                                                        wire:click="overridePlacement({{ $row->result->id }})">
                                                        Set place
                                                    </x-filament::button>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @endif

                        {{-- Panel footer --}}
                        @if ($div->status !== 'complete')
                            <div class="mt-4 flex items-center justify-between gap-3">
                                <div>
                                    @if ($this->rollcallMode)
                                        <x-filament::button color="gray" size="sm"
                                            wire:click="cancelScoring">
                                            Cancel
                                        </x-filament::button>
                                    @else
                                        <x-filament::button color="gray" size="sm"
                                            x-on:click="$dispatch('open-modal', { id: 'confirm-cancel-scoring' })">
                                            Cancel
                                        </x-filament::button>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2">
                                    @if (! $this->rollcallMode)
                                        <x-filament::button color="gray" size="sm"
                                            x-on:click="$dispatch('open-modal', { id: 'confirm-rollcall-return' })"
                                            icon="heroicon-m-arrow-left">
                                            Back to rollcall
                                        </x-filament::button>
                                    @endif
                                    @if ($this->isScoringComplete())
                                        <x-filament::button color="success" size="sm"
                                            x-on:click="$dispatch('open-modal', { id: 'confirm-mark-complete' })">
                                            Mark complete
                                        </x-filament::button>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="mt-4 flex items-center justify-between gap-3">
                                <x-filament::button color="gray" size="sm" wire:click="deselectDivision">
                                    Close
                                </x-filament::button>
                                <x-filament::button color="warning" size="sm"
                                    x-on:click="$dispatch('open-modal', { id: 'confirm-reactivate-division' })">
                                    Re-activate scoring
                                </x-filament::button>
                            </div>
                        @endif
                    </div>
                @endif
            @endforeach
        </div>
    @endif

    <x-filament::modal id="confirm-cancel-scoring" width="sm">
        <x-slot name="heading">Cancel scoring?</x-slot>
        <x-slot name="description">All scores and placements will be cleared.</x-slot>
        <x-slot name="footerActions">
            <x-filament::button color="danger"
                wire:click="cancelScoring"
                x-on:click="$dispatch('close-modal', { id: 'confirm-cancel-scoring' })">
                Yes, cancel
            </x-filament::button>
            <x-filament::button color="gray"
                x-on:click="$dispatch('close-modal', { id: 'confirm-cancel-scoring' })">
                Keep scoring
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

    <x-filament::modal id="confirm-rollcall-return" width="sm">
        <x-slot name="heading">Return to rollcall?</x-slot>
        <x-slot name="description">All scores, placements, and the bracket will be cleared.</x-slot>
        <x-slot name="footerActions">
            <x-filament::button color="danger"
                wire:click="toggleRollcall"
                x-on:click="$dispatch('close-modal', { id: 'confirm-rollcall-return' })">
                Yes, return to rollcall
            </x-filament::button>
            <x-filament::button color="gray"
                x-on:click="$dispatch('close-modal', { id: 'confirm-rollcall-return' })">
                Keep scoring
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

    <x-filament::modal id="confirm-reset-bracket" width="sm">
        <x-slot name="heading">Reset bracket?</x-slot>
        <x-slot name="description">All match results will be cleared and the bracket regenerated.</x-slot>
        <x-slot name="footerActions">
            <x-filament::button color="danger"
                wire:click="resetBracket"
                x-on:click="$dispatch('close-modal', { id: 'confirm-reset-bracket' })">
                Yes, reset
            </x-filament::button>
            <x-filament::button color="gray"
                x-on:click="$dispatch('close-modal', { id: 'confirm-reset-bracket' })">
                Cancel
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

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

    <x-filament::modal id="confirm-reactivate-division" width="sm">
        <x-slot name="heading">Re-activate scoring?</x-slot>
        <x-slot name="description">This will reopen the division for editing. You will need to mark it complete again when finished.</x-slot>
        <x-slot name="footerActions">
            <x-filament::button color="warning"
                wire:click="reactivateDivision"
                x-on:click="$dispatch('close-modal', { id: 'confirm-reactivate-division' })">
                Yes, re-activate
            </x-filament::button>
            <x-filament::button color="gray"
                x-on:click="$dispatch('close-modal', { id: 'confirm-reactivate-division' })">
                Cancel
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

    <x-filament::modal id="confirm-mark-complete" width="sm">
        <x-slot name="heading">Mark division complete?</x-slot>
        <x-slot name="description">Results will be finalised and visible to competitors.</x-slot>
        <x-slot name="footerActions">
            <x-filament::button color="success"
                wire:click="markDivisionComplete"
                x-on:click="$dispatch('close-modal', { id: 'confirm-mark-complete' })">
                Yes, mark complete
            </x-filament::button>
            <x-filament::button color="gray"
                x-on:click="$dispatch('close-modal', { id: 'confirm-mark-complete' })">
                Cancel
            </x-filament::button>
        </x-slot>
    </x-filament::modal>
</x-filament-panels::page>
