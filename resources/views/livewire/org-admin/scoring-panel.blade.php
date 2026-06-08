<div x-data="{ cancelling: false }" x-on:scoring-cancel-confirmed.window="cancelling = true">
    <style>
        input[type=number]::-webkit-outer-spin-button,
        input[type=number]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        input[type=number] { -moz-appearance: textfield; }
    </style>
            {{-- Save while timer active confirmation modal --}}
        <div x-data="{
                open: false,
                matchId: null,
                show(id) { this.matchId = id; this.open = true; },
                confirm() { const mid = this.matchId; window.dispatchEvent(new CustomEvent('timer-reset', { detail: { matchId: mid } })); this.open = false; $wire.recordBracketScore(mid); this.matchId = null; },
                cancel() { this.open = false; this.matchId = null; }
             }"
             x-on:save-confirm.window="show($event.detail.matchId)">
            <template x-if="open">
                <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
                    <div class="rounded-xl border border-warning-300 bg-white dark:bg-slate-800 dark:border-warning-700 p-6 max-w-sm w-full shadow-xl">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white mb-1">Timer is still active</p>
                        <p class="text-sm text-gray-600 dark:text-gray-300 mb-5">The round timer is running or paused. Save the score now and end the round?</p>
                        <div class="flex gap-3 justify-end">
                            <x-filament::button color="gray" size="sm" @click="cancel()">Cancel</x-filament::button>
                            <x-filament::button color="success" size="sm" @click="confirm()">Save score</x-filament::button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

    @if($div)
                        @php
                        $rows               = $this->competitorRows;
                        $method             = $this->getScoringMethod();
                        $judges             = $this->getJudgeCount();
                        $categories         = $this->getScoreCategories();
                        $hasCategories      = $categories->isNotEmpty();
                        $categoryMode       = $div->competitionEvent?->score_category_mode ?? 'single';
                        $judgeMin           = $div->competitionEvent?->min_score;
                        $judgeMax           = $div->competitionEvent?->max_score;
                        $isReadOnly         = $div->status === 'complete';
                        $targetScore        = $method === 'first_to_n' ? $this->getTargetScore() : null;
                        $incrementButtons   = in_array($method, ['first_to_n', 'timed_points']) ? $this->getIncrementButtons() : [];
                        $totalCheckedIn     = \App\Models\EnrolmentEvent::where('division_id', $this->division_id)
                            ->whereHas('enrolment', fn ($q) => $q->where('status', 'checked_in'))
                            ->count();
                        $competitorCount    = $rows->count();
                        $usedPlacements     = $rows->pluck('result.placement')->filter()->values()->all();
                        $enabledPenalties   = $this->getEnabledPenaltyTypes();
                        $dqViaPenalties     = in_array('dq', $enabledPenalties);
                        $isBracket          = $this->isTournament();
                        $highLowDrop        = $div->competitionEvent->high_low_drop ?? false;
                    @endphp
                    <div x-show="!cancelling" class="mb-2 rounded-lg border border-primary-200 dark:border-primary-700 bg-white dark:bg-slate-800 p-4 scoring-panel-glow">

                        {{-- Panel header: step indicator (hidden for completed read-only view) --}}
                        @if (! $isReadOnly)
                        <div class="flex items-center justify-between mb-4">
                            @if ($this->rollcallRequired)
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
                            @else
                            <div></div>
                            @endif
                            <div class="flex items-center gap-2">
                                @if (! $this->rollcallMode && ! $this->isTournament())
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
                                @endif
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5 mb-3">
                            @if (! $this->rollcallMode)
                                <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 dark:bg-slate-700 px-2 py-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    <x-heroicon-m-trophy class="w-3 h-3 shrink-0" />
                                    {{ $this->getAwardedPlacesLabel() }}
                                </span>
                            @endif
                            @foreach ($this->getScoringSettingPills() as $pill)
                                <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-slate-700 px-2 py-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $pill }}
                                </span>
                            @endforeach
                        </div>
                        @endif

                        @if ($this->rollcallMode)
                            @if ($this->rollcallRequired)
                            {{-- Step 1: Rollcall --}}
                            @php
                                $rollcall = $this->getRollcallRows();
                            @endphp
                            @if ($rollcall->isEmpty())
                                <p class="text-center text-sm text-gray-400 py-4">No checked-in competitors in this division.</p>
                            @else
                                @php $activeEeIds = $rollcall->where('absent', false)->pluck('ee_id')->values()->all(); @endphp
                                <div x-data="{
                                        present: {{ json_encode($this->rollcallPresent) }},
                                        allActive: {{ json_encode($activeEeIds) }},
                                        get allMarked() {
                                            return this.allActive.length > 0 && this.allActive.every(id => this.present.includes(id));
                                        },
                                        toggle(id) {
                                            const idx = this.present.indexOf(id);
                                            if (idx >= 0) { this.present = this.present.filter(i => i !== id); }
                                            else { this.present = [...this.present, id]; }
                                        },
                                        markAll()   { this.present = [...new Set([...this.present, ...this.allActive])]; },
                                        unmarkAll() { this.present = this.present.filter(id => !this.allActive.includes(id)); }
                                     }"
                                     x-on:begin-scoring-pressed.window="$wire.call('toggleRollcall', present)">
                                    <div class="flex items-center justify-between mb-3">
                                        <p class="text-xs text-gray-400">Tap each competitor to confirm they are present.</p>
                                        <x-filament::button size="xs" color="gray"
                                            x-on:click="allMarked ? unmarkAll() : markAll()"
                                            x-text="allMarked ? 'Unmark all present' : 'Mark all present'">
                                            Mark all present
                                        </x-filament::button>
                                    </div>
                                    <ul class="divide-y divide-gray-100 dark:divide-slate-800">
                                        @foreach ($rollcall->where('absent', false) as $rc)
                                            <li x-on:click="toggle({{ $rc->ee_id }})"
                                                class="flex items-center gap-3 py-2.5 cursor-pointer select-none">
                                                <template x-if="present.includes({{ $rc->ee_id }})">
                                                    <x-heroicon-m-check-circle class="w-6 h-6 text-success-500 shrink-0" />
                                                </template>
                                                <template x-if="!present.includes({{ $rc->ee_id }})">
                                                    <div class="w-6 h-6 rounded-full border-2 border-gray-300 dark:border-gray-600 shrink-0"></div>
                                                </template>
                                                <span class="text-sm"
                                                    :class="present.includes({{ $rc->ee_id }}) ? 'font-medium text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400'">
                                                    {{ $rc->name }}
                                                    @if ($rc->info)
                                                        <span class="font-normal text-gray-400 dark:text-gray-500">({{ $rc->info }})</span>
                                                    @endif
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            @else
                            {{-- No rollcall — simple Begin Scoring gate --}}
                            <div class="flex flex-col items-center justify-center gap-2 py-6 text-center">
                                <x-heroicon-o-play-circle class="w-10 h-10 text-gray-300 dark:text-gray-600" />
                                <p class="text-sm text-gray-500 dark:text-gray-400">All checked-in competitors will be included.</p>
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
                                    $format           = $this->getTournamentFormat();
                                    $hasBracket       = $this->bracketExists;
                                    $scoringMethod    = $this->getScoringMethod();
                                    $isScored         = in_array($scoringMethod, ['judges_total', 'judges_average', 'first_to_n', 'timed_points']);
                                    $targetScore      = $scoringMethod === 'first_to_n' ? $this->getTargetScore() : null;
                                    $incrementButtons = in_array($scoringMethod, ['first_to_n', 'timed_points']) ? $this->getIncrementButtons() : [];
                                    $roundDuration    = in_array($scoringMethod, ['first_to_n', 'timed_points', 'win_loss']) ? $this->getRoundDuration() : null;
                                    $tbDuration       = in_array($scoringMethod, ['first_to_n', 'timed_points']) ? $this->getTiebreakerDuration() : null;
                                    $tbMode           = in_array($scoringMethod, ['first_to_n', 'timed_points']) ? $this->getTiebreakerMode() : 'sudden_death';
                                    $overtimeRounds   = $tbMode === 'overtime' ? $this->getOvertimeRounds() : 1;
                                @endphp

                                {{-- wire:key changes on any bracket structural change (new matches) or completion, forcing full replacement --}}
                                <div wire:key="bracket-{{ $this->division_id }}-{{ $hasBracket ? 'has' : 'empty' }}-{{ collect($bracketData)->flatten(2)->count() }}-{{ $this->isScoringComplete() ? 'done' : 'active' }}">

                                @if (! $hasBracket)
                                    @if ($this->manualPairingMode)
                                        {{-- Manual pairing wizard --}}
                                        @php
                                            $isOddPairing = (count($this->pairingCompetitorList) % 2 !== 0);
                                            $usedPairingIds = collect($this->manualPairings)
                                                ->flatMap(fn ($p) => [
                                                    isset($p['home']) && $p['home'] !== '' ? (int) $p['home'] : null,
                                                    isset($p['away']) && $p['away'] !== '' && $p['away'] !== 'bye' ? (int) $p['away'] : null,
                                                ])
                                                ->filter()
                                                ->all();
                                        @endphp
                                        <div class="space-y-2">
                                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                                Assign each competitor to a Round 1 matchup.
                                                @if ($isOddPairing)
                                                    One competitor must receive a bye (advances automatically to Round 2).
                                                @endif
                                            </p>

                                            @foreach ($this->manualPairings as $slotIdx => $pair)
                                                @php
                                                    $slotHomeId = isset($pair['home']) && $pair['home'] !== '' ? (int) $pair['home'] : null;
                                                    $slotAwayId = isset($pair['away']) && $pair['away'] !== '' && $pair['away'] !== 'bye' ? (int) $pair['away'] : null;
                                                @endphp
                                                <div class="rounded-lg border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-900/50 px-3 py-2.5">
                                                    <p class="text-xs font-medium text-gray-400 mb-2">Match {{ $slotIdx + 1 }}</p>
                                                    <div class="flex items-center gap-2 flex-wrap"
                                                         x-data="{
                                                             shorten(sel) {
                                                                 const s = sel.options[sel.selectedIndex];
                                                                 if (s && s.value && s.dataset.name) s.textContent = s.dataset.name;
                                                             },
                                                             restore(sel) {
                                                                 Array.from(sel.options).forEach(o => {
                                                                     if (o.dataset.name) o.textContent = o.dataset.info ? o.dataset.name + ' (' + o.dataset.info + ')' : o.dataset.name;
                                                                 });
                                                             }
                                                         }"
                                                         x-init="
                                                             shorten($refs.home); shorten($refs.away);
                                                             $wire.$watch('manualPairings', () => $nextTick(() => { shorten($refs.home); shorten($refs.away); }));
                                                         ">
                                                        <select wire:model.live="manualPairings.{{ $slotIdx }}.home"
                                                            x-ref="home"
                                                            x-on:mousedown="restore($el)"
                                                            class="flex-1 min-w-32 rounded border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm text-gray-900 dark:text-white py-1.5 px-2">
                                                            <option value="">— Select competitor —</option>
                                                            @foreach ($this->pairingCompetitorList as $comp)
                                                                @if (! in_array($comp['ee_id'], $usedPairingIds) || $comp['ee_id'] === $slotHomeId)
                                                                    <option value="{{ $comp['ee_id'] }}" data-name="{{ $comp['name'] }}" data-info="{{ $comp['info'] }}">{{ $comp['name'] }}{{ $comp['info'] ? ' (' . $comp['info'] . ')' : '' }}</option>
                                                                @endif
                                                            @endforeach
                                                        </select>
                                                        <span class="text-xs text-gray-400 shrink-0">vs</span>
                                                        <select wire:model.live="manualPairings.{{ $slotIdx }}.away"
                                                            x-ref="away"
                                                            x-on:mousedown="restore($el)"
                                                            class="flex-1 min-w-32 rounded border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm text-gray-900 dark:text-white py-1.5 px-2">
                                                            <option value="">— Select competitor —</option>
                                                            @if ($isOddPairing)
                                                                <option value="bye">Bye (advances automatically)</option>
                                                            @endif
                                                            @foreach ($this->pairingCompetitorList as $comp)
                                                                @if (! in_array($comp['ee_id'], $usedPairingIds) || $comp['ee_id'] === $slotAwayId)
                                                                    <option value="{{ $comp['ee_id'] }}" data-name="{{ $comp['name'] }}" data-info="{{ $comp['info'] }}">{{ $comp['name'] }}{{ $comp['info'] ? ' (' . $comp['info'] . ')' : '' }}</option>
                                                                @endif
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                            @endforeach

                                            <div class="flex justify-end gap-2 pt-1">
                                                @if ($this->bracketExists)
                                                    <x-filament::button size="sm" color="gray" wire:click="closePairingWizard">
                                                        Cancel
                                                    </x-filament::button>
                                                @else
                                                    <x-filament::button size="sm" color="gray"
                                                        x-on:click="window.dispatchEvent(new Event('pairing-cancelled'))">
                                                        Cancel
                                                    </x-filament::button>
                                                @endif
                                                @if ($this->isPairingComplete())
                                                    <x-filament::button size="sm" color="primary" wire:click="confirmManualPairings">
                                                        Confirm pairings
                                                    </x-filament::button>
                                                @else
                                                    <x-filament::button size="sm" color="primary" disabled>
                                                        Confirm pairings
                                                    </x-filament::button>
                                                @endif
                                            </div>
                                        </div>
                                    @else
                                        <div class="text-center py-4">
                                            <p class="text-sm text-gray-500">{{ $competitorCount }} competitor(s) competing.</p>
                                        </div>
                                    @endif
                                @else
                                    {{-- Bracket header --}}
                                    @if (! $this->isScoringComplete())
                                    <div class="flex justify-end mb-3">
                                        <x-filament::button size="xs" color="gray"
                                            x-on:click="$dispatch('open-modal', { id: 'confirm-reset-bracket' })">
                                            Reset bracket
                                        </x-filament::button>
                                    </div>
                                    @endif

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
                                            $repAll          = $bracketData['repechage'] ?? [];
                                            $repHasRealMatch = collect($repAll)->flatten(1)->contains(fn($m) => ! $m->is_bye);
                                            if (! empty($repAll) && $repHasRealMatch) {
                                                $displaySections[] = ['label' => '3rd Place Playoff', 'rounds' => $repAll, 'key' => 'repechage'];
                                            }
                                            if (isset($wbAll[$wbFinalRound])) {
                                                $displaySections[] = ['label' => 'Final', 'rounds' => [$wbFinalRound => $wbAll[$wbFinalRound]], 'key' => 'winners'];
                                            }
                                        } else {
                                            $sectionDefs = array_filter([
                                                'winners'     => ['label' => in_array($format, ['double_elimination', 'repechage']) ? 'Winners bracket' : null, 'key' => 'winners'],
                                                'losers'      => ['label' => 'Losers bracket',     'key' => 'losers'],
                                                'repechage'   => $format === 'se_3rd_place' ? ['label' => 'Repechage bracket', 'key' => 'repechage'] : null,
                                                'repechage_a' => $format === 'repechage'    ? ['label' => 'Repechage — Side A', 'key' => 'repechage_a'] : null,
                                                'repechage_b' => $format === 'repechage'    ? ['label' => 'Repechage — Side B', 'key' => 'repechage_b'] : null,
                                                'grand_final' => ['label' => 'Grand Final',        'key' => 'grand_final'],
                                            ]);
                                            $displaySections = [];
                                            foreach ($sectionDefs as $bk => $meta) {
                                                $bkRounds = $bracketData[$bk] ?? [];
                                                $hasVisible = collect($bkRounds)->flatten(1)->contains(fn($m) => ! $m->is_bye);
                                                if ($hasVisible) {
                                                    $displaySections[] = ['label' => $meta['label'], 'rounds' => $bkRounds, 'key' => $bk];
                                                }
                                            }
                                        }
                                    @endphp

                                    @php $rowsByEeId = $rows->keyBy(fn($r) => $r->ee->id); @endphp
                                    @foreach ($displaySections as $displaySection)
                                        @php
                                            $displayBracketKey  = $displaySection['key'];
                                            $rounds             = $displaySection['rounds'];
                                            $sectionLabel       = $displaySection['label'];
                                            $sectionFirstRound  = array_key_first($rounds ?? [1 => null]);
                                        @endphp
                                        <div wire:key="section-{{ $this->division_id }}-{{ $displayBracketKey }}-{{ $sectionFirstRound }}">

                                        @if ($sectionLabel)
                                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mt-4 mb-1">{{ $sectionLabel }}</p>
                                        @endif

                                        @foreach ($rounds as $roundNum => $matches)
                                            @php $visibleMatches = collect($matches)->filter(fn($m) => ! $m->is_bye); @endphp
                                            @if ($visibleMatches->isEmpty()) @continue @endif
                                            <div wire:key="round-{{ $this->division_id }}-{{ $displayBracketKey }}-{{ $roundNum }}" class="mb-3">
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
                                                            $pending    = $match->is_pending;
                                                            $homeWon    = $match->home_result === 'win';
                                                            $awayWon    = $match->home_result === 'loss';
                                                            $homeResult = ($rowsByEeId[$match->home_id] ?? null)?->result;
                                                            $awayResult = ($rowsByEeId[$match->away_id] ?? null)?->result;
                                                        @endphp
                                                        <div class="rounded-lg border px-3 py-2 text-sm
                                                            {{ ! $pending ? 'border-success-200 dark:border-success-800 bg-success-50 dark:bg-success-900/20' : 'border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900' }}">

                                                            {{-- Names row --}}
                                                            <div class="flex items-start gap-2">
                                                                <div class="flex-1 min-w-0">
                                                                    <div class="flex items-center gap-1 font-medium {{ $homeWon ? 'text-success-700 dark:text-success-400' : ($awayWon ? 'text-gray-400' : 'text-gray-900 dark:text-white') }}">
                                                                        @if ($homeWon && ! $match->is_bye)<span>🏆</span>@endif<span class="truncate min-w-0 {{ $awayWon ? 'line-through' : '' }}">{{ $match->home_name }}</span>@if (($match->home_dq_in_match || $match->home_forfeit_in_match) && ! $homeWon) <span class="text-xs font-normal text-danger-600 shrink-0">[{{ $match->home_forfeit_in_match ? 'Forfeit' : 'DQ' }}]</span>@endif
                                                                        @if ($homeResult && (! $isReadOnly || $homeResult->note))
                                                                            <button type="button"
                                                                                data-result-id="{{ $homeResult->id }}"
                                                                                data-note="{{ $homeResult->note ?? '' }}"
                                                                                x-on:click="$dispatch('open-note-modal', { resultId: parseInt($el.dataset.resultId), note: $el.dataset.note })"
                                                                                class="shrink-0 {{ $homeResult->note ? 'text-primary-500' : 'text-gray-400 hover:text-primary-500 dark:hover:text-primary-400' }} transition-colors">
                                                                                <x-heroicon-o-document-text class="w-4 h-4" />
                                                                            </button>
                                                                        @endif
                                                                    </div>
                                                                    @if ($match->home_info)
                                                                        <div class="text-xs text-gray-400 dark:text-gray-500 truncate">{{ $match->home_info }}</div>
                                                                    @endif
                                                                    @if ($homeResult)
                                                                        @php $homeLog = $this->getPenaltyLog($homeResult->id, $match->id); @endphp
                                                                        @if ($pending && ! $isReadOnly && ! empty($enabledPenalties))
                                                                            @php
                                                                                $bWC = $this->getWarnCount($homeResult->id, $match->id);
                                                                                $homeFlagged = $homeResult->disqualified || $homeResult->forfeited;
                                                                            @endphp
                                                                            <div class="flex flex-wrap gap-1 items-center mt-1">
                                                                                @foreach ($enabledPenalties as $pType)
                                                                                    @if ($match->away_id !== null)
                                                                                        @if ($pType === 'forfeit' && $homeFlagged) @continue @endif
                                                                                        @if ($pType === 'dq' && $homeResult->forfeited) @continue @endif
                                                                                        <button type="button" wire:click="openPenaltyModal({{ $homeResult->id }}, '{{ $pType }}', {{ $match->id }})"
                                                                                            class="px-1.5 py-0.5 rounded text-xs font-medium border {{ in_array($pType,['dq','forfeit']) ? 'border-danger-300 dark:border-danger-700 bg-danger-50 dark:bg-danger-900/20 text-danger-700 dark:text-danger-400' : 'border-warning-300 dark:border-warning-700 bg-warning-50 dark:bg-warning-900/20 text-warning-700 dark:text-warning-400' }} active:scale-95 transition-transform">
                                                                                            @if($pType==='warn'&&$bWC>0)Warn {{$bWC}}@else{{$this->getPenaltyLabel($pType)}}@endif
                                                                                        </button>
                                                                                    @endif
                                                                                @endforeach
                                                                                @if ($this->hasUndoablePenalty($homeResult->id, $match->id))
                                                                                    <button type="button" wire:click="undoPenalty({{ $homeResult->id }}, {{ $match->id }})" class="px-1.5 py-0.5 rounded text-xs border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 active:scale-95 transition-transform"><x-heroicon-m-arrow-uturn-left class="inline w-3 h-3" /></button>
                                                                                @endif
                                                                            </div>
                                                                        @endif
                                                                        @if(!empty($homeLog))<ul class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 space-y-0.5">@foreach($homeLog as $e)<li>{{$e['label']}}</li>@endforeach</ul>@endif
                                                                    @endif
                                                                </div>
                                                                <span class="text-xs text-gray-400 shrink-0 mt-0.5">vs</span>
                                                                <div class="flex-1 min-w-0 text-right">
                                                                    <div class="flex items-center justify-end gap-1 font-medium {{ $awayWon ? 'text-success-700 dark:text-success-400' : ($homeWon ? 'text-gray-400' : 'text-gray-900 dark:text-white') }}">
                                                                        @if ($awayResult && (! $isReadOnly || $awayResult->note))
                                                                            <button type="button"
                                                                                data-result-id="{{ $awayResult->id }}"
                                                                                data-note="{{ $awayResult->note ?? '' }}"
                                                                                x-on:click="$dispatch('open-note-modal', { resultId: parseInt($el.dataset.resultId), note: $el.dataset.note })"
                                                                                class="shrink-0 {{ $awayResult->note ? 'text-primary-500' : 'text-gray-400 hover:text-primary-500 dark:hover:text-primary-400' }} transition-colors">
                                                                                <x-heroicon-o-document-text class="w-4 h-4" />
                                                                            </button>
                                                                        @endif
                                                                        @if (($match->away_dq_in_match || $match->away_forfeit_in_match) && ! $awayWon) <span class="text-xs font-normal text-danger-600 shrink-0">[{{ $match->away_forfeit_in_match ? 'Forfeit' : 'DQ' }}]</span> @endif<span class="truncate min-w-0 {{ ($homeWon && ! $match->is_bye) ? 'line-through' : '' }}">{{ $match->away_name }}</span>@if ($awayWon) <span>🏆</span>@endif
                                                                    </div>
                                                                    @if ($match->away_info)
                                                                        <div class="text-xs text-gray-400 dark:text-gray-500 truncate">{{ $match->away_info }}</div>
                                                                    @endif
                                                                    @if ($awayResult)
                                                                        @php $awayLog = $this->getPenaltyLog($awayResult->id, $match->id); @endphp
                                                                        @if ($pending && ! $isReadOnly && ! empty($enabledPenalties))
                                                                            @php
                                                                                $bWC = $this->getWarnCount($awayResult->id, $match->id);
                                                                                $awayFlagged = $awayResult->disqualified || $awayResult->forfeited;
                                                                            @endphp
                                                                            <div class="flex flex-wrap gap-1 items-center justify-end mt-1">
                                                                                @if ($this->hasUndoablePenalty($awayResult->id, $match->id))
                                                                                    <button type="button" wire:click="undoPenalty({{ $awayResult->id }}, {{ $match->id }})" class="px-1.5 py-0.5 rounded text-xs border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 active:scale-95 transition-transform"><x-heroicon-m-arrow-uturn-left class="inline w-3 h-3" /></button>
                                                                                @endif
                                                                                @foreach ($enabledPenalties as $pType)
                                                                                    @if ($match->away_id !== null)
                                                                                        @if ($pType === 'forfeit' && $awayFlagged) @continue @endif
                                                                                        @if ($pType === 'dq' && $awayResult->forfeited) @continue @endif
                                                                                        <button type="button" wire:click="openPenaltyModal({{ $awayResult->id }}, '{{ $pType }}', {{ $match->id }})"
                                                                                            class="px-1.5 py-0.5 rounded text-xs font-medium border {{ in_array($pType,['dq','forfeit']) ? 'border-danger-300 dark:border-danger-700 bg-danger-50 dark:bg-danger-900/20 text-danger-700 dark:text-danger-400' : 'border-warning-300 dark:border-warning-700 bg-warning-50 dark:bg-warning-900/20 text-warning-700 dark:text-warning-400' }} active:scale-95 transition-transform">
                                                                                            @if($pType==='warn'&&$bWC>0)Warn {{$bWC}}@else{{$this->getPenaltyLabel($pType)}}@endif
                                                                                        </button>
                                                                                    @endif
                                                                                @endforeach
                                                                            </div>
                                                                        @endif
                                                                        @if(!empty($awayLog))<ul class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 space-y-0.5 text-right">@foreach($awayLog as $e)<li>{{$e['label']}}</li>@endforeach</ul>@endif
                                                                    @endif
                                                                </div>
                                                            </div>

                                                            {{-- Round timer --}}
                                                            @if ($roundDuration && $pending && $match->home_id && $match->away_id)
                                                                <div x-data="matchTimer({{ $match->id }}, {{ $roundDuration }}, {{ $tbDuration ?? 'null' }}, '{{ $tbMode }}', {{ $overtimeRounds }})"
                                                                     x-on:timer-reset.window="if ($event.detail.matchId === matchId) reset()"
                                                                     x-on:timer-tied.window="if ($event.detail.matchId === matchId) enterSdPrompt()"
                                                                     x-on:overtime-tied.window="if ($event.detail.matchId === matchId) enterOvertimeTied()"
                                                                     x-on:timer-pause.window="if ($event.detail.matchId === matchId && (phase === 'running' || phase === 'tb_running')) pause()"
                                                                     class="mt-2">
                                                                    {{-- Timer bar --}}
                                                                    <div class="flex items-center justify-between gap-2 rounded-lg px-3 py-2"
                                                                         :class="{
                                                                             'bg-success-50 dark:bg-success-900/20': phase === 'idle' || phase === 'running' || phase === 'paused',
                                                                             'bg-warning-50 dark:bg-warning-900/20': phase === 'tb_running' || phase === 'tb_paused',
                                                                             'bg-danger-50 dark:bg-danger-900/20': phase === 'expired' || phase === 'tb_expired',
                                                                         }">
                                                                        <div class="flex items-center gap-2">
                                                                            <span class="flex items-baseline leading-none">
                                                                                <span class="font-mono text-2xl font-bold tabular-nums"
                                                                                      :class="{
                                                                                          'text-success-700 dark:text-success-300': phase === 'idle' || phase === 'running',
                                                                                          'text-warning-600 dark:text-warning-400': phase === 'paused' || phase === 'tb_running' || phase === 'tb_paused',
                                                                                          'text-danger-600 dark:text-danger-400 timer-expire-flash': phase === 'expired' || phase === 'tb_expired',
                                                                                      }"
                                                                                      x-text="displaySeconds"></span><span class="font-mono text-xs font-medium tabular-nums text-gray-400 dark:text-gray-500 relative -top-[3px]"
                                                                                      :class="{ 'timer-expire-flash': phase === 'expired' || phase === 'tb_expired' }"
                                                                                      x-text="displayCentis"></span>
                                                                            </span>
                                                                            <span x-show="phase === 'tb_running' || phase === 'tb_paused'"
                                                                                  x-text="tbMode === 'overtime' ? (overtimeRounds > 1 ? 'OT ' + overtimeRound : 'Overtime') : 'Sudden Death'"
                                                                                  class="text-xs font-semibold uppercase tracking-wide text-warning-600 dark:text-warning-400">
                                                                            </span>
                                                                            <span x-show="phase === 'expired'"
                                                                                  class="text-xs font-semibold uppercase tracking-wide text-danger-600 dark:text-danger-400 timer-expire-flash">
                                                                                Time Expired
                                                                            </span>
                                                                            <span x-show="phase === 'tb_expired'"
                                                                                  x-text="tbMode === 'overtime' ? 'OT Expired' : 'SD Expired'"
                                                                                  class="text-xs font-semibold uppercase tracking-wide text-danger-600 dark:text-danger-400 timer-expire-flash">
                                                                            </span>
                                                                        </div>
                                                                        <div class="flex items-center gap-1.5">
                                                                            <x-filament::button size="sm" color="success" x-show="phase === 'idle'" @click="start()">▶ Start</x-filament::button>
                                                                            <x-filament::button size="sm" color="warning" x-show="phase === 'running' || phase === 'tb_running'" @click="pause()">⏸ Pause</x-filament::button>
                                                                            <x-filament::button size="sm" color="success" x-show="phase === 'paused' || phase === 'tb_paused'" @click="resume()">▶ Resume</x-filament::button>
                                                                            @if ($tbDuration)
                                                                                <x-filament::button size="sm" color="warning" x-show="sdNeeded" @click="startTiebreaker()" x-text="tbMode === 'overtime' ? (overtimeRound >= 1 ? '⚡ OT ' + (overtimeRound + 1) : '⚡ Overtime') : '⚡ Sudden Death'"></x-filament::button>
                                                                            @endif
                                                                            <x-filament::button size="sm" color="gray" x-show="phase === 'paused' || phase === 'tb_paused' || phase === 'expired' || phase === 'tb_expired'" @click="reset()">↺ Reset</x-filament::button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @endif

                                                            {{-- Controls row --}}
                                                            @if ($pending)
                                                                @if ($isScored)
                                                                    @if ($match->home_id && $match->away_id)
                                                                        {{-- Mobile: per-competitor rows with increment buttons --}}
                                                                        <div class="sm:hidden mt-2 space-y-3"
                                                                             x-data="{
                                                                                 homeHistory: [],
                                                                                 awayHistory: [],
                                                                                 init() {
                                                                                     const h = Number($wire.get('bracketScoreInput.{{ $match->id }}.home') || 0);
                                                                                     if (h > 0) this.homeHistory = [h];
                                                                                     const a = Number($wire.get('bracketScoreInput.{{ $match->id }}.away') || 0);
                                                                                     if (a > 0) this.awayHistory = [a];
                                                                                 },
                                                                                 get sdLocked() { return $store.roundTimer.sdLocked && $store.roundTimer.matchId === {{ $match->id }}; },
                                                                                 addScore(side, amount) {
                                                                                     if (this.sdLocked) return;
                                                                                     const max = {{ $targetScore ?? 'Infinity' }};
                                                                                     const cur = side === 'home' ? this.homeHistory : this.awayHistory;
                                                                                     const total = cur.reduce((s,v) => s+v, 0);
                                                                                     if (total + amount > max) return;
                                                                                     cur.push(amount);
                                                                                     $wire.set('bracketScoreInput.{{ $match->id }}.' + side, total + amount, false);
                                                                                 },
                                                                                 undoScore(side) {
                                                                                     if (this.sdLocked) return;
                                                                                     const cur = side === 'home' ? this.homeHistory : this.awayHistory;
                                                                                     if (cur.length === 0) return;
                                                                                     cur.pop();
                                                                                     const total = cur.reduce((s,v) => s+v, 0);
                                                                                     $wire.set('bracketScoreInput.{{ $match->id }}.' + side, total > 0 ? total : null, false);
                                                                                 }
                                                                             }">
                                                                            <div class="space-y-1">
                                                                                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium truncate">{{ $match->home_name }}</p>
                                                                                <div class="text-3xl font-bold leading-none py-1 transition-colors"
                                                                                     :class="homeHistory.reduce((s,v)=>s+v,0) >= {{ $targetScore ?? 99999 }} ? 'text-green-600 dark:text-green-400 winner-halo' : 'text-gray-900 dark:text-white'"
                                                                                     x-text="homeHistory.reduce((s,v)=>s+v,0)"></div>
                                                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                                                    <button type="button"
                                                                                        x-on:click="undoScore('home')"
                                                                                        x-bind:disabled="homeHistory.length === 0 || sdLocked"
                                                                                        x-bind:class="(homeHistory.length === 0 || sdLocked) ? 'opacity-40 cursor-not-allowed' : 'active:scale-95'"
                                                                                        class="h-9 w-9 flex items-center justify-center rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-slate-800 text-gray-600 dark:text-gray-300 font-medium transition-transform"><x-heroicon-m-arrow-uturn-left class="w-4 h-4" /></button>
                                                                                    @foreach ($incrementButtons as $btn)
                                                                                        <button type="button"
                                                                                        x-on:click="addScore('home', {{ $btn }})"
                                                                                        x-bind:class="sdLocked ? 'opacity-40 cursor-not-allowed' : 'active:scale-95'"
                                                                                        class="h-9 px-3 flex items-center justify-center rounded bg-primary-600 dark:bg-primary-500 text-white font-semibold shadow-sm transition-transform">
                                                                                        +{{ $btn }}
                                                                                    </button>
                                                                                    @endforeach
                                                                                    @if ($homeResult && ! $isReadOnly && ! $dqViaPenalties && ! $homeResult->forfeited)
                                                                                        <x-filament::button size="xs"
                                                                                        color="{{ $homeResult->disqualified ? 'gray' : 'danger' }}"
                                                                                        wire:click="toggleDisqualify({{ $homeResult->id }})"
                                                                                        @click="if ($store.roundTimer.running && $store.roundTimer.matchId === {{ $match->id }}) $dispatch('timer-pause', { matchId: {{ $match->id }} })">
                                                                                        {{ $homeResult->disqualified ? 'Un-DQ' : 'DQ' }}
                                                                                    </x-filament::button>
                                                                                    @endif
                                                                                    @if (! $isReadOnly)
                                                                                        <span x-data x-show="$store.roundTimer.sdActive && $store.roundTimer.matchId === {{ $match->id }}">
                                                                                            <x-filament::button size="xs" color="success"
                                                                                            @click="window.dispatchEvent(new CustomEvent('timer-reset',{detail:{matchId:{{$match->id}}}}));$wire.declareBracketWinner({{$match->id}},'home')">Win</x-filament::button>
                                                                                        </span>
                                                                                    @endif
                                                                                </div>
                                                                            </div>
                                                                            <div class="space-y-1">
                                                                                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium truncate">{{ $match->away_name }}</p>
                                                                                <div class="text-3xl font-bold leading-none py-1 transition-colors"
                                                                                     :class="awayHistory.reduce((s,v)=>s+v,0) >= {{ $targetScore ?? 99999 }} ? 'text-green-600 dark:text-green-400 winner-halo' : 'text-gray-900 dark:text-white'"
                                                                                     x-text="awayHistory.reduce((s,v)=>s+v,0)"></div>
                                                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                                                    <button type="button"
                                                                                        x-on:click="undoScore('away')"
                                                                                        x-bind:disabled="awayHistory.length === 0 || sdLocked"
                                                                                        x-bind:class="(awayHistory.length === 0 || sdLocked) ? 'opacity-40 cursor-not-allowed' : 'active:scale-95'"
                                                                                        class="h-9 w-9 flex items-center justify-center rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-slate-800 text-gray-600 dark:text-gray-300 font-medium transition-transform"><x-heroicon-m-arrow-uturn-left class="w-4 h-4" /></button>
                                                                                    @foreach ($incrementButtons as $btn)
                                                                                        <button type="button"
                                                                                        x-on:click="addScore('away', {{ $btn }})"
                                                                                        x-bind:class="sdLocked ? 'opacity-40 cursor-not-allowed' : 'active:scale-95'"
                                                                                        class="h-9 px-3 flex items-center justify-center rounded bg-primary-600 dark:bg-primary-500 text-white font-semibold shadow-sm transition-transform">
                                                                                        +{{ $btn }}
                                                                                    </button>
                                                                                    @endforeach
                                                                                    @if ($awayResult && ! $isReadOnly && ! $dqViaPenalties && ! $awayResult->forfeited)
                                                                                        <x-filament::button size="xs"
                                                                                        color="{{ $awayResult->disqualified ? 'gray' : 'danger' }}"
                                                                                        wire:click="toggleDisqualify({{ $awayResult->id }})"
                                                                                        @click="if ($store.roundTimer.running && $store.roundTimer.matchId === {{ $match->id }}) $dispatch('timer-pause', { matchId: {{ $match->id }} })">
                                                                                        {{ $awayResult->disqualified ? 'Un-DQ' : 'DQ' }}
                                                                                    </x-filament::button>
                                                                                    @endif
                                                                                    @if (! $isReadOnly)
                                                                                        <span x-data x-show="$store.roundTimer.sdActive && $store.roundTimer.matchId === {{ $match->id }}">
                                                                                            <x-filament::button size="xs" color="success"
                                                                                            @click="window.dispatchEvent(new CustomEvent('timer-reset',{detail:{matchId:{{$match->id}}}}));$wire.declareBracketWinner({{$match->id}},'away')">Win</x-filament::button>
                                                                                        </span>
                                                                                    @endif
                                                                                </div>
                                                                            </div>
                                                                            <x-filament::button color="success" class="w-full"
                                                                                @click="$store.roundTimer.active && $store.roundTimer.matchId === {{ $match->id }} ? $dispatch('save-confirm', { matchId: {{ $match->id }} }) : $wire.recordBracketScore({{ $match->id }})">Save</x-filament::button>
                                                                        </div>
                                                                        {{-- Desktop: stacked columns --}}
                                                                        <div class="hidden sm:flex items-start gap-2 mt-2"
                                                                             x-data="{
                                                                                 homeHistory: [],
                                                                                 awayHistory: [],
                                                                                 init() {
                                                                                     const h = Number($wire.get('bracketScoreInput.{{ $match->id }}.home') || 0);
                                                                                     if (h > 0) this.homeHistory = [h];
                                                                                     const a = Number($wire.get('bracketScoreInput.{{ $match->id }}.away') || 0);
                                                                                     if (a > 0) this.awayHistory = [a];
                                                                                 },
                                                                                 get sdLocked() { return $store.roundTimer.sdLocked && $store.roundTimer.matchId === {{ $match->id }}; },
                                                                                 addScore(side, amount) {
                                                                                     if (this.sdLocked) return;
                                                                                     const max = {{ $targetScore ?? 'Infinity' }};
                                                                                     const cur = side === 'home' ? this.homeHistory : this.awayHistory;
                                                                                     const total = cur.reduce((s,v) => s+v, 0);
                                                                                     if (total + amount > max) return;
                                                                                     cur.push(amount);
                                                                                     $wire.set('bracketScoreInput.{{ $match->id }}.' + side, total + amount, false);
                                                                                 },
                                                                                 undoScore(side) {
                                                                                     if (this.sdLocked) return;
                                                                                     const cur = side === 'home' ? this.homeHistory : this.awayHistory;
                                                                                     if (cur.length === 0) return;
                                                                                     cur.pop();
                                                                                     const total = cur.reduce((s,v) => s+v, 0);
                                                                                     $wire.set('bracketScoreInput.{{ $match->id }}.' + side, total > 0 ? total : null, false);
                                                                                 }
                                                                             }">
                                                                            <div class="flex-1 flex flex-col items-end gap-1">
                                                                                <div class="text-3xl font-bold leading-none transition-colors"
                                                                                     :class="homeHistory.reduce((s,v)=>s+v,0) >= {{ $targetScore ?? 99999 }} ? 'text-green-600 dark:text-green-400 winner-halo' : 'text-gray-900 dark:text-white'"
                                                                                     x-text="homeHistory.reduce((s,v)=>s+v,0)"></div>
                                                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                                                    <button type="button"
                                                                                        x-on:click="undoScore('home')"
                                                                                        x-bind:disabled="homeHistory.length === 0 || sdLocked"
                                                                                        x-bind:class="(homeHistory.length === 0 || sdLocked) ? 'opacity-40 cursor-not-allowed' : 'active:scale-95'"
                                                                                        class="h-9 w-9 flex items-center justify-center rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-slate-800 text-gray-600 dark:text-gray-300 font-medium transition-transform"><x-heroicon-m-arrow-uturn-left class="w-4 h-4" /></button>
                                                                                    @foreach ($incrementButtons as $btn)
                                                                                        <button type="button"
                                                                                        x-on:click="addScore('home', {{ $btn }})"
                                                                                        x-bind:class="sdLocked ? 'opacity-40 cursor-not-allowed' : 'active:scale-95'"
                                                                                        class="h-9 px-3 flex items-center justify-center rounded bg-primary-600 dark:bg-primary-500 text-white font-semibold shadow-sm transition-transform">
                                                                                        +{{ $btn }}
                                                                                    </button>
                                                                                    @endforeach
                                                                                    @if ($homeResult && ! $isReadOnly && ! $dqViaPenalties && ! $homeResult->forfeited)
                                                                                        <x-filament::button size="xs"
                                                                                        color="{{ $homeResult->disqualified ? 'gray' : 'danger' }}"
                                                                                        wire:click="toggleDisqualify({{ $homeResult->id }})"
                                                                                        @click="if ($store.roundTimer.running && $store.roundTimer.matchId === {{ $match->id }}) $dispatch('timer-pause', { matchId: {{ $match->id }} })">
                                                                                        {{ $homeResult->disqualified ? 'Un-DQ' : 'DQ' }}
                                                                                    </x-filament::button>
                                                                                    @endif
                                                                                </div>
                                                                                @if (! $isReadOnly)
                                                                                    <span x-data x-show="$store.roundTimer.sdActive && $store.roundTimer.matchId === {{ $match->id }}" class="block">
                                                                                        <x-filament::button size="xs" color="success"
                                                                                        @click="window.dispatchEvent(new CustomEvent('timer-reset',{detail:{matchId:{{$match->id}}}}));$wire.declareBracketWinner({{$match->id}},'home')">← Win</x-filament::button>
                                                                                    </span>
                                                                                @endif
                                                                            </div>
                                                                            <span class="text-sm text-gray-400 shrink-0 mt-2">—</span>
                                                                            <div class="flex-1 flex flex-col items-start gap-1">
                                                                                <div class="text-3xl font-bold leading-none transition-colors"
                                                                                     :class="awayHistory.reduce((s,v)=>s+v,0) >= {{ $targetScore ?? 99999 }} ? 'text-green-600 dark:text-green-400 winner-halo' : 'text-gray-900 dark:text-white'"
                                                                                     x-text="awayHistory.reduce((s,v)=>s+v,0)"></div>
                                                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                                                    <button type="button"
                                                                                        x-on:click="undoScore('away')"
                                                                                        x-bind:disabled="awayHistory.length === 0 || sdLocked"
                                                                                        x-bind:class="(awayHistory.length === 0 || sdLocked) ? 'opacity-40 cursor-not-allowed' : 'active:scale-95'"
                                                                                        class="h-9 w-9 flex items-center justify-center rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-slate-800 text-gray-600 dark:text-gray-300 font-medium transition-transform"><x-heroicon-m-arrow-uturn-left class="w-4 h-4" /></button>
                                                                                    @foreach ($incrementButtons as $btn)
                                                                                        <button type="button"
                                                                                        x-on:click="addScore('away', {{ $btn }})"
                                                                                        x-bind:class="sdLocked ? 'opacity-40 cursor-not-allowed' : 'active:scale-95'"
                                                                                        class="h-9 px-3 flex items-center justify-center rounded bg-primary-600 dark:bg-primary-500 text-white font-semibold shadow-sm transition-transform">
                                                                                        +{{ $btn }}
                                                                                    </button>
                                                                                    @endforeach
                                                                                    @if ($awayResult && ! $isReadOnly && ! $dqViaPenalties && ! $awayResult->forfeited)
                                                                                        <x-filament::button size="xs"
                                                                                        color="{{ $awayResult->disqualified ? 'gray' : 'danger' }}"
                                                                                        wire:click="toggleDisqualify({{ $awayResult->id }})"
                                                                                        @click="if ($store.roundTimer.running && $store.roundTimer.matchId === {{ $match->id }}) $dispatch('timer-pause', { matchId: {{ $match->id }} })">
                                                                                        {{ $awayResult->disqualified ? 'Un-DQ' : 'DQ' }}
                                                                                    </x-filament::button>
                                                                                    @endif
                                                                                </div>
                                                                                @if (! $isReadOnly)
                                                                                    <span x-data x-show="$store.roundTimer.sdActive && $store.roundTimer.matchId === {{ $match->id }}" class="block">
                                                                                        <x-filament::button size="xs" color="success"
                                                                                        @click="window.dispatchEvent(new CustomEvent('timer-reset',{detail:{matchId:{{$match->id}}}}));$wire.declareBracketWinner({{$match->id}},'away')">Win →</x-filament::button>
                                                                                    </span>
                                                                                @endif
                                                                            </div>
                                                                            <x-filament::button size="xs" color="success" class="shrink-0 self-end"
                                                                                @click="$store.roundTimer.active && $store.roundTimer.matchId === {{ $match->id }} ? $dispatch('save-confirm', { matchId: {{ $match->id }} }) : $wire.recordBracketScore({{ $match->id }})">Save</x-filament::button>
                                                                        </div>
                                                                    @endif
                                                                @else
                                                                    @if ($match->home_id && $match->away_id)
                                                                        <div class="mt-2 flex flex-wrap justify-center gap-2">
                                                                            @if ($homeResult && ! $isReadOnly)
                                                                                @if (! $dqViaPenalties)
                                                                                    <x-filament::button size="xs"
                                                                                        color="{{ $homeResult->disqualified ? 'gray' : 'danger' }}"
                                                                                        wire:click="toggleDisqualify({{ $homeResult->id }})">
                                                                                        {{ $homeResult->disqualified ? 'Un-DQ' : 'DQ' }}
                                                                                    </x-filament::button>
                                                                                @endif
                                                                            @endif
                                                                            <x-filament::button size="xs" color="success"
                                                                                wire:click="recordBracketWinner({{ $match->id }}, {{ $match->home_id }})">
                                                                                ← Wins
                                                                            </x-filament::button>
                                                                            <x-filament::button size="xs" color="success"
                                                                                wire:click="recordBracketWinner({{ $match->id }}, {{ $match->away_id }})">
                                                                                Wins →
                                                                            </x-filament::button>
                                                                            @if ($awayResult && ! $isReadOnly)
                                                                                @if (! $dqViaPenalties)
                                                                                    <x-filament::button size="xs"
                                                                                        color="{{ $awayResult->disqualified ? 'gray' : 'danger' }}"
                                                                                        wire:click="toggleDisqualify({{ $awayResult->id }})">
                                                                                        {{ $awayResult->disqualified ? 'Un-DQ' : 'DQ' }}
                                                                                    </x-filament::button>
                                                                                @endif
                                                                            @endif
                                                                        </div>
                                                                    @endif
                                                                @endif
                                                            @else
                                                                <div class="mt-1 flex items-center justify-center gap-2">
                                                                    @if ($isScored && $match->home_score !== null)
                                                                        <span class="text-base font-medium text-gray-700 dark:text-gray-200">
                                                                            {{ (float)$match->home_score + 0 }} — {{ (float)$match->away_score + 0 }}
                                                                        </span>
                                                                    @endif
                                                                    @if (! $isReadOnly && $match->can_undo)
                                                                        <x-filament::button size="xs" color="gray"
                                                                            wire:click="clearBracketResult({{ $match->id }})">
                                                                            Undo
                                                                        </x-filament::button>
                                                                    @endif
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                        </div>{{-- end section wire:key --}}
                                    @endforeach

                                    {{-- Bracket results summary --}}
                                    @php
                                        $allMatches = collect($bracketData)->flatten(2);
                                        $pendingCount = $allMatches->filter(fn($m) => $m->is_pending)->count();
                                        $isComplete   = $pendingCount === 0
                                            && $allMatches->filter(fn($m) => ! $m->is_bye && $m->winner_id)->isNotEmpty();
                                        $bracketPlacements  = [];
                                        $onlyTwoCompetitors = false;

                                        $placementCap = 3;
                                        if ($isComplete) {
                                            $wbRounds           = $bracketData['winners'] ?? [];
                                            $wbFinalRound       = ! empty($wbRounds) ? max(array_keys($wbRounds)) : null;
                                            $onlyTwoCompetitors = ($wbFinalRound === 1);
                                            $_capEvent          = $div->competitionEvent;
                                            $placementCap       = match (true) {
                                                $competitorCount <= 2 => $_capEvent->awarded_places_2 ?? 2,
                                                $competitorCount === 3 => $_capEvent->awarded_places_3 ?? 3,
                                                default               => $_capEvent->awarded_places_4plus ?? 3,
                                            };

                                            $finalist2Forfeited = false;
                                            foreach ($rows as $row) {
                                                $p = $row->result->placement;
                                                if ($p && $p >= 1 && $p <= 3) {
                                                    if (isset($bracketPlacements[$p])) {
                                                        $bracketPlacements[$p] .= ' / ' . $row->name;
                                                    } else {
                                                        $bracketPlacements[$p] = $row->name;
                                                    }
                                                    if ($p === 2 && $row->result->forfeited) {
                                                        $finalist2Forfeited = true;
                                                    }
                                                }
                                            }
                                        }
                                    @endphp

                                    @if ($isComplete && ! empty($bracketPlacements))
                                        <div wire:key="bracket-results-{{ $this->division_id }}"
                                             class="mt-4 rounded-lg border border-success-300 dark:border-success-700 bg-success-50 dark:bg-success-900/20 px-4 py-3">
                                            <p class="text-xs font-semibold uppercase tracking-wider text-success-700 dark:text-success-400 mb-2">Results</p>
                                            @if (isset($bracketPlacements[1]))
                                                <p class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white"><span class="text-2xl leading-none">🥇</span> {{ $bracketPlacements[1] }}</p>
                                            @endif
                                            @if ($placementCap >= 2 && isset($bracketPlacements[2]) && (! $onlyTwoCompetitors || $finalist2Forfeited))
                                                <p class="flex items-center gap-2 text-base text-gray-700 dark:text-gray-300 mt-1"><span class="text-2xl leading-none">🥈</span> {{ $bracketPlacements[2] }}</p>
                                            @endif
                                            @if (! $onlyTwoCompetitors && $placementCap >= 3 && isset($bracketPlacements[3]))
                                                <p class="flex items-center gap-2 text-base text-gray-700 dark:text-gray-300 mt-1"><span class="text-2xl leading-none">🥉</span> {{ $bracketPlacements[3] }}</p>
                                            @endif
                                        </div>
                                    @endif
                                @endif
                                </div>{{-- end wire:key bracket wrapper --}}
                            @else
                                {{-- Standard scoring (judges / win-loss / first-to-n) --}}

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
                                             class="rounded-lg border {{ $result->disqualified ? 'opacity-60' : '' }} border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900">

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
                                                                class="shrink-0 {{ $result->note ? 'text-primary-500' : 'text-gray-400 hover:text-primary-500 dark:hover:text-primary-400' }} transition-colors">
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
                                                        @switch($result->placement)
                                                            @case(1) <span class="text-3xl leading-none">🥇</span> @break
                                                            @case(2) <span class="text-3xl leading-none">🥈</span> @break
                                                            @case(3) <span class="text-3xl leading-none">🥉</span> @break
                                                            @default <span class="text-base font-bold text-gray-500 dark:text-gray-400">#{{ $result->placement }}</span>
                                                        @endswitch
                                                    </div>
                                                @endif

                                                @if (! $isReadOnly)
                                                    <div class="shrink-0">
                                                        @if ($method === 'win_loss')
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
                                            </div>{{-- end flex items-center --}}

                                            {{-- Non-judged: penalty buttons + undo + log + note --}}
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
                                                                class="px-2 py-1 rounded text-xs border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-slate-800 text-gray-600 dark:text-gray-400 active:scale-95 transition-transform">
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
                                            </div>{{-- end px-3 py-3 --}}

                                            {{-- Expandable judge score sheet --}}
                                            @if (! $isReadOnly && in_array($method, ['judges_total', 'judges_average']))
                                                <div x-show="open" x-transition
                                                     class="border-t border-gray-100 dark:border-slate-700 px-3 pb-3 pt-3 space-y-3">
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
                                                                                    class="w-11 h-11 flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-slate-800 text-gray-700 dark:text-gray-200 text-xl font-medium active:scale-95 transition-transform"
                                                                                    @if ($isSaved) disabled @endif>−</button>
                                                                                <input type="number" step="0.1"
                                                                                    min="{{ $catMin }}"
                                                                                    @if ($catMax !== null) max="{{ $catMax }}" @endif
                                                                                    wire:model.blur="categoryScores.{{ $result->id }}.{{ $j }}.{{ $cat->id }}"
                                                                                    data-cat-j="{{ $j }}" data-cat-id="{{ $cat->id }}"
                                                                                    class="flex-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-900 text-gray-900 dark:text-white text-base py-2.5 px-3 text-center"
                                                                                    placeholder="{{ number_format($catMin, 1) }}"
                                                                                    @if ($isSaved) disabled @endif />
                                                                                <button type="button"
                                                                                    x-on:click="const i=$el.previousElementSibling; const v=Math.round((parseFloat(i.value||{{ $catMin }})+0.1)*10)/10; i.value={{ $catMax !== null ? 'Math.min('.$catMax.',v)' : 'v' }}.toFixed(1); i.dispatchEvent(new Event('input',{bubbles:true}));"
                                                                                    class="w-11 h-11 flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-slate-800 text-gray-700 dark:text-gray-200 text-xl font-medium active:scale-95 transition-transform"
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
                                                                    class="flex-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-900 text-gray-900 dark:text-white text-base py-2.5 px-3 text-center {{ ($isSaved && $isDropped) ? 'opacity-40' : ($isSaved ? 'opacity-50' : '') }}"
                                                                    placeholder="{{ $judgeMin ?? '0.0' }}"
                                                                    @if ($isSaved) disabled @endif />
                                                                @if (! $isSaved)
                                                                    <div class="flex gap-1 shrink-0">
                                                                        <button type="button"
                                                                            x-on:click="let v = Math.round((parseFloat($refs.inp.value || 0) - 0.1) * 10) / 10; $refs.inp.value = Math.max(0, v).toFixed(1); $refs.inp.dispatchEvent(new Event('input', {bubbles: true}));"
                                                                            class="w-11 h-11 flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-slate-800 text-gray-700 dark:text-gray-200 text-xl font-medium active:scale-95 transition-transform">−</button>
                                                                        <button type="button"
                                                                            x-on:click="let v = Math.round((parseFloat($refs.inp.value || 0) + 0.1) * 10) / 10; $refs.inp.value = Math.min(10, v).toFixed(1); $refs.inp.dispatchEvent(new Event('input', {bubbles: true}));"
                                                                            class="w-11 h-11 flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-slate-800 text-gray-700 dark:text-gray-200 text-xl font-medium active:scale-95 transition-transform">+</button>
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
                                                                class="w-full rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-900 text-base text-gray-900 dark:text-white px-2 py-2">
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
                                                     class="border-t border-gray-100 dark:border-slate-700 px-3 pb-3 pt-3 space-y-3">
                                                    <div>
                                                        <div class="flex items-center justify-between mb-2">
                                                            <span class="text-sm font-medium {{ $atTarget ? 'inline-block bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300 rounded px-1 winner-halo' : 'text-gray-700 dark:text-gray-300' }}">
                                                                Points: <strong>{{ (int) ($result->total_score ?? 0) }}</strong>
                                                                @if ($targetScore) <span class="{{ $atTarget ? '' : 'text-gray-400' }}">/ {{ $targetScore }}</span> @endif
                                                            </span>
                                                            <button type="button" wire:click="undoPoints({{ $result->id }})"
                                                                @if (! $hasEvents) disabled @endif
                                                                class="flex items-center gap-1 text-xs px-2.5 py-1.5 rounded border {{ $hasEvents ? 'border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-slate-800 text-gray-700 dark:text-gray-200 active:scale-95' : 'border-gray-200 dark:border-gray-700 text-gray-300 dark:text-gray-600 cursor-not-allowed' }} transition-transform">
                                                                <x-heroicon-m-arrow-uturn-left class="w-3.5 h-3.5" /> Undo
                                                            </button>
                                                        </div>
                                                        <div class="flex flex-wrap gap-2">
                                                            @foreach ($incrementButtons as $btn)
                                                                <button type="button"
                                                                    wire:click="addPoints({{ $result->id }}, {{ $btn }})"
                                                                    @if ($atTarget) disabled @endif
                                                                    class="flex-1 min-w-[3rem] h-14 flex items-center justify-center rounded-lg text-xl font-semibold shadow-sm transition-transform {{ $atTarget ? 'bg-gray-100 dark:bg-slate-800 text-gray-300 dark:text-gray-600 cursor-not-allowed' : 'bg-primary-600 dark:bg-primary-500 text-white active:scale-95' }}">
                                                                    +{{ $btn }}
                                                                </button>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                    {{-- Raw input fallback --}}
                                                    <details class="text-xs text-gray-400">
                                                        <summary class="cursor-pointer select-none">Manual entry</summary>
                                                        <div class="flex items-center gap-2 mt-2">
                                                            <input type="number" min="0"
                                                                wire:model="pointsInput.{{ $result->id }}"
                                                                class="flex-1 text-center rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-900 text-gray-900 dark:text-white text-base py-2 px-3"
                                                                placeholder="0" />
                                                            <x-filament::button size="sm" color="gray"
                                                                wire:click="savePoints({{ $result->id }})"
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
                                        <thead>
                                            <tr class="border-b border-gray-200 dark:border-slate-700 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">
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
                                                @endphp
                                                <tbody wire:key="dtrow-{{ $result->id }}"
                                                    data-scoring-key="row-{{ $result->id }}"
                                                    class="border-b border-gray-100 dark:border-slate-800 last:border-b-0">
                                                <tr class="{{ $result->disqualified ? 'opacity-50' : '' }}">
                                                    <td class="py-2 pr-4">
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
                                                                    class="shrink-0 {{ $result->note ? 'text-primary-500' : 'text-gray-400 hover:text-primary-500 dark:hover:text-primary-400' }} transition-colors">
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
                                                            $isSaved          = in_array($result->id, $this->savedResultIds);
                                                            $inTiebreakerFlow = $result->tiebreaker_score !== null || $result->placement_overridden;
                                                            $rawScores        = array_filter(array_values($this->judgeScores[$result->id] ?? []), fn ($v) => $v !== null && $v !== '');
                                                            $scoreCount       = count($rawScores);
                                                            $liveTotal        = $scoreCount > 0
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
                                                                            class="w-6 h-7 flex items-center justify-center rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-slate-800 text-gray-700 dark:text-gray-200 font-medium active:scale-95 transition-transform"
                                                                            @if ($isSaved) disabled @endif>−</button>
                                                                        <input type="number" step="0.1"
                                                                            @if ($judgeMin !== null) min="{{ $judgeMin }}" @else min="0" @endif
                                                                            @if ($judgeMax !== null) max="{{ $judgeMax }}" @endif
                                                                            wire:model="judgeScores.{{ $result->id }}.{{ $j }}"
                                                                            class="w-12 text-center rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-900 text-gray-900 dark:text-white text-base py-0.5 px-0.5"
                                                                            placeholder="{{ $judgeMin ?? '0.0' }}"
                                                                            @if ($isSaved) disabled @endif />
                                                                        <button type="button"
                                                                            x-on:click="const i=$el.previousElementSibling; const v=Math.round((parseFloat(i.value||0)+0.1)*10)/10; i.value=Math.min(10,v).toFixed(1); i.dispatchEvent(new Event('input',{bubbles:true}));"
                                                                            class="w-6 h-7 flex items-center justify-center rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-slate-800 text-gray-700 dark:text-gray-200 font-medium active:scale-95 transition-transform"
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
                                                                            class="h-7 px-2 flex items-center justify-center rounded text-sm font-semibold shadow-sm transition-transform {{ $atTarget ? 'bg-gray-100 dark:bg-slate-800 text-gray-300 dark:text-gray-600 cursor-not-allowed' : 'bg-primary-600 dark:bg-primary-500 text-white active:scale-95' }}">
                                                                            +{{ $btn }}
                                                                        </button>
                                                                    @endforeach
                                                                    <button type="button"
                                                                        wire:click="undoPoints({{ $result->id }})"
                                                                        @if (! $hasEvents) disabled @endif
                                                                        class="h-7 px-2 flex items-center justify-center rounded border text-xs transition-transform {{ $hasEvents ? 'border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-slate-800 text-gray-600 dark:text-gray-300 active:scale-95' : 'border-gray-200 dark:border-gray-700 text-gray-300 dark:text-gray-600 cursor-not-allowed' }}">
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
                                                                class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-900 text-base text-gray-900 dark:text-white px-1 py-0.5 w-14">
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
                                                                        wire:click="toggleDisqualify({{ $result->id }})"
                                                                        :disabled="$inTiebreakerFlow ?? false">
                                                                        {{ $result->disqualified ? 'Un-DQ' : 'DQ' }}
                                                                    </x-filament::button>
                                                                @endif
                                                                @if (in_array($method, ['judges_total', 'judges_average']))
                                                                    @if ($isSaved)
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
                                                                            class="w-6 h-7 flex items-center justify-center rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-slate-800 text-gray-700 dark:text-gray-200 font-medium active:scale-95 transition-transform"
                                                                            @if ($isSaved) disabled @endif>−</button>
                                                                        <input type="number" step="0.1"
                                                                            min="{{ $catMin }}"
                                                                            @if ($catMax !== null) max="{{ $catMax }}" @endif
                                                                            wire:model.blur="categoryScores.{{ $result->id }}.{{ $j }}.{{ $cat->id }}"
                                                                            data-cat-j="{{ $j }}" data-cat-id="{{ $cat->id }}"
                                                                            class="w-12 text-center rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-900 text-gray-900 dark:text-white text-base py-0.5 px-0.5"
                                                                            placeholder="{{ number_format($catMin, 1) }}"
                                                                            @if ($isSaved) disabled @endif />
                                                                        <button type="button"
                                                                            x-on:click="const i=$el.previousElementSibling; const v=Math.round((parseFloat(i.value||{{ $catMin }})+0.1)*10)/10; i.value={{ $catMax !== null ? 'Math.min('.$catMax.',v)' : 'v' }}.toFixed(1); i.dispatchEvent(new Event('input',{bubbles:true}));"
                                                                            class="w-6 h-7 flex items-center justify-center rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-slate-800 text-gray-700 dark:text-gray-200 font-medium active:scale-95 transition-transform"
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
                        @endif

                        {{-- Sudden death tiebreaker (hidden when placement override mode is active) --}}
                        @if (! $this->rollcallMode && ! $this->isRoundRobin() && ! $this->placementOverrideMode)
                            @php
                                $tiedGroups   = $this->getTiedGroups();
                                $defaultScore = $this->selectedDivision?->competitionEvent->default_score;
                            @endphp
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
                                                         class="rounded-lg border border-warning-200 dark:border-warning-700 bg-white dark:bg-slate-900">

                                                        {{-- Card header --}}
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
                                                            @endif

                                                            @if (! $isReadOnly)
                                                                @if (! $tbSaved && ! $result->placement_overridden)
                                                                    @php
                                                                        $tbOncePenalties = array_filter($enabledPenalties, fn ($t) => in_array($t, ['dq', 'forfeit']));
                                                                    @endphp
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
                                                                        {{-- locked by head judge, no action --}}
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

                                                        {{-- Expandable judge inputs --}}
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
                                                                                                class="w-11 h-11 flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-slate-800 text-gray-700 dark:text-gray-200 text-xl font-medium active:scale-95 transition-transform">−</button>
                                                                                            <input type="number" step="0.1"
                                                                                                min="{{ $catMin }}"
                                                                                                @if ($catMax !== null) max="{{ $catMax }}" @endif
                                                                                                value="{{ $this->tbPendingCat[$result->id][$j][$cat->id] ?? ($defaultScore !== null ? number_format((float)$defaultScore, 1) : '') }}"
                                                                                                data-cat-j="{{ $j }}" data-cat-id="{{ $cat->id }}"
                                                                                                class="flex-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-900 text-gray-900 dark:text-white text-base py-2.5 px-3 text-center"
                                                                                                placeholder="{{ number_format($catMin, 1) }}" />
                                                                                            <button type="button"
                                                                                                x-on:click="const i=$el.previousElementSibling; const v=Math.round((parseFloat(i.value||{{ $catMin }})+0.1)*10)/10; i.value={{ $catMax !== null ? 'Math.min('.$catMax.',v)' : 'v' }}.toFixed(1); i.dispatchEvent(new Event('input',{bubbles:true}));"
                                                                                                class="w-11 h-11 flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-slate-800 text-gray-700 dark:text-gray-200 text-xl font-medium active:scale-95 transition-transform">+</button>
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
                                                                                    class="flex-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-900 text-gray-900 dark:text-white text-base py-2.5 px-3 text-center"
                                                                                    placeholder="0.0" />
                                                                                <div class="flex gap-1 shrink-0">
                                                                                    <button type="button"
                                                                                        x-on:click="tbJ['{{ $j }}'] = Math.max(0, Math.round((parseFloat(tbJ['{{ $j }}']||0)-0.1)*10)/10).toFixed(1)"
                                                                                        class="w-11 h-11 flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-slate-800 text-gray-700 dark:text-gray-200 text-xl font-medium active:scale-95 transition-transform">−</button>
                                                                                    <button type="button"
                                                                                        x-on:click="tbJ['{{ $j }}'] = Math.min(10, Math.round((parseFloat(tbJ['{{ $j }}']||0)+0.1)*10)/10).toFixed(1)"
                                                                                        class="w-11 h-11 flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-slate-800 text-gray-700 dark:text-gray-200 text-xl font-medium active:scale-95 transition-transform">+</button>
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
                                                                            @php
                                                                                $tbJScore = $result->judgeScores->where('is_tiebreaker', true)->where('judge_number', $j)->first();
                                                                            @endphp
                                                                            <span class="text-base {{ $tbIsDropped ? 'line-through text-gray-400 dark:text-gray-500' : 'text-gray-700 dark:text-gray-300' }} {{ ($tbSaved && ! $tbIsDropped) ? 'opacity-50' : '' }}">
                                                                                {{ $tbJScore ? number_format((float) $tbJScore->score, 1) : '—' }}
                                                                            </span>
                                                                        @elseif ($hasCategories)
                                                                            <span class="text-base text-gray-500 dark:text-gray-400">—</span>
                                                                        @else
                                                                            <div class="flex items-center gap-1">
                                                                                <button type="button"
                                                                                    x-on:click="tbJ['{{ $j }}'] = Math.max(0, Math.round((parseFloat(tbJ['{{ $j }}']||0)-0.1)*10)/10).toFixed(1)"
                                                                                    class="w-7 h-7 flex items-center justify-center rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-slate-800 text-gray-700 dark:text-gray-200 font-medium active:scale-95 transition-transform">−</button>
                                                                                <input type="number" step="0.1" min="0" max="10"
                                                                                    x-model="tbJ['{{ $j }}']"
                                                                                    class="w-[3.25rem] text-center rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-900 text-gray-900 dark:text-white text-base py-0.5 px-1"
                                                                                    placeholder="0.0" />
                                                                                <button type="button"
                                                                                    x-on:click="tbJ['{{ $j }}'] = Math.min(10, Math.round((parseFloat(tbJ['{{ $j }}']||0)+0.1)*10)/10).toFixed(1)"
                                                                                    class="w-7 h-7 flex items-center justify-center rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-slate-800 text-gray-700 dark:text-gray-200 font-medium active:scale-95 transition-transform">+</button>
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
                                                                    @else
                                                                        <span class="text-gray-400">—</span>
                                                                    @endif
                                                                </td>
                                                                @if (! $isReadOnly)
                                                                    <td class="py-2">
                                                                        @if ($result->placement_overridden)
                                                                            <x-filament::button size="xs" color="gray" disabled>
                                                                                Undo
                                                                            </x-filament::button>
                                                                        @elseif ($tbSaved)
                                                                            <x-filament::button size="xs" color="gray"
                                                                                wire:click="clearTiebreakerScore({{ $result->id }})">
                                                                                Undo
                                                                            </x-filament::button>
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
                                                                                        class="w-6 h-7 flex items-center justify-center rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-slate-800 text-gray-700 dark:text-gray-200 font-medium active:scale-95 transition-transform">−</button>
                                                                                    <input type="number" step="0.1"
                                                                                        min="{{ $catMin }}"
                                                                                        @if ($catMax !== null) max="{{ $catMax }}" @endif
                                                                                        value="{{ $this->tbPendingCat[$result->id][$j][$cat->id] ?? ($defaultScore !== null ? number_format((float)$defaultScore, 1) : '') }}"
                                                                                        data-cat-j="{{ $j }}" data-cat-id="{{ $cat->id }}"
                                                                                        class="w-9 text-center rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-900 text-gray-900 dark:text-white text-base py-0.5 px-0.5"
                                                                                        placeholder="{{ number_format($catMin, 1) }}" />
                                                                                    <button type="button"
                                                                                        x-on:click="const i=$el.previousElementSibling; const v=Math.round((parseFloat(i.value||{{ $catMin }})+0.1)*10)/10; i.value={{ $catMax !== null ? 'Math.min('.$catMax.',v)' : 'v' }}.toFixed(1); i.dispatchEvent(new Event('input',{bubbles:true}));"
                                                                                        class="w-6 h-7 flex items-center justify-center rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-slate-800 text-gray-700 dark:text-gray-200 font-medium active:scale-95 transition-transform">+</button>
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

                            {{-- Show tiebreaker scores already recorded --}}
                            @php
                                $withTiebreaker = $this->competitorRows
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
                                                            class="shrink-0 rounded border border-warning-300 dark:border-warning-600 bg-white dark:bg-slate-900 text-base text-gray-900 dark:text-white px-2 py-1.5">
                                                            <option value="">— Place —</option>
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

                        {{-- Panel footer --}}
                        @if ($div->status !== 'complete')
                            <div class="mt-4 flex items-center justify-between gap-3">
                                @if (! $this->manualPairingMode)
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
                                @endif
                                <div class="flex items-center gap-2">
                                    @if (! $this->manualPairingMode)
                                    @if ($this->rollcallMode)
                                        <x-filament::button color="primary" size="sm"
                                            x-on:click="$dispatch('begin-scoring-pressed')"
                                            icon="heroicon-m-arrow-right" icon-position="after">
                                            Begin Scoring
                                        </x-filament::button>
                                    @endif
                                    @if (! $this->rollcallMode && $this->rollcallRequired)
                                        <x-filament::button color="gray" size="sm"
                                            x-on:click="$dispatch('open-modal', { id: 'confirm-rollcall-return' })"
                                            icon="heroicon-m-arrow-left">
                                            Back to rollcall
                                        </x-filament::button>
                                    @endif
                                    @if (! $this->rollcallMode && $this->isTournament() && ! $this->bracketExists)
                                        <x-filament::button color="primary" size="sm"
                                            wire:click="generateBracket"
                                            icon="heroicon-m-arrow-right" icon-position="after">
                                            Generate bracket
                                        </x-filament::button>
                                    @endif
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
                            <div class="mt-4 flex flex-col gap-2">
                                @if ($div->completed_at)
                                    @php
                                        $completedUser = $div->completedBy;
                                        $completedName = $completedUser?->selfProfile?->full_name;
                                        $completedDisplay = $completedName
                                            ? "{$completedName} ({$completedUser->email})"
                                            : ($completedUser?->email ?? 'Unknown');
                                    @endphp
                                    <p class="text-center text-xs text-gray-500 dark:text-gray-400">
                                        Completed by {{ $completedDisplay }}
                                        on {{ $div->completed_at->format('d M Y \a\t g:i A') }}
                                    </p>
                                @endif
                                <div class="flex items-center justify-between gap-3">
                                    <x-filament::button color="gray" size="sm" wire:click="closeSelf">
                                        Close
                                    </x-filament::button>
                                    <x-filament::button color="warning" size="sm"
                                        x-on:click="$dispatch('open-modal', { id: 'confirm-reactivate-division' })">
                                        Re-activate scoring
                                    </x-filament::button>
                                </div>
                            </div>
                        @endif
                    </div>
    @endif

    <div class="h-0 overflow-hidden">
        <x-filament::modal id="confirm-cancel-scoring" width="sm">
        <x-slot name="heading">Cancel scoring?</x-slot>
        <x-slot name="description">All scores and placements will be cleared.</x-slot>
        <x-slot name="footerActions">
            <x-filament::button color="danger"
                wire:click="cancelScoring"
                x-on:click="window.dispatchEvent(new CustomEvent('scoring-cancel-confirmed')); $dispatch('close-modal', { id: 'confirm-cancel-scoring' })">
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

    {{-- Penalty reason selection modal --}}
    <x-filament::modal id="penalty-reason-modal" width="sm" :close-by-clicking-away="false">
        <x-slot name="heading">
            Select reason
            @if ($penaltyModalType)
                <span class="ml-1 text-sm font-normal text-gray-500 dark:text-gray-400">— {{ $this->getPenaltyLabel($penaltyModalType) }}</span>
            @endif
        </x-slot>
        <div class="space-y-2">
            @foreach ($penaltyModalReasons as $reason)
                <button type="button"
                    wire:click="confirmPenalty(@js($reason))"
                    x-on:click="$dispatch('close-modal', { id: 'penalty-reason-modal' })"
                    class="w-full text-left px-3 py-2 rounded-lg border text-sm transition-colors border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-primary-400 hover:bg-primary-50 dark:hover:border-primary-600 dark:hover:bg-primary-900/20 active:scale-95">
                    {{ $reason }}
                </button>
            @endforeach
            @if (in_array($penaltyModalType, ['dq', 'forfeit']))
                <div class="{{ $penaltyModalReasons ? 'border-t border-gray-100 dark:border-gray-700 pt-3 mt-1' : '' }}">
                    <input type="text"
                        wire:model="penaltyModalFreeText"
                        placeholder="Enter reason (optional)..."
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-900 text-gray-900 dark:text-white text-sm py-2 px-3 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                </div>
            @endif
        </div>
        <x-slot name="footerActions">
            @if (in_array($penaltyModalType, ['dq', 'forfeit']))
                <x-filament::button
                    wire:click="confirmPenalty"
                    x-on:click="$dispatch('close-modal', { id: 'penalty-reason-modal' })">
                    Apply
                </x-filament::button>
            @endif
            <x-filament::button color="gray"
                x-on:click="$dispatch('close-modal', { id: 'penalty-reason-modal' })">
                Cancel
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

    {{-- Note modal --}}
    <div
        x-data="{ noteResultId: null, noteText: '' }"
        x-on:open-note-modal.window="noteResultId = $event.detail.resultId; noteText = $event.detail.note; $dispatch('open-modal', { id: 'note-modal' })"
    >
        <x-filament::modal id="note-modal" width="md">
            <x-slot name="heading">Note</x-slot>
            <textarea x-model="noteText" rows="5"
                placeholder="Add a note about this competitor…"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-900 text-gray-900 dark:text-white text-sm py-2 px-3 focus:outline-none focus:ring-1 focus:ring-primary-500 resize-none"></textarea>
            <x-slot name="footerActions">
                <x-filament::button x-on:click="$wire.saveNote(noteResultId, noteText)">Save</x-filament::button>
                <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'note-modal' })">Cancel</x-filament::button>
            </x-slot>
        </x-filament::modal>
    </div>
    </div>{{-- end h-0 modals wrapper --}}
</div>