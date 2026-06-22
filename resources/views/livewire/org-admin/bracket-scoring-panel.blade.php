<div x-data="{}">
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
            <div class="rounded-xl border border-warning-300 bg-white dark:bg-gray-800 dark:border-warning-700 p-6 max-w-sm w-full shadow-xl">
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

@php
    $rows             = $this->competitorRows;
    $isReadOnly       = $div->status === 'complete';
    $scoringMethod    = $this->getScoringMethod();
    $isScored         = in_array($scoringMethod, ['judges_total', 'judges_average', 'first_to_n', 'timed_points']);
    $targetScore      = $scoringMethod === 'first_to_n' ? $this->getTargetScore() : null;
    $incrementButtons = in_array($scoringMethod, ['first_to_n', 'timed_points']) ? $this->getIncrementButtons() : [];
    $roundDuration    = in_array($scoringMethod, ['first_to_n', 'timed_points', 'win_loss']) ? $this->getRoundDuration() : null;
    $tbDuration       = in_array($scoringMethod, ['first_to_n', 'timed_points']) ? $this->getTiebreakerDuration() : null;
    $tbMode           = in_array($scoringMethod, ['first_to_n', 'timed_points']) ? $this->getTiebreakerMode() : 'sudden_death';
    $overtimeRounds   = $tbMode === 'overtime' ? $this->getOvertimeRounds() : 1;
    $enabledPenalties = $this->getEnabledPenaltyTypes();
    $dqViaPenalties   = in_array('dq', $enabledPenalties);
    $hasBracket       = $this->bracketExists;
    $format           = $this->getTournamentFormat();
    $competitorCount  = $rows->count();
    $bracketData      = $hasBracket ? $this->getBracketData() : [];
@endphp

{{-- wire:key changes on any bracket structural change (new matches) or completion, forcing full replacement --}}
<div wire:key="bracket-{{ $this->division_id }}-{{ $hasBracket ? 'has' : 'empty' }}-{{ collect($bracketData)->flatten(2)->count() }}-{{ $this->isScoringComplete() ? 'done' : 'active' }}">

@if (! $hasBracket)
    @if ($this->manualPairingMode)
        {{-- Manual pairing wizard --}}
        @php
            $isOddPairing   = (count($this->pairingCompetitorList) % 2 !== 0);
            $byeAlreadyUsed = collect($this->manualPairings)->contains(fn ($p) => ($p['away'] ?? '') === 'bye');
            $usedPairingIds = collect($this->manualPairings)
                ->flatMap(fn ($p) => [
                    isset($p['home']) && $p['home'] !== '' ? (int) $p['home'] : null,
                    isset($p['away']) && $p['away'] !== '' && $p['away'] !== 'bye' ? (int) $p['away'] : null,
                ])
                ->filter()
                ->all();
        @endphp
        <div class="space-y-2"
             x-data="{
                 refreshDisabled() {
                     const selects = $el.querySelectorAll('.pairing-select');
                     const usedIds = new Set([...selects].map(s => s.value).filter(v => v && v !== ''));
                     selects.forEach(sel => {
                         const own = sel.value;
                         sel.querySelectorAll('option').forEach(opt => {
                             if (!opt.value) return;
                             opt.disabled = usedIds.has(opt.value) && opt.value !== own;
                         });
                     });
                 }
             }"
             x-init="
                 $nextTick(() => refreshDisabled());
                 $wire.$watch('manualPairings', () => setTimeout(() => refreshDisabled(), 0));
             "
             x-on:change.capture="$nextTick(() => refreshDisabled())">
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
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 px-3 py-2.5">
                    <p class="text-xs font-medium text-gray-400 mb-2">Match {{ $slotIdx + 1 }}</p>
                    <div class="flex items-center gap-2 flex-wrap">
                        <select wire:model.live="manualPairings.{{ $slotIdx }}.home"
                            class="pairing-select flex-1 min-w-32 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white py-1.5 px-2">
                            <option value="">— Select competitor —</option>
                            @foreach ($this->pairingCompetitorList as $comp)
                                @if (! in_array($comp['ee_id'], $usedPairingIds) || $comp['ee_id'] === $slotHomeId)
                                    <option value="{{ $comp['ee_id'] }}">{{ $comp['name'] }}{{ $comp['info'] ? ' (' . $comp['info'] . ')' : '' }}</option>
                                @endif
                            @endforeach
                        </select>
                        <span class="text-xs text-gray-400 shrink-0">vs</span>
                        <select wire:model.live="manualPairings.{{ $slotIdx }}.away"
                            class="pairing-select flex-1 min-w-32 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white py-1.5 px-2">
                            <option value="">— Select competitor —</option>
                            @if ($isOddPairing && (! $byeAlreadyUsed || ($pair['away'] ?? '') === 'bye'))
                                <option value="bye">Bye (advances automatically)</option>
                            @endif
                            @foreach ($this->pairingCompetitorList as $comp)
                                @if (! in_array($comp['ee_id'], $usedPairingIds) || $comp['ee_id'] === $slotAwayId)
                                    <option value="{{ $comp['ee_id'] }}">{{ $comp['name'] }}{{ $comp['info'] ? ' (' . $comp['info'] . ')' : '' }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                </div>
            @endforeach

            <div class="flex justify-end gap-2 pt-1">
                <x-filament::button size="sm" color="gray" wire:click="cancelPairing">
                    Cancel
                </x-filament::button>
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
            $wbR1Count    = count($bracketData['winners'][1] ?? []);
            $wbFinalRound = $wbR1Count > 1 ? (int) ceil(log($wbR1Count, 2)) + 1 : 1;

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
                $bkRounds   = $bracketData[$bk] ?? [];
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
            $displayBracketKey = $displaySection['key'];
            $rounds            = $displaySection['rounds'];
            $sectionLabel      = $displaySection['label'];
            $sectionFirstRound = array_key_first($rounds ?? [1 => null]);
        @endphp
        <div wire:key="section-{{ $this->division_id }}-{{ $displayBracketKey }}-{{ $sectionFirstRound }}">

        @if ($sectionLabel)
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mt-4 mb-1">{{ $sectionLabel }}</p>
        @endif

        @foreach ($rounds as $roundNum => $matches)
            @php $visibleMatches = collect($matches)->filter(fn($m) => ! $m->is_bye); @endphp
            @if ($visibleMatches->isEmpty()) @continue @endif
            <div wire:key="round-{{ $this->division_id }}-{{ $displayBracketKey }}-{{ $roundNum }}" class="mb-3">
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
                            $isWaiting  = $pending && ($match->home_id === null || $match->away_id === null);
                            $homeWon    = $match->home_result === 'win';
                            $awayWon    = $match->home_result === 'loss';
                            $homeResult = ($rowsByEeId[$match->home_id] ?? null)?->result;
                            $awayResult = ($rowsByEeId[$match->away_id] ?? null)?->result;
                        @endphp
                        <div class="rounded-lg border border-l-4 px-3 py-2 text-sm
                            {{ ! $pending
                                ? 'border-success-200 dark:border-success-800 bg-success-50 dark:bg-success-900/20 border-l-success-500 dark:border-l-success-500'
                                : ($isWaiting
                                    ? 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 border-l-amber-400 dark:border-l-amber-500 opacity-50'
                                    : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 border-l-blue-400 dark:border-l-blue-500') }}">

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
                                                class="shrink-0 {{ $homeResult->note ? 'text-primary-500 animate-pulse' : 'text-gray-400 hover:text-primary-500 dark:hover:text-primary-400' }} transition-colors">
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
                                                class="shrink-0 {{ $awayResult->note ? 'text-primary-500 animate-pulse' : 'text-gray-400 hover:text-primary-500 dark:hover:text-primary-400' }} transition-colors">
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
                                                        class="h-9 w-9 flex items-center justify-center rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300 font-medium transition-transform"><x-heroicon-m-arrow-uturn-left class="w-4 h-4" /></button>
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
                                                        class="h-9 w-9 flex items-center justify-center rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300 font-medium transition-transform"><x-heroicon-m-arrow-uturn-left class="w-4 h-4" /></button>
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
                                                        class="h-9 w-9 flex items-center justify-center rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300 font-medium transition-transform"><x-heroicon-m-arrow-uturn-left class="w-4 h-4" /></button>
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
                                                        class="h-9 w-9 flex items-center justify-center rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300 font-medium transition-transform"><x-heroicon-m-arrow-uturn-left class="w-4 h-4" /></button>
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
        $allMatches       = collect($bracketData)->flatten(2);
        $pendingCount     = $allMatches->filter(fn($m) => $m->is_pending)->count();
        $isComplete       = $pendingCount === 0 && $allMatches->filter(fn($m) => ! $m->is_bye && $m->winner_id)->isNotEmpty();
        $bracketPlacements  = [];
        $onlyTwoCompetitors = false;

        $placementCap = 3;
        if ($isComplete) {
            $wbRounds           = $bracketData['winners'] ?? [];
            $wbFinalRound       = ! empty($wbRounds) ? max(array_keys($wbRounds)) : null;
            $onlyTwoCompetitors = ($wbFinalRound === 1);
            $_capEvent          = $div->competitionEvent;
            $placementCap       = match (true) {
                $competitorCount <= 2  => $_capEvent->awarded_places_2    ?? 2,
                $competitorCount === 3 => $_capEvent->awarded_places_3    ?? 3,
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

<div class="h-0 overflow-hidden">
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
</div>
</div>{{-- end wire:key bracket wrapper --}}
</div>{{-- root --}}
