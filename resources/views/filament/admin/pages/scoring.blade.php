<x-filament-panels::page>
    @php $divisionList = $this->getDivisionList(); @endphp
    @php $selectedComp = $this->competition_id ? \App\Models\Competition::find($this->competition_id) : null; @endphp
    @php $incompleteCount = $divisionList->filter(fn ($item) => $item->division->status !== 'complete')->count(); @endphp

    {{-- Top bar: competition + location --}}
    <div class="mb-2 rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 dark:border-primary-800 dark:bg-primary-950/30">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-primary-700 dark:text-primary-400">Competition</p>
        <div class="flex flex-wrap gap-3 items-center">
            <x-filament::input.wrapper class="flex-1 min-w-48 dark:bg-slate-900">
                <select wire:model.live="competition_id"
                    class="w-full block border-0 bg-transparent py-1.5 text-sm text-gray-900 dark:text-white focus:ring-0 dark:bg-slate-900">
                    <option value="">— Select competition —</option>
                    @foreach ($this->getCompetitions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </x-filament::input.wrapper>

            @php $locations = $this->getLocations(); @endphp
            @if (! empty($locations))
                <x-filament::input.wrapper class="min-w-40 dark:bg-slate-900">
                    <select wire:model.live="filter_location"
                        class="w-full block border-0 bg-transparent py-1.5 text-sm text-gray-900 dark:text-white focus:ring-0 dark:bg-slate-900">
                        <option value="">— All locations —</option>
                        @foreach ($locations as $loc)
                            <option value="{{ $loc }}">{{ $loc }}</option>
                        @endforeach
                    </select>
                </x-filament::input.wrapper>
            @endif

            @if ($this->competition_id && $selectedComp?->status === 'running' && ! $divisionList->isEmpty() && $incompleteCount > 0)
                <button wire:click="jumpToNextIncomplete"
                    class="inline-flex items-center gap-1 rounded-md border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-2 py-1 text-xs text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
                    <x-heroicon-m-arrow-down-circle class="w-3.5 h-3.5" />
                    Next incomplete ({{ $incompleteCount }})
                </button>
            @endif
        </div>
    </div>

    @if (! $this->competition_id)
        <p class="text-center text-gray-400 py-12">Select a competition to begin scoring.</p>
    @elseif ($selectedComp?->status !== 'running')
        <p class="text-center text-gray-400 py-12">Competition is not running yet. Start the competition to begin scoring.</p>
    @elseif ($divisionList->isEmpty())
        <p class="text-center text-gray-400 py-12">No divisions assigned to {{ $this->filter_location }}.</p>
    @else
        {{-- Timer navigation warning modal --}}
        <div x-data="{
                open: false,
                pendingId: null,
                show(id) { this.pendingId = id; this.open = true; },
                confirm() {
                    const mid = Alpine.store('roundTimer').matchId;
                    if (mid) window.dispatchEvent(new CustomEvent('timer-reset', { detail: { matchId: mid } }));
                    this.open = false;
                    $wire.selectDivision(this.pendingId);
                    this.pendingId = null;
                },
                cancel() {
                    this.open = false;
                    this.pendingId = null;
                    const divId = $wire.division_id;
                    if (divId) window.dispatchEvent(new CustomEvent('scroll-to-division', { detail: { divisionId: divId } }));
                }
             }"
             x-on:timer-nav-warn.window="show($event.detail.divisionId)">
            <template x-if="open">
                <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
                    <div class="rounded-xl border border-warning-300 bg-white dark:bg-slate-800 dark:border-warning-700 p-6 max-w-sm w-full shadow-xl">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white mb-1">Timer is running</p>
                        <p class="text-sm text-gray-600 dark:text-gray-300 mb-5">A round timer is active. Current time will be reset if you navigate away.</p>
                        <div class="flex gap-3 justify-end">
                            <x-filament::button color="gray" size="sm" @click="cancel()">Stay here</x-filament::button>
                            <x-filament::button color="warning" size="sm" @click="confirm()">Navigate away</x-filament::button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- Save while timer active confirmation modal --}}
        <div x-data="{
                open: false,
                matchId: null,
                show(id) { this.matchId = id; this.open = true; },
                confirm() { this.open = false; $wire.recordBracketScore(this.matchId); this.matchId = null; },
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

        {{-- Alpine timer component definition --}}
        <script>
            (function _registerMatchTimer() {
                const define = () => {
                    if (!Alpine.store('roundTimer')) {
                        Alpine.store('roundTimer', { running: false, active: false, sdActive: false, sdLocked: false, matchId: null });
                    } else {
                        const _s = Alpine.store('roundTimer');
                        if (_s.sdActive === undefined) _s.sdActive = false;
                        if (_s.active   === undefined) _s.active   = false;
                        if (_s.sdLocked === undefined) _s.sdLocked = false;
                    }

                    Alpine.data('matchTimer', (matchId, duration, tbDuration, tbMode, overtimeRounds) => ({
                    matchId,
                    tbMode:            tbMode || 'sudden_death',
                    overtimeRounds:    overtimeRounds || 1,
                    // duration/tbDuration are in whole seconds (from PHP).
                    // Internally track centiseconds (1/100 s) for sub-second display.
                    durationCs:        duration   * 100,
                    tbDurationCs:      tbDuration ? tbDuration * 100 : null,
                    remainingCs:       null,
                    phase:             'idle',
                    interval:          null,
                    startedAt:         null,
                    remainingAtStartCs: null,
                    sdNeeded:          false,
                    overtimeTied:      false,
                    overtimeRound:     0,
                    _audioCtx:         null,
                    _lastSave:         0,

                    init() { this.restore(); },

                    storageKey() { return 'timer_match_' + this.matchId; },

                    get displaySeconds() {
                        const cs   = this.remainingCs !== null ? this.remainingCs : this.durationCs;
                        const secs = Math.floor(cs / 100);
                        return Math.floor(secs / 60) + ':' + String(secs % 60).padStart(2, '0');
                    },
                    get displayCentis() {
                        const cs = this.remainingCs !== null ? this.remainingCs : this.durationCs;
                        return '.' + String(cs % 100).padStart(2, '0');
                    },

                    save() {
                        if (this.phase === 'idle' && !this.sdNeeded && !this.overtimeTied) { localStorage.removeItem(this.storageKey()); return; }
                        this._lastSave = Date.now();
                        localStorage.setItem(this.storageKey(), JSON.stringify({
                            phase: this.phase,
                            startedAt: this.startedAt,
                            remainingAtStartCs: this.remainingAtStartCs,
                            remainingCs: this.remainingCs,
                            sdNeeded: this.sdNeeded,
                            overtimeTied: this.overtimeTied,
                            overtimeRound: this.overtimeRound,
                        }));
                    },

                    restore() {
                        const raw = localStorage.getItem(this.storageKey());
                        if (!raw) return;
                        const s = JSON.parse(raw);
                        if (s.phase === 'running' || s.phase === 'tb_running') {
                            const elapsedCs = Math.floor((Date.now() - s.startedAt) / 10);
                            const rem = Math.max(0, s.remainingAtStartCs - elapsedCs);
                            if (rem > 0) {
                                this.phase              = s.phase;
                                this.remainingCs        = rem;
                                this.startedAt          = s.startedAt;
                                this.remainingAtStartCs = s.remainingAtStartCs;
                                this._tick();
                                this._syncStore();
                            } else {
                                this.phase       = s.phase === 'running' ? 'expired' : 'tb_expired';
                                this.remainingCs = 0;
                                this.save();
                            }
                        } else {
                            this.phase       = s.phase;
                            this.remainingCs = s.remainingCs;
                        }
                        this.sdNeeded     = s.sdNeeded     ?? false;
                        this.overtimeTied = s.overtimeTied ?? false;
                        this.overtimeRound = s.overtimeRound ?? 0;
                    },

                    start() {
                        this._bell(3);
                        this.remainingCs        = this.durationCs;
                        this.phase              = 'running';
                        this.startedAt          = Date.now();
                        this.remainingAtStartCs = this.durationCs;
                        this.save();
                        this._syncStore();
                        this._tick();
                    },

                    pause() {
                        clearInterval(this.interval);
                        this.phase = this.phase === 'running' ? 'paused' : 'tb_paused';
                        this._syncStore();
                        this.save();
                    },

                    resume() {
                        this.phase              = this.phase === 'paused' ? 'running' : 'tb_running';
                        this.startedAt          = Date.now();
                        this.remainingAtStartCs = this.remainingCs;
                        this.save();
                        this._syncStore();
                        this._tick();
                    },

                    reset() {
                        clearInterval(this.interval);
                        this.phase              = 'idle';
                        this.remainingCs        = null;
                        this.startedAt          = null;
                        this.remainingAtStartCs = null;
                        this.sdNeeded           = false;
                        this.overtimeTied       = false;
                        this.overtimeRound      = 0;
                        localStorage.removeItem(this.storageKey());
                        this._syncStore();
                    },

                    startTiebreaker() {
                        this._bell(1);
                        this.overtimeRound     += 1;
                        this.sdNeeded           = false;
                        clearInterval(this.interval);
                        this.remainingCs        = this.tbDurationCs;
                        this.phase              = 'tb_running';
                        this.startedAt          = Date.now();
                        this.remainingAtStartCs = this.tbDurationCs;
                        this.save();
                        this._syncStore();
                        this._tick();
                    },

                    enterSdPrompt() {
                        this.sdNeeded = true;
                        this.save();
                        this._syncStore();
                    },

                    enterOvertimeTied() {
                        if (this.phase === 'tb_running' || this.phase === 'tb_paused' || this.phase === 'tb_expired') {
                            // Win buttons already visible (tb_expired keeps sdActive true).
                            // If more OT rounds remain, also show the "Start OT N" prompt.
                            if (this.overtimeRound < this.overtimeRounds) {
                                this.sdNeeded = true;
                            }
                        } else {
                            // OT not yet started — show prompt to begin first OT round.
                            this.sdNeeded = true;
                        }
                        this.save();
                        this._syncStore();
                    },

                    _tick() {
                        clearInterval(this.interval);
                        this.interval = setInterval(() => {
                            const elapsedCs = Math.floor((Date.now() - this.startedAt) / 10);
                            const rem = Math.max(0, this.remainingAtStartCs - elapsedCs);
                            this.remainingCs = rem;
                            if (rem === 0) {
                                clearInterval(this.interval);
                                const wasRunning = this.phase === 'running';
                                this.phase = wasRunning ? 'expired' : 'tb_expired';
                                this._syncStore();
                                this.save();
                                this._bell(3);
                                if (wasRunning) {
                                    this.$wire.call('onTimerExpired', this.matchId);
                                } else if (this.tbMode === 'overtime') {
                                    this.$wire.call('onOvertimeExpired', this.matchId);
                                }
                                return;
                            }
                            if (Date.now() - this._lastSave >= 250) this.save();
                        }, 20);
                    },

                    _syncStore() {
                        const sdMode   = this.tbMode !== 'overtime';
                        const running  = this.phase === 'running' || this.phase === 'tb_running';
                        // OT tb_running/tb_paused don't block save (scoring continues normally)
                        const active   = this.phase === 'running' || this.phase === 'paused'
                            || (sdMode && (this.phase === 'tb_running' || this.phase === 'tb_paused'));
                        // Win buttons appear as soon as tiebreak is triggered in either mode.
                        const sdActive = this.sdNeeded || this.phase === 'tb_running' || this.phase === 'tb_paused' || this.phase === 'tb_expired' || this.overtimeTied;
                        // SD: lock score inputs during tiebreak period
                        const sdLocked = sdMode && (this.phase === 'tb_running' || this.phase === 'tb_paused');
                        Alpine.store('roundTimer').running  = running;
                        Alpine.store('roundTimer').active   = active;
                        Alpine.store('roundTimer').sdActive = sdActive;
                        Alpine.store('roundTimer').sdLocked = sdLocked;
                        Alpine.store('roundTimer').matchId  = (active || sdActive) ? this.matchId : null;
                    },

                    _getAudioCtx() {
                        if (!this._audioCtx) {
                            this._audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                        }
                        if (this._audioCtx.state === 'suspended') this._audioCtx.resume();
                        return this._audioCtx;
                    },

                    _bell(times) {
                        try {
                            const ctx = this._getAudioCtx();
                            for (let i = 0; i < times; i++) {
                                const t = ctx.currentTime + i * 0.42;
                                // Four harmonics for a metallic bell tone
                                [830, 1245, 1994, 2490].forEach((freq, h) => {
                                    const osc  = ctx.createOscillator();
                                    const gain = ctx.createGain();
                                    osc.connect(gain);
                                    gain.connect(ctx.destination);
                                    osc.type = 'sine';
                                    osc.frequency.value = freq;
                                    const vol = 0.55 / (h + 1);
                                    gain.gain.setValueAtTime(0, t);
                                    gain.gain.linearRampToValueAtTime(vol, t + 0.008);
                                    gain.gain.exponentialRampToValueAtTime(0.001, t + 2.2);
                                    osc.start(t);
                                    osc.stop(t + 2.2);
                                });
                            }
                        } catch(e) {}
                    },
                    }));

                    window.addEventListener('scoring-cleared', () => {
                        Object.keys(localStorage)
                            .filter(k => k.startsWith('timer_match_'))
                            .forEach(k => localStorage.removeItem(k));
                        const s = Alpine.store('roundTimer');
                        if (s) { s.running = false; s.active = false; s.sdActive = false; s.sdLocked = false; s.matchId = null; }
                    });
                };

                if (window.Alpine && Alpine.data) {
                    define();
                } else {
                    document.addEventListener('alpine:init', define);
                }
            })();
        </script>

        {{-- Division list --}}
        <style>
            @keyframes scoring-row-pulse {
                0%   { box-shadow: 0 0 0 0 rgba(99,102,241,.6); }
                35%  { box-shadow: 0 0 0 10px rgba(99,102,241,.2); }
                100% { box-shadow: 0 0 0 16px rgba(99,102,241,0); }
            }
            .scoring-row-pulse {
                animation: scoring-row-pulse .8s ease-out forwards;
            }
            @keyframes event-header-pulse {
                0%, 100% { filter: drop-shadow(0 0 3px var(--primary-glow, #818cf8)); }
                50%       { filter: drop-shadow(0 0 9px var(--primary-glow, #818cf8)) drop-shadow(0 0 2px var(--primary-glow, #818cf8)); }
            }
            .event-header-pulse-active {
                animation: event-header-pulse 2.5s ease-in-out infinite;
            }
            @keyframes division-row-enter {
                from { opacity: 0; transform: translateY(-5px); }
                to   { opacity: 1; transform: translateY(0); }
            }
            .division-enter {
                animation: division-row-enter 0.18s ease-out both;
            }
            input[type=number]::-webkit-outer-spin-button,
            input[type=number]::-webkit-inner-spin-button {
                -webkit-appearance: none;
                margin: 0;
            }
            input[type=number] {
                -moz-appearance: textfield;
            }
            @keyframes timer-expire-flash {
                0%, 100% { opacity: 1; }
                50%       { opacity: 0.35; }
            }
            .timer-expire-flash { animation: timer-expire-flash 0.7s ease-in-out infinite; }
            @keyframes winner-halo {
                0%   { box-shadow: 0 0 0 0 rgba(34,197,94,.70); }
                60%  { box-shadow: 0 0 0 8px rgba(34,197,94,.18); }
                100% { box-shadow: 0 0 0 14px rgba(34,197,94,0); }
            }
            .winner-halo { animation: winner-halo 0.55s ease-out 3; }
            button { touch-action: manipulation; }
        </style>
        <div class="space-y-1 mb-4"
            x-on:scroll-to-division.window="
                let el = document.getElementById('division-row-' + $event.detail.divisionId);
                if (!el) return;

                // Find the real scrollable container (Filament wraps content in an overflow-y:auto element)
                function getScrollContainer(node) {
                    let p = node.parentElement;
                    while (p && p !== document.body) {
                        const ov = getComputedStyle(p).overflowY;
                        if ((ov === 'auto' || ov === 'scroll') && p.scrollHeight > p.clientHeight) return p;
                        p = p.parentElement;
                    }
                    return document.documentElement;
                }

                const container = getScrollContainer(el);
                const isRoot    = container === document.documentElement;
                const contTop   = isRoot ? 0 : container.getBoundingClientRect().top;
                const elTop     = el.getBoundingClientRect().top;
                const scrollNow = isRoot ? window.scrollY : container.scrollTop;
                const target    = Math.max(0, scrollNow + elTop - contTop - 80);
                const distance  = target - scrollNow;

                if (Math.abs(distance) > 4) {
                    const duration = Math.min(400, Math.max(180, Math.abs(distance) * 0.4));
                    const t0 = performance.now();
                    (function step(now) {
                        const p    = Math.min((now - t0) / duration, 1);
                        const ease = 1 - Math.pow(1 - p, 3);   // ease-out cubic
                        const y    = scrollNow + distance * ease;
                        isRoot ? window.scrollTo(0, y) : (container.scrollTop = y);
                        if (p < 1) requestAnimationFrame(step);
                    })(t0);
                }

                // Pulse ring to confirm which row was selected
                el.classList.remove('scoring-row-pulse');
                void el.offsetWidth;
                el.classList.add('scoring-row-pulse');
                el.addEventListener('animationend', () => el.classList.remove('scoring-row-pulse'), { once: true });
            "
        >
            @foreach ($divisionList as $item)
                @php
                    $div      = $item->division;
                    $selected = $this->division_id === $div->id && $this->panelOpen;
                    $inProgress = $item->scoring_started && $div->status !== 'complete';
                    $rowClass = $div->status === 'complete'
                        ? 'bg-green-50 border-green-300 dark:bg-green-900/20 dark:border-green-700'
                        : ($inProgress
                            ? 'bg-amber-50 border-amber-300 dark:bg-amber-900/20 dark:border-amber-700'
                            : 'bg-white border-gray-200 shadow-sm dark:bg-slate-900 dark:border-slate-700');
                    $textClass = $div->status === 'complete'
                        ? 'text-green-800 dark:text-green-300'
                        : ($inProgress
                            ? 'text-amber-800 dark:text-amber-300'
                            : 'text-gray-900 dark:text-white');
                @endphp
                <div @class(['ring-2 ring-primary-500 dark:ring-primary-400 rounded-xl' => $selected])>
                <div
                    id="division-row-{{ $div->id }}"
                    wire:key="division-{{ $div->id }}"
                    @click="$store.roundTimer.running ? $dispatch('timer-nav-warn', { divisionId: {{ $div->id }} }) : $wire.selectDivision({{ $div->id }})"
                    class="{{ $selected ? '' : 'division-enter' }} flex items-center justify-between gap-3 rounded-lg border px-4 py-3 transition-all cursor-pointer
                        {{ $rowClass }}
                        {{ $selected
                            ? 'event-header-pulse-active'
                            : 'hover:border-primary-300 dark:hover:border-primary-600' }}"
                    style="animation-delay: {{ min($loop->index * 40, 320) }}ms"
                >
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="font-mono text-sm font-bold shrink-0 {{ $textClass }}">{{ $div->code }}</span>
                        <div class="min-w-0">
                            <p class="text-sm font-medium {{ $textClass }} truncate">
                                {{ $div->competitionEvent->name }}
                                @if ($div->location_label)
                                    <span class="font-normal text-gray-500 dark:text-gray-400">— {{ $div->location_label }}</span>
                                @endif
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $div->label }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 shrink-0">
                        <span class="text-xs text-gray-500 flex flex-col sm:flex-row sm:gap-1 items-end sm:items-center">
                            <span>{{ $item->checked_in_count }} checked in</span>
                            @if ($item->scoring_started || $item->competitors_count !== $item->checked_in_count || $div->status === 'complete')
                                <span><span class="hidden sm:inline">&middot; </span>{{ $item->competitors_count }} competing</span>
                            @endif
                        </span>

                        @if ($div->status === 'complete')
                            <x-heroicon-m-check-circle class="w-5 h-5 text-success-500" />
                        @elseif ($inProgress)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">In progress</span>
                        @else
                            <x-heroicon-m-chevron-right class="w-4 h-4 text-gray-400 {{ $selected ? 'rotate-90' : '' }}" />
                        @endif
                    </div>
                </div>

                {{-- Inline scoring panel --}}
                @if ($selected)
                    @php
                        $rows               = $this->getCompetitorRows();
                        $method             = $this->getScoringMethod();
                        $judges             = $this->getJudgeCount();
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
                    @endphp
                    <div class="mb-2 rounded-lg border border-primary-200 dark:border-primary-700 bg-white dark:bg-slate-800 p-4">

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
                            <div class="flex items-center gap-2">
                                @if (! $this->rollcallMode)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 dark:bg-slate-700 px-2 py-0.5 text-xs text-gray-500 dark:text-gray-400">
                                        <x-heroicon-m-trophy class="w-3 h-3 shrink-0" />
                                        {{ $this->getAwardedPlacesLabel() }}
                                    </span>
                                @endif
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
                        @endif

                        @if ($this->rollcallMode)
                            {{-- Step 1: Rollcall --}}
                            @php
                                $rollcall = $this->getRollcallRows();
                            @endphp
                            @if ($rollcall->isEmpty())
                                <p class="text-center text-sm text-gray-400 py-4">No checked-in competitors in this division.</p>
                            @else
                                @php
                                    $activeEeIds = $rollcall->where('absent', false)->pluck('ee_id');
                                    $allMarked   = $activeEeIds->isNotEmpty() && $activeEeIds->every(fn ($id) => in_array($id, $this->rollcallPresent));
                                @endphp
                                <div class="flex items-center justify-between mb-3">
                                    <p class="text-xs text-gray-400">Tap each competitor to confirm they are present.</p>
                                    <x-filament::button
                                        size="xs"
                                        color="gray"
                                        wire:click="{{ $allMarked ? 'unmarkAllPresent' : 'markAllPresent' }}">
                                        {{ $allMarked ? 'Unmark all present' : 'Mark all present' }}
                                    </x-filament::button>
                                </div>
                                <ul class="divide-y divide-gray-100 dark:divide-slate-800">
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
                                                @if ($rc->info)
                                                    <span class="font-normal text-gray-400 dark:text-gray-500">({{ $rc->info }})</span>
                                                @endif
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>

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
                                                <x-filament::button size="sm" color="gray" wire:click="closePairingWizard">
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
                                            <p class="text-sm text-gray-500 mb-1">{{ $competitorCount }} competitor(s) competing.</p>
                                            <p class="text-xs text-gray-400 mb-3">
                                                {{ match($format) { 'double_elimination' => 'Double elimination bracket', 'round_robin' => 'Round robin', 'repechage' => 'Single elimination with repechage', 'se_3rd_place' => 'Single elimination with 3rd place playoff', default => 'Single elimination bracket' } }}
                                            </p>
                                            <x-filament::button color="primary" wire:click="generateBracket">
                                                Generate bracket
                                            </x-filament::button>
                                        </div>
                                    @endif
                                @else
                                    {{-- Bracket header --}}
                                    <div class="flex items-center justify-between mb-3">
                                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">
                                            {{ match($format) { 'double_elimination' => 'Double elimination', 'round_robin' => 'Round robin', 'repechage' => 'Repechage', 'se_3rd_place' => 'SE + 3rd place playoff', default => 'Single elimination' } }} bracket
                                        </p>
                                        @if (! $this->isScoringComplete())
                                        <x-filament::button size="xs" color="gray"
                                            x-on:click="$dispatch('open-modal', { id: 'confirm-reset-bracket' })">
                                            Reset bracket
                                        </x-filament::button>
                                        @endif
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
                                                            $homeResult = $rows->first(fn($r) => $r->ee->id === $match->home_id)?->result;
                                                            $awayResult = $rows->first(fn($r) => $r->ee->id === $match->away_id)?->result;
                                                        @endphp
                                                        <div class="rounded-lg border px-3 py-2 text-sm
                                                            {{ ! $pending ? 'border-success-200 dark:border-success-800 bg-success-50 dark:bg-success-900/20' : 'border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900' }}">

                                                            {{-- Names row --}}
                                                            <div class="flex items-start gap-2">
                                                                <div class="flex-1 min-w-0">
                                                                    <div class="font-medium truncate {{ $homeWon ? 'text-success-700 dark:text-success-400' : ($awayWon ? 'text-gray-400' : 'text-gray-900 dark:text-white') }}">
                                                                        @if ($homeWon)🏆 @endif<span class="{{ $awayWon ? 'line-through' : '' }}">{{ $match->home_name }}</span>@if ($homeResult?->disqualified) <span class="text-xs font-normal text-danger-600">[{{ $this->getDqLabel($homeResult->id) }}]</span>@endif
                                                                    </div>
                                                                    @if ($match->home_info)
                                                                        <div class="text-xs text-gray-400 dark:text-gray-500 truncate">{{ $match->home_info }}</div>
                                                                    @endif
                                                                    @if ($pending && ! $isReadOnly && ! empty($enabledPenalties) && $homeResult)
                                                                        @php $bWC=$this->getWarnCount($homeResult->id,$match->id); $bLog=$this->getPenaltyLog($homeResult->id,$match->id); $bCU=$this->hasUndoablePenalty($homeResult->id,$match->id); @endphp
                                                                        <div class="flex flex-wrap gap-1 items-center mt-1">
                                                                            @foreach ($enabledPenalties as $pType)
                                                                                @if ($pType === 'forfeit' || $match->away_id !== null)
                                                                                    <button type="button" wire:click="openPenaltyModal({{ $homeResult->id }}, '{{ $pType }}', {{ $match->id }})"
                                                                                        class="px-1.5 py-0.5 rounded text-xs font-medium border {{ in_array($pType,['dq','forfeit']) ? 'border-danger-300 dark:border-danger-700 bg-danger-50 dark:bg-danger-900/20 text-danger-700 dark:text-danger-400' : 'border-warning-300 dark:border-warning-700 bg-warning-50 dark:bg-warning-900/20 text-warning-700 dark:text-warning-400' }} active:scale-95 transition-transform">
                                                                                        @if($pType==='warn'&&$bWC>0)Warn {{$bWC}}@else{{$this->getPenaltyLabel($pType)}}@endif
                                                                                    </button>
                                                                                @endif
                                                                            @endforeach
                                                                            @if($bCU)<button type="button" wire:click="undoPenalty({{ $homeResult->id }}, {{ $match->id }})" class="px-1.5 py-0.5 rounded text-xs border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 active:scale-95 transition-transform"><x-heroicon-m-arrow-uturn-left class="inline w-3 h-3" /></button>@endif
                                                                        </div>
                                                                        @if(!empty($bLog))<ul class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 space-y-0.5">@foreach($bLog as $e)<li>{{$e['label']}}</li>@endforeach</ul>@endif
                                                                    @endif
                                                                </div>
                                                                <span class="text-xs text-gray-400 shrink-0 mt-0.5">vs</span>
                                                                <div class="flex-1 min-w-0 text-right">
                                                                    <div class="font-medium truncate {{ $awayWon ? 'text-success-700 dark:text-success-400' : ($homeWon ? 'text-gray-400' : 'text-gray-900 dark:text-white') }}">
                                                                        @if ($awayResult?->disqualified) <span class="text-xs font-normal text-danger-600">[{{ $this->getDqLabel($awayResult->id) }}]</span> @endif<span class="{{ $homeWon ? 'line-through' : '' }}">{{ $match->away_name }}</span>@if ($awayWon) 🏆@endif
                                                                    </div>
                                                                    @if ($match->away_info)
                                                                        <div class="text-xs text-gray-400 dark:text-gray-500 truncate">{{ $match->away_info }}</div>
                                                                    @endif
                                                                    @if ($pending && ! $isReadOnly && ! empty($enabledPenalties) && $awayResult)
                                                                        @php $bWC=$this->getWarnCount($awayResult->id,$match->id); $bLog=$this->getPenaltyLog($awayResult->id,$match->id); $bCU=$this->hasUndoablePenalty($awayResult->id,$match->id); @endphp
                                                                        <div class="flex flex-wrap gap-1 items-center justify-end mt-1">
                                                                            @if($bCU)<button type="button" wire:click="undoPenalty({{ $awayResult->id }}, {{ $match->id }})" class="px-1.5 py-0.5 rounded text-xs border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 active:scale-95 transition-transform"><x-heroicon-m-arrow-uturn-left class="inline w-3 h-3" /></button>@endif
                                                                            @foreach ($enabledPenalties as $pType)
                                                                                @if ($pType === 'forfeit' || $match->away_id !== null)
                                                                                    <button type="button" wire:click="openPenaltyModal({{ $awayResult->id }}, '{{ $pType }}', {{ $match->id }})"
                                                                                        class="px-1.5 py-0.5 rounded text-xs font-medium border {{ in_array($pType,['dq','forfeit']) ? 'border-danger-300 dark:border-danger-700 bg-danger-50 dark:bg-danger-900/20 text-danger-700 dark:text-danger-400' : 'border-warning-300 dark:border-warning-700 bg-warning-50 dark:bg-warning-900/20 text-warning-700 dark:text-warning-400' }} active:scale-95 transition-transform">
                                                                                        @if($pType==='warn'&&$bWC>0)Warn {{$bWC}}@else{{$this->getPenaltyLabel($pType)}}@endif
                                                                                    </button>
                                                                                @endif
                                                                            @endforeach
                                                                        </div>
                                                                        @if(!empty($bLog))<ul class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 space-y-0.5 text-right">@foreach($bLog as $e)<li>{{$e['label']}}</li>@endforeach</ul>@endif
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
                                                                                      x-text="displaySeconds"></span><span class="font-mono text-xs font-medium tabular-nums text-gray-400 dark:text-gray-500 relative top-[5px]"
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
                                                                                 get sdLocked() { return $store.roundTimer.sdLocked && $store.roundTimer.matchId === {{ $match->id }}; },
                                                                                 addScore(side, amount) {
                                                                                     if (this.sdLocked) return;
                                                                                     const max = {{ $targetScore ?? 'Infinity' }};
                                                                                     const cur = side === 'home' ? this.homeHistory : this.awayHistory;
                                                                                     const total = cur.reduce((s,v) => s+v, 0);
                                                                                     if (total + amount > max) return;
                                                                                     cur.push(amount);
                                                                                     $wire.set('bracketScoreInput.{{ $match->id }}.' + side, total + amount);
                                                                                 },
                                                                                 undoScore(side) {
                                                                                     if (this.sdLocked) return;
                                                                                     const cur = side === 'home' ? this.homeHistory : this.awayHistory;
                                                                                     if (cur.length === 0) return;
                                                                                     cur.pop();
                                                                                     const total = cur.reduce((s,v) => s+v, 0);
                                                                                     $wire.set('bracketScoreInput.{{ $match->id }}.' + side, total > 0 ? total : null);
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
                                                                                    @if ($homeResult && ! $isReadOnly && ! $dqViaPenalties)
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
                                                                                            wire:click="declareBracketWinner({{ $match->id }}, 'home')">Win</x-filament::button>
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
                                                                                    @if ($awayResult && ! $isReadOnly && ! $dqViaPenalties)
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
                                                                                            wire:click="declareBracketWinner({{ $match->id }}, 'away')">Win</x-filament::button>
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
                                                                                 get sdLocked() { return $store.roundTimer.sdLocked && $store.roundTimer.matchId === {{ $match->id }}; },
                                                                                 addScore(side, amount) {
                                                                                     if (this.sdLocked) return;
                                                                                     const max = {{ $targetScore ?? 'Infinity' }};
                                                                                     const cur = side === 'home' ? this.homeHistory : this.awayHistory;
                                                                                     const total = cur.reduce((s,v) => s+v, 0);
                                                                                     if (total + amount > max) return;
                                                                                     cur.push(amount);
                                                                                     $wire.set('bracketScoreInput.{{ $match->id }}.' + side, total + amount);
                                                                                 },
                                                                                 undoScore(side) {
                                                                                     if (this.sdLocked) return;
                                                                                     const cur = side === 'home' ? this.homeHistory : this.awayHistory;
                                                                                     if (cur.length === 0) return;
                                                                                     cur.pop();
                                                                                     const total = cur.reduce((s,v) => s+v, 0);
                                                                                     $wire.set('bracketScoreInput.{{ $match->id }}.' + side, total > 0 ? total : null);
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
                                                                                    @if ($homeResult && ! $isReadOnly && ! $dqViaPenalties)
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
                                                                                        wire:click="declareBracketWinner({{ $match->id }}, 'home')">← Win</x-filament::button>
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
                                                                                    @if ($awayResult && ! $isReadOnly && ! $dqViaPenalties)
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
                                                                                        wire:click="declareBracketWinner({{ $match->id }}, 'away')">Win →</x-filament::button>
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
                                            $wbRounds     = $bracketData['winners'] ?? [];
                                            $wbFinalRound = ! empty($wbRounds) ? max(array_keys($wbRounds)) : null;
                                            $onlyTwoCompetitors = ($wbFinalRound === 1);
                                            $_capEvent    = $div->competitionEvent;
                                            $placementCap = match (true) {
                                                $competitorCount <= 2 => $_capEvent->awarded_places_2 ?? 2,
                                                $competitorCount === 3 => $_capEvent->awarded_places_3 ?? 3,
                                                default               => $_capEvent->awarded_places_4plus ?? 3,
                                            };

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
                                        <div wire:key="bracket-results-{{ $this->division_id }}"
                                             class="mt-4 rounded-lg border border-success-300 dark:border-success-700 bg-success-50 dark:bg-success-900/20 px-4 py-3">
                                            <p class="text-xs font-semibold uppercase tracking-wider text-success-700 dark:text-success-400 mb-2">Results</p>
                                            @if (isset($bracketPlacements[1]))
                                                <p class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white"><span class="text-2xl leading-none">🥇</span> {{ $bracketPlacements[1] }}</p>
                                            @endif
                                            @if (! $onlyTwoCompetitors && $placementCap >= 2 && isset($bracketPlacements[2]))
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
                                        @endphp
                                        <div wire:key="mobile-row-{{ $result->id }}"
                                             x-data="{ open: false }"
                                             class="rounded-lg border {{ $result->disqualified ? 'opacity-60' : '' }} border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900">

                                            {{-- Card header --}}
                                            <div class="px-3 py-3">
                                            <div class="flex items-center gap-2">
                                                <div class="min-w-0 flex-1">
                                                    <p class="font-medium text-sm text-gray-900 dark:text-white truncate">
                                                        {{ $row->name }}
                                                        @if ($result->disqualified)
                                                            <span class="ml-1 text-xs text-danger-600">{{ $this->getDqLabel($result->id) }}</span>
                                                        @endif
                                                    </p>
                                                    @if ($row->info)
                                                        <p class="text-xs text-gray-400 dark:text-gray-500">{{ $row->info }}</p>
                                                    @endif
                                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                                        @if (in_array($method, ['judges_total', 'judges_average']))
                                                            @if ($isSaved)
                                                                Total: <strong>{{ $liveTotal !== null ? number_format($liveTotal, 1) : '—' }}</strong>
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

                                            {{-- Penalty buttons below name, no repeat --}}
                                            @if (in_array($method, ['win_loss', 'first_to_n', 'timed_points']) && ! empty($enabledPenalties))
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
                                                        <div x-data="{}">
                                                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Judge {{ $j }}</label>
                                                            <div class="flex items-center gap-2">
                                                                <input x-ref="inp" type="number" step="0.1" min="0" max="10"
                                                                    wire:model="judgeScores.{{ $result->id }}.{{ $j }}"
                                                                    class="flex-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-900 text-gray-900 dark:text-white text-base py-2.5 px-3 {{ $isSaved ? 'opacity-50' : '' }}"
                                                                    placeholder="0.0"
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

                                                    <div class="flex gap-2 pt-1">
                                                        @if ($isSaved)
                                                            <x-filament::button color="gray" class="flex-1"
                                                                wire:click="undoJudgeScores({{ $result->id }})"
                                                                :disabled="$inTiebreakerFlow">Undo</x-filament::button>
                                                        @else
                                                            <x-filament::button color="primary" class="flex-1"
                                                                wire:click="saveJudgeScores({{ $result->id }})"
                                                                x-on:click="open = false">Save scores</x-filament::button>
                                                        @endif
                                                        <x-filament::button
                                                            color="{{ $result->disqualified ? 'gray' : 'danger' }}"
                                                            wire:click="toggleDisqualify({{ $result->id }})"
                                                            :disabled="$inTiebreakerFlow">
                                                            {{ $result->disqualified ? 'Un-DQ' : 'DQ' }}
                                                        </x-filament::button>
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
                                                        <th class="pb-2 pr-2">J{{ $j }}</th>
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
                                        <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                                            @foreach ($rows as $row)
                                                @php
                                                    $result  = $row->result;
                                                    $isSaved = in_array($result->id, $this->savedResultIds);
                                                @endphp
                                                <tr class="{{ $result->disqualified ? 'opacity-50' : '' }}">
                                                    <td class="py-2 pr-4">
                                                        <div class="font-medium text-gray-900 dark:text-white">
                                                            {{ $row->name }}
                                                            @if ($result->disqualified)
                                                                <span class="ml-1 text-xs text-danger-600">{{ $this->getDqLabel($result->id) }}</span>
                                                            @endif
                                                        </div>
                                                        @if ($row->info)
                                                            <div class="text-xs text-gray-400 dark:text-gray-500">{{ $row->info }}</div>
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
                                                        @endphp
                                                        @for ($j = 1; $j <= $judges; $j++)
                                                            <td class="py-2 pr-2">
                                                                @if ($isReadOnly)
                                                                    <span class="text-base text-gray-700 dark:text-gray-300">
                                                                        {{ number_format((float) ($this->judgeScores[$result->id][$j] ?? 0), 1) }}
                                                                    </span>
                                                                @else
                                                                    <div class="flex items-center gap-1 {{ $isSaved ? 'opacity-50' : '' }}" x-data="{}">
                                                                        <button type="button"
                                                                            x-on:click="const i=$el.nextElementSibling; const v=Math.round((parseFloat(i.value||0)-0.1)*10)/10; i.value=Math.max(0,v).toFixed(1); i.dispatchEvent(new Event('input',{bubbles:true}));"
                                                                            class="w-7 h-7 flex items-center justify-center rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-slate-800 text-gray-700 dark:text-gray-200 font-medium active:scale-95 transition-transform"
                                                                            @if ($isSaved) disabled @endif>−</button>
                                                                        <input type="number" step="0.1" min="0" max="10"
                                                                            wire:model="judgeScores.{{ $result->id }}.{{ $j }}"
                                                                            class="w-[3.25rem] text-center rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-900 text-gray-900 dark:text-white text-base py-0.5 px-1"
                                                                            placeholder="0.0"
                                                                            @if ($isSaved) disabled @endif />
                                                                        <button type="button"
                                                                            x-on:click="const i=$el.previousElementSibling; const v=Math.round((parseFloat(i.value||0)+0.1)*10)/10; i.value=Math.min(10,v).toFixed(1); i.dispatchEvent(new Event('input',{bubbles:true}));"
                                                                            class="w-7 h-7 flex items-center justify-center rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-slate-800 text-gray-700 dark:text-gray-200 font-medium active:scale-95 transition-transform"
                                                                            @if ($isSaved) disabled @endif>+</button>
                                                                    </div>
                                                                @endif
                                                            </td>
                                                        @endfor
                                                        <td class="py-2 pr-4">
                                                            <span class="font-semibold">
                                                                {{ $isSaved && $liveTotal !== null ? number_format($liveTotal, 1) : '—' }}
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
                                                            <div class="flex flex-wrap gap-1">
                                                                @if (in_array($method, ['judges_total', 'judges_average']))
                                                                    @if ($isSaved)
                                                                        <x-filament::button size="xs" color="gray"
                                                                            wire:click="undoJudgeScores({{ $result->id }})"
                                                                            :disabled="$inTiebreakerFlow">
                                                                            Undo
                                                                        </x-filament::button>
                                                                    @else
                                                                        <x-filament::button size="xs" color="primary"
                                                                            wire:click="saveJudgeScores({{ $result->id }})">
                                                                            Save
                                                                        </x-filament::button>
                                                                    @endif
                                                                @endif
                                                                @if (! $dqViaPenalties || in_array($method, ['judges_total', 'judges_average']))
                                                                    <x-filament::button size="xs"
                                                                        color="{{ $result->disqualified ? 'gray' : 'danger' }}"
                                                                        wire:click="toggleDisqualify({{ $result->id }})"
                                                                        :disabled="$inTiebreakerFlow ?? false">
                                                                        {{ $result->disqualified ? 'Un-DQ' : 'DQ' }}
                                                                    </x-filament::button>
                                                                @endif
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
                                                         x-data="{ open: false }"
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
                                                                @for ($j = 1; $j <= $judges; $j++)
                                                                    <div x-data="{}">
                                                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Judge {{ $j }}</label>
                                                                        <div class="flex items-center gap-2">
                                                                            <input x-ref="inp" type="number" step="0.1" min="0" max="10"
                                                                                wire:model="tiebreakerJudgeInputs.{{ $result->id }}.{{ $j }}"
                                                                                class="flex-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-900 text-gray-900 dark:text-white text-base py-2.5 px-3"
                                                                                placeholder="0.0" />
                                                                            <div class="flex gap-1 shrink-0">
                                                                                <button type="button"
                                                                                    x-on:click="let v = Math.round((parseFloat($refs.inp.value || 0) - 0.1) * 10) / 10; $refs.inp.value = Math.max(0, v).toFixed(1); $refs.inp.dispatchEvent(new Event('input', {bubbles: true}));"
                                                                                    class="w-11 h-11 flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-slate-800 text-gray-700 dark:text-gray-200 text-xl font-medium active:scale-95 transition-transform">−</button>
                                                                                <button type="button"
                                                                                    x-on:click="let v = Math.round((parseFloat($refs.inp.value || 0) + 0.1) * 10) / 10; $refs.inp.value = Math.min(10, v).toFixed(1); $refs.inp.dispatchEvent(new Event('input', {bubbles: true}));"
                                                                                    class="w-11 h-11 flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-slate-800 text-gray-700 dark:text-gray-200 text-xl font-medium active:scale-95 transition-transform">+</button>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                @endfor

                                                                <div class="pt-1">
                                                                    <x-filament::button color="warning" class="w-full"
                                                                        wire:click="saveTiebreakerScores({{ $result->id }})"
                                                                        x-on:click="open = false">Save scores</x-filament::button>
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
                                                                $tbSaved   = $result->tiebreaker_score !== null || $result->placement_overridden;
                                                                $tbDisplay = $result->tiebreaker_score !== null
                                                                    ? number_format((float) $result->tiebreaker_score, 1)
                                                                    : '—';
                                                            @endphp
                                                            <tr>
                                                                <td class="py-2 pr-4">
                                                                    <div class="font-medium text-gray-900 dark:text-white">{{ $row->name }}</div>
                                                                    @if ($row->info)
                                                                        <div class="text-xs text-gray-400 dark:text-gray-500">{{ $row->info }}</div>
                                                                    @endif
                                                                </td>
                                                                @for ($j = 1; $j <= $judges; $j++)
                                                                    <td class="py-2 pr-2">
                                                                        @if ($isReadOnly || $tbSaved)
                                                                            <span class="text-base text-gray-700 dark:text-gray-300 {{ $tbSaved ? 'opacity-50' : '' }}">
                                                                                {{ number_format((float) ($this->tiebreakerJudgeInputs[$result->id][$j] ?? 0), 1) }}
                                                                            </span>
                                                                        @else
                                                                            <div class="flex items-center gap-1" x-data="{}">
                                                                                <button type="button"
                                                                                    x-on:click="const i=$el.nextElementSibling; const v=Math.round((parseFloat(i.value||0)-0.1)*10)/10; i.value=Math.max(0,v).toFixed(1); i.dispatchEvent(new Event('input',{bubbles:true}));"
                                                                                    class="w-7 h-7 flex items-center justify-center rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-slate-800 text-gray-700 dark:text-gray-200 font-medium active:scale-95 transition-transform">−</button>
                                                                                <input type="number" step="0.1" min="0" max="10"
                                                                                    wire:model="tiebreakerJudgeInputs.{{ $result->id }}.{{ $j }}"
                                                                                    class="w-[3.25rem] text-center rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-900 text-gray-900 dark:text-white text-base py-0.5 px-1"
                                                                                    placeholder="0.0" />
                                                                                <button type="button"
                                                                                    x-on:click="const i=$el.previousElementSibling; const v=Math.round((parseFloat(i.value||0)+0.1)*10)/10; i.value=Math.min(10,v).toFixed(1); i.dispatchEvent(new Event('input',{bubbles:true}));"
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
                                                <table class="w-full text-base">
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
                                                                <td class="py-2 pr-4">
                                                                    <div class="font-medium text-gray-900 dark:text-white">{{ $tbRow->name }}</div>
                                                                    @if ($tbRow->info)
                                                                        <div class="text-xs text-gray-400 dark:text-gray-500">{{ $tbRow->info }}</div>
                                                                    @endif
                                                                </td>
                                                                @for ($j = 1; $j <= $judges; $j++)
                                                                    <td class="py-2 pr-2">
                                                                        <span class="text-base text-gray-700 dark:text-gray-300">
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
                                            $startPos        = $this->getCompetitorRows()
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
                                    @if ($this->rollcallMode)
                                        <x-filament::button color="primary" size="sm"
                                            wire:click="toggleRollcall"
                                            icon="heroicon-m-arrow-right" icon-position="after">
                                            Begin Scoring
                                        </x-filament::button>
                                    @endif
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
                                    <x-filament::button color="gray" size="sm" wire:click="deselectDivision">
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
                </div>{{-- outer ring wrapper --}}
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

    {{-- Penalty reason selection modal --}}
    <x-filament::modal id="penalty-reason-modal" width="sm" :close-by-clicking-away="false">
        <x-slot name="heading">
            Select reason
            @if ($penaltyModalType)
                <span class="ml-1 text-sm font-normal text-gray-500 dark:text-gray-400">— {{ ucfirst($penaltyModalType) }}</span>
            @endif
        </x-slot>
        <div class="space-y-2">
            @foreach ($penaltyModalReasons as $reason)
                <button type="button"
                    wire:click="$set('penaltyModalSelectedReason', @js($reason))"
                    class="w-full text-left px-3 py-2 rounded-lg border text-sm transition-colors
                        {{ $penaltyModalSelectedReason === $reason
                            ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 font-medium'
                            : 'border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-gray-400 dark:hover:border-gray-500' }}">
                    {{ $reason }}
                </button>
            @endforeach
            @if (empty($penaltyModalReasons))
                <p class="text-sm text-gray-400 dark:text-gray-500">No reasons configured for this event.</p>
            @endif
        </div>
        <x-slot name="footerActions">
            <x-filament::button
                wire:click="confirmPenalty"
                x-on:click="$dispatch('close-modal', { id: 'penalty-reason-modal' })"
                :disabled="empty($penaltyModalReasons)">
                Apply
            </x-filament::button>
            <x-filament::button color="gray"
                x-on:click="$dispatch('close-modal', { id: 'penalty-reason-modal' })">
                Cancel
            </x-filament::button>
        </x-slot>
    </x-filament::modal>
</x-filament-panels::page>
