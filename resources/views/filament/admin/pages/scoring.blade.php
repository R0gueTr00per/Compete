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
                        this._bell(2);
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
            .division-selected-glow {
                box-shadow: 0 0 22px 4px rgba(99,102,241,.30);
                border-radius: .75rem;
            }
            .scoring-panel-glow {
                box-shadow: 0 0 0 1px rgba(99,102,241,.35), 0 0 18px 4px rgba(99,102,241,.22);
            }
            @keyframes event-header-pulse {
                0%, 100% { filter: drop-shadow(0 0 3px var(--primary-glow, #818cf8)); }
                50%       { filter: drop-shadow(0 0 9px var(--primary-glow, #818cf8)) drop-shadow(0 0 2px var(--primary-glow, #818cf8)); }
            }
            .event-header-pulse-active {
                animation: event-header-pulse 2.5s ease-in-out infinite;
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
            @if ($this->pendingLockDivisionId)
                @php $pendingItem = $divisionList->first(fn ($i) => $i->division->id === $this->pendingLockDivisionId); @endphp
                @if ($pendingItem)
                    <div class="mb-3 rounded-lg border border-amber-300 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700 px-4 py-3 text-sm">
                        <p class="font-medium text-amber-800 dark:text-amber-200">
                            <x-heroicon-m-lock-closed class="inline w-4 h-4 mr-1 -mt-0.5" />
                            {{ $pendingItem->locked_by_other }} is scoring {{ $pendingItem->division->code }}.
                        </p>
                        <div class="mt-2 flex gap-2">
                            <x-filament::button size="xs" color="warning" wire:click="proceedOpenLocked">Open anyway</x-filament::button>
                            <x-filament::button size="xs" color="gray" wire:click="cancelOpenLocked">Cancel</x-filament::button>
                        </div>
                    </div>
                @endif
            @endif

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
                <div wire:key="row-{{ $div->id }}">
                <div
                    id="division-row-{{ $div->id }}"
                    wire:key="division-{{ $div->id }}"
                    @click="$store.roundTimer.running ? $dispatch('timer-nav-warn', { divisionId: {{ $div->id }} }) : $wire.selectDivision({{ $div->id }})"
                    class="flex items-center justify-between gap-3 rounded-lg border px-4 py-3 cursor-pointer
                        {{ $rowClass }}
                        {{ $selected
                            ? 'event-header-pulse-active'
                            : 'hover:border-primary-300 dark:hover:border-primary-600' }}"
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
                        @elseif ($item->locked_by_other)
                            <x-heroicon-m-lock-closed class="w-4 h-4 text-amber-500 dark:text-amber-400" title="{{ $item->locked_by_other }}" />
                        @elseif ($inProgress)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">In progress</span>
                        @else
                            <x-heroicon-m-chevron-right class="w-4 h-4 text-gray-400 {{ $selected ? 'rotate-90' : '' }}" />
                        @endif
                    </div>
                </div>

                {{-- Inline scoring panel --}}
                @if ($selected)
                    <livewire:org-admin.scoring-panel
                        :division-id="$this->division_id"
                        :competition-id="$this->competition_id"
                        :key="'panel-' . $this->division_id"
                    />
                @endif
                </div>{{-- outer ring wrapper --}}
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
