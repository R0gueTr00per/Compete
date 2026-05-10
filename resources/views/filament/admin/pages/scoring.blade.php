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
                <x-filament::input.wrapper class="min-w-40">
                    <select wire:model.live="filter_location"
                        class="w-full block border-0 bg-transparent py-1.5 text-sm text-gray-900 dark:text-white focus:ring-0">
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
                    $selected = $this->division_id === $div->id;
                    $rowClass = match ($div->status) {
                        'complete'  => 'bg-success-50 border-success-300 dark:bg-success-900/20 dark:border-success-700',
                        'running'   => 'bg-warning-50 border-warning-300 dark:bg-warning-900/20 dark:border-warning-700',
                        'cancelled' => 'bg-danger-50 border-danger-300 dark:bg-danger-900/20 dark:border-danger-700',
                        default     => 'bg-white border-gray-200 dark:bg-gray-900 dark:border-gray-700',
                    };
                    $textClass = match ($div->status) {
                        'complete'  => 'text-success-800 dark:text-success-300',
                        'running'   => 'text-warning-800 dark:text-warning-300',
                        'cancelled' => 'text-danger-700 dark:text-danger-400',
                        default     => 'text-gray-900 dark:text-white',
                    };
                @endphp
                <div
                    wire:click="selectDivision({{ $div->id }})"
                    class="flex items-center justify-between gap-3 rounded-lg border px-4 py-3 cursor-pointer transition-all
                        {{ $rowClass }}
                        {{ $selected ? 'ring-2 ring-primary-500' : 'hover:border-primary-300 dark:hover:border-primary-600' }}"
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
                        <span class="text-xs text-gray-500">{{ $item->checked_in_count }} checked in</span>

                        @if ($div->status === 'complete')
                            <x-heroicon-m-check-circle class="w-5 h-5 text-success-500" />
                        @elseif ($div->status === 'running')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-warning-100 text-warning-800 dark:bg-warning-900 dark:text-warning-200">Running</span>
                        @elseif ($div->status === 'cancelled')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-danger-100 text-danger-800 dark:bg-danger-900 dark:text-danger-200">Cancelled</span>
                        @else
                            <x-heroicon-m-chevron-right class="w-4 h-4 text-gray-400 {{ $selected ? 'rotate-90' : '' }}" />
                        @endif
                    </div>
                </div>

                {{-- Inline scoring panel --}}
                @if ($selected)
                    @if ($div->status === 'cancelled')
                        <div class="ml-4 mb-2 rounded-lg border border-danger-200 dark:border-danger-700 bg-danger-50 dark:bg-danger-900/20 px-4 py-3">
                            <p class="text-sm font-medium text-danger-800 dark:text-danger-300">This division has been cancelled.</p>
                        </div>
                    @else
                    @php
                        $rows   = $this->getCompetitorRows();
                        $method = $this->getScoringMethod();
                        $judges = $this->getJudgeCount();
                    @endphp
                    <div class="ml-4 mb-2 rounded-lg border border-primary-200 dark:border-primary-700 bg-primary-50/50 dark:bg-primary-900/10 p-4">

                        {{-- Panel header: step indicator --}}
                        <div class="flex items-center justify-between mb-3">
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
                            <span class="text-xs text-gray-500">{{ $rows->count() }} checked in</span>
                        </div>

                        @if ($this->rollcallMode)
                            {{-- Step 1: Rollcall --}}
                            @php
                                $rollcall = $this->getRollcallRows();
                                $active   = $rollcall->where('absent', false);
                                $absent   = $rollcall->where('absent', true);
                            @endphp
                            @if ($rollcall->isEmpty())
                                <p class="text-center text-sm text-gray-400 py-4">No checked-in competitors in this division.</p>
                            @else
                                <p class="text-xs text-gray-400 mb-3">Tap each competitor to confirm they are present.</p>
                                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach ($active as $rc)
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

                                @if ($absent->isNotEmpty())
                                    <div class="mt-3 border-t border-gray-100 dark:border-gray-800 pt-3">
                                        <p class="text-xs font-semibold uppercase tracking-wider text-danger-500 mb-2">Previously marked absent</p>
                                        <ul class="space-y-1.5">
                                            @foreach ($absent as $rc)
                                                <li class="flex items-center gap-3">
                                                    <x-heroicon-m-x-circle class="w-5 h-5 text-danger-400 shrink-0" />
                                                    <span class="text-sm text-gray-400 line-through">{{ $rc->name }}</span>
                                                    <button wire:click="undoRollcallRemoval({{ $rc->ee_id }})"
                                                        class="ml-auto text-xs text-primary-500 hover:text-primary-700 font-medium">
                                                        Undo
                                                    </button>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <div class="mt-4 flex justify-end">
                                    <x-filament::button color="primary" wire:click="toggleRollcall" icon="heroicon-m-arrow-right" icon-position="after">
                                        Begin Scoring
                                    </x-filament::button>
                                </div>
                            @endif

                        @else
                            {{-- Step 2: Scoring --}}
                            <div class="mb-3">
                                <button wire:click="toggleRollcall"
                                    class="flex items-center gap-1 text-xs text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
                                    <x-heroicon-m-arrow-left class="w-3 h-3" />
                                    Back to rollcall
                                </button>
                            </div>

                            @if ($rows->isEmpty())
                                <p class="text-center text-sm text-gray-400 py-4">No checked-in competitors in this division.</p>
                            @elseif ($this->isTournament())
                                {{-- Tournament bracket scoring --}}
                                @php
                                    $bracketData = $this->getBracketData();
                                    $format      = $this->getTournamentFormat();
                                    $hasBracket  = collect($bracketData)->flatten(2)->isNotEmpty();
                                @endphp

                                @if (! $hasBracket)
                                    <div class="text-center py-4">
                                        <p class="text-sm text-gray-500 mb-1">{{ $rows->count() }} competitors checked in.</p>
                                        <p class="text-xs text-gray-400 mb-3">
                                            {{ match($format) { 'double_elimination' => 'Double elimination bracket', 'round_robin' => 'Round robin', 'repechage' => 'Single elimination with repechage', default => 'Single elimination bracket' } }}
                                        </p>
                                        <x-filament::button color="primary" wire:click="generateBracket">
                                            Generate bracket
                                        </x-filament::button>
                                    </div>
                                @else
                                    {{-- Bracket header --}}
                                    <div class="flex items-center justify-between mb-3">
                                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">
                                            {{ match($format) { 'double_elimination' => 'Double elimination', 'round_robin' => 'Round robin', 'repechage' => 'Repechage', default => 'Single elimination' } }} bracket
                                        </p>
                                        <x-filament::button size="xs" color="gray"
                                            wire:click="resetBracket"
                                            wire:confirm="Delete all bracket results and start over?">
                                            Reset bracket
                                        </x-filament::button>
                                    </div>

                                    @php
                                        $sections = [
                                            'winners'     => 'Winners bracket',
                                            'losers'      => 'Losers bracket',
                                            'repechage'   => 'Repechage bracket',
                                            'grand_final' => 'Grand Final',
                                        ];
                                    @endphp

                                    @foreach ($sections as $bracketKey => $bracketLabel)
                                        @php $rounds = $bracketData[$bracketKey] ?? []; @endphp
                                        @if (empty($rounds)) @continue @endif

                                        @if ($format === 'double_elimination' || $format === 'repechage' || $bracketKey === 'grand_final')
                                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mt-4 mb-1">{{ $bracketLabel }}</p>
                                        @endif

                                        @foreach ($rounds as $roundNum => $matches)
                                            <div class="mb-3">
                                                @if (count($rounds) > 1 || $bracketKey !== 'grand_final')
                                                    <p class="text-xs text-gray-400 mb-1.5">
                                                        @if ($bracketKey === 'grand_final') Grand Final
                                                        @else Round {{ $roundNum }}
                                                        @endif
                                                    </p>
                                                @endif

                                                <div class="space-y-1.5">
                                                    @foreach ($matches as $match)
                                                        @if ($match->is_bye) @continue @endif

                                                        @php
                                                            $pending   = $match->is_pending;
                                                            $homeWon   = $match->home_result === 'win';
                                                            $awayWon   = $match->home_result === 'loss';
                                                        @endphp
                                                        <div class="rounded-lg border px-3 py-2 text-sm
                                                            {{ ! $pending ? 'border-success-200 dark:border-success-800 bg-success-50 dark:bg-success-900/20' : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900' }}">

                                                            <div class="flex items-center gap-2">
                                                                {{-- Home competitor --}}
                                                                <span class="flex-1 font-medium truncate
                                                                    {{ $homeWon ? 'text-success-700 dark:text-success-400' : ($awayWon ? 'text-gray-400 line-through' : 'text-gray-900 dark:text-white') }}">
                                                                    {{ $match->home_name }}
                                                                </span>

                                                                {{-- vs / result --}}
                                                                @if ($pending)
                                                                    <div class="flex gap-1 shrink-0">
                                                                        @if ($match->home_id)
                                                                            <x-filament::button size="xs" color="success"
                                                                                wire:click="recordBracketWinner({{ $match->id }}, {{ $match->home_id }})">
                                                                                ← Wins
                                                                            </x-filament::button>
                                                                        @endif
                                                                        @if ($match->away_id)
                                                                            <x-filament::button size="xs" color="success"
                                                                                wire:click="recordBracketWinner({{ $match->id }}, {{ $match->away_id }})">
                                                                                Wins →
                                                                            </x-filament::button>
                                                                        @endif
                                                                    </div>
                                                                @else
                                                                    <div class="flex items-center gap-1 shrink-0">
                                                                        <x-heroicon-m-trophy class="w-3.5 h-3.5 text-warning-500" />
                                                                        <button wire:click="clearBracketResult({{ $match->id }})"
                                                                            class="text-gray-300 hover:text-danger-400 transition-colors ml-1" title="Clear result">
                                                                            <x-heroicon-m-x-mark class="w-3 h-3" />
                                                                        </button>
                                                                    </div>
                                                                @endif

                                                                {{-- Away competitor --}}
                                                                <span class="flex-1 text-right font-medium truncate
                                                                    {{ $awayWon ? 'text-success-700 dark:text-success-400' : ($homeWon ? 'text-gray-400 line-through' : 'text-gray-900 dark:text-white') }}">
                                                                    {{ $match->away_name }}
                                                                </span>
                                                            </div>

                                                            @if (! $pending && $match->winner_id)
                                                                <p class="text-xs text-success-600 dark:text-success-400 mt-1">
                                                                    🏆 {{ $homeWon ? $match->home_name : $match->away_name }} advances
                                                                    @if ($bracketKey === 'losers' || $bracketKey === 'grand_final' || $bracketKey === 'repechage')
                                                                        @if ($match->loser_id) · {{ $homeWon ? $match->away_name : $match->home_name }} eliminated @endif
                                                                    @endif
                                                                </p>
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
                                                <p class="text-sm font-semibold text-gray-900 dark:text-white">🥇 {{ $bracketPlacements[1] }}</p>
                                            @endif
                                            @if (! $onlyTwoCompetitors && isset($bracketPlacements[2]))
                                                <p class="text-sm text-gray-700 dark:text-gray-300 mt-1">🥈 {{ $bracketPlacements[2] }}</p>
                                            @endif
                                            @if (! $onlyTwoCompetitors && isset($bracketPlacements[3]))
                                                <p class="text-sm text-gray-700 dark:text-gray-300 mt-1">🥉 {{ $bracketPlacements[3] }}</p>
                                            @endif
                                        </div>
                                    @endif
                                @endif
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
                                                <th class="pb-2">Actions</th>
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
                                                        @for ($j = 1; $j <= $judges; $j++)
                                                            <td class="py-2 pr-2">
                                                                <x-filament::input type="number" step="0.1" min="0" max="10"
                                                                    wire:model="judgeScores.{{ $result->id }}.{{ $j }}"
                                                                    class="w-16" placeholder="0.0" />
                                                            </td>
                                                        @endfor
                                                        <td class="py-2 pr-4">
                                                            <span class="font-semibold">
                                                                {{ $result->total_score ? number_format((float)$result->total_score, 1) : '—' }}
                                                            </span>
                                                            <x-filament::button size="xs" color="primary"
                                                                wire:click="saveJudgeScores({{ $result->id }})" class="ml-1">
                                                                Save
                                                            </x-filament::button>
                                                        </td>

                                                    @elseif ($method === 'win_loss')
                                                        <td class="py-2 pr-4">
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
                                                        </td>

                                                    @elseif ($method === 'first_to_n')
                                                        <td class="py-2 pr-4">
                                                            <div class="flex items-center gap-1">
                                                                <x-filament::input type="number" min="0"
                                                                    wire:model="pointsInput.{{ $result->id }}"
                                                                    class="w-16" placeholder="0" />
                                                                <x-filament::button size="xs" color="primary"
                                                                    wire:click="savePoints({{ $result->id }})">
                                                                    Save
                                                                </x-filament::button>
                                                            </div>
                                                        </td>
                                                    @endif

                                                    <td class="py-2 pr-4">
                                                        @if ($result->placement)
                                                            <span class="font-bold {{ $result->placement_overridden ? 'text-warning-600' : '' }}">
                                                                @switch($result->placement)
                                                                    @case(1) 🥇 @break
                                                                    @case(2) 🥈 @break
                                                                    @case(3) 🥉 @break
                                                                    @default {{ $result->placement }}
                                                                @endswitch
                                                                @if ($result->placement_overridden)
                                                                    <span class="text-xs font-normal">(ov)</span>
                                                                @endif
                                                            </span>
                                                        @else
                                                            <span class="text-gray-400">—</span>
                                                        @endif
                                                    </td>

                                                    <td class="py-2">
                                                        <div class="flex flex-wrap gap-1">
                                                            <x-filament::input type="number" min="1"
                                                                wire:model="placementInput.{{ $result->id }}"
                                                                class="w-12" placeholder="#" />
                                                            <x-filament::button size="xs" color="warning"
                                                                wire:click="overridePlacement({{ $result->id }})">
                                                                Override
                                                            </x-filament::button>
                                                            @if ($result->placement_overridden)
                                                                <x-filament::button size="xs" color="gray"
                                                                    wire:click="clearOverride({{ $result->id }})">
                                                                    Auto
                                                                </x-filament::button>
                                                            @endif
                                                            <x-filament::button size="xs"
                                                                color="{{ $result->disqualified ? 'gray' : 'danger' }}"
                                                                wire:click="toggleDisqualify({{ $result->id }})">
                                                                {{ $result->disqualified ? 'Un-DQ' : 'DQ' }}
                                                            </x-filament::button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        @endif

                        {{-- Sudden death tiebreaker --}}
                        @if (! $this->rollcallMode && ! $this->isRoundRobin())
                            @php $tiedGroups = $this->getTiedGroups(); @endphp
                            @if ($tiedGroups->isNotEmpty())
                                <div class="mt-4 rounded-lg border border-warning-300 dark:border-warning-700 bg-warning-50 dark:bg-warning-900/20 p-4">
                                    <p class="text-sm font-semibold text-warning-800 dark:text-warning-300 mb-3">
                                        ⚡ Sudden death required — {{ $tiedGroups->count() }} tie(s) detected
                                    </p>

                                    @foreach ($tiedGroups as $group)
                                        <div class="mb-4">
                                            <p class="text-xs font-medium text-warning-700 dark:text-warning-400 mb-2">
                                                Tied at {{ number_format((float) $group->first()->result->total_score, 1) }}:
                                                {{ $group->pluck('name')->join(', ') }}
                                            </p>

                                            <div class="space-y-2">
                                                @foreach ($group as $row)
                                                    @php $result = $row->result; @endphp
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <span class="text-sm font-medium text-gray-900 dark:text-white w-36 shrink-0">{{ $row->name }}</span>
                                                        @for ($j = 1; $j <= $judges; $j++)
                                                            <x-filament::input
                                                                type="number" step="0.1" min="0" max="10"
                                                                wire:model="tiebreakerJudgeInputs.{{ $result->id }}.{{ $j }}"
                                                                class="w-16" placeholder="J{{ $j }}" />
                                                        @endfor
                                                        <x-filament::button size="xs" color="warning"
                                                            wire:click="saveTiebreakerScores({{ $result->id }})">
                                                            Save
                                                        </x-filament::button>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Show tiebreaker scores already recorded, with option to clear --}}
                            @php
                                $withTiebreaker = $this->getCompetitorRows()
                                    ->filter(fn ($row) => $row->result->tiebreaker_score !== null);
                                $stillTied = $this->getStillTiedAfterTiebreaker();
                            @endphp
                            @if ($withTiebreaker->isNotEmpty())
                                <div class="mt-3 rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">Tiebreaker scores</p>
                                    @foreach ($withTiebreaker as $row)
                                        <div class="flex items-center justify-between gap-2 py-1 text-sm">
                                            <span class="text-gray-700 dark:text-gray-300">{{ $row->name }}</span>
                                            <div class="flex items-center gap-2">
                                                <span class="font-semibold">{{ number_format((float) $row->result->tiebreaker_score, 1) }}</span>
                                                <x-filament::button size="xs" color="gray"
                                                    wire:click="clearTiebreakerScore({{ $row->result->id }})">
                                                    Clear
                                                </x-filament::button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Head judge override when tiebreaker also ties --}}
                            @if ($stillTied->isNotEmpty())
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

                        {{-- Mark complete / Cancel --}}
                        @if ($div->status !== 'complete' && $div->status !== 'cancelled')
                            <div class="mt-4 flex items-center justify-between gap-3">
                                <x-filament::button color="danger" size="sm"
                                    wire:click="cancelDivision"
                                    wire:confirm="Cancel this division? It will be hidden from scoring and marked as cancelled.">
                                    Cancel division
                                </x-filament::button>
                                <x-filament::button color="success" size="sm"
                                    wire:click="markDivisionComplete"
                                    wire:confirm="Mark this division as complete? This cannot be undone.">
                                    Mark division complete
                                </x-filament::button>
                            </div>
                        @endif
                    </div>
                    @endif {{-- end non-cancelled panel --}}
                @endif
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
