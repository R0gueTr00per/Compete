<x-filament-panels::page>
    {{-- Navigate-away loading overlay --}}
    <div wire:loading.flex wire:target="leavePage"
         class="fixed inset-0 z-50 items-center justify-center bg-white/80 dark:bg-slate-900/80">
        <svg class="animate-spin h-8 w-8 text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
        </svg>
    </div>
    {{-- Timer-aware leave confirmation --}}
    <div x-data="{
            confirmOpen: false,
            tryLeave() {
                if (window.Alpine && Alpine.store('roundTimer') && Alpine.store('roundTimer').running) {
                    this.confirmOpen = true;
                } else {
                    $wire.leavePage();
                }
            },
            doLeave() {
                const mid = Alpine.store('roundTimer').matchId;
                if (mid) window.dispatchEvent(new CustomEvent('timer-reset', { detail: { matchId: mid } }));
                this.confirmOpen = false;
                $wire.leavePage();
            }
         }"
         x-on:pairing-cancelled.window="$wire.leavePage()">

        {{-- Back to list button --}}
        <div class="flex items-center gap-3 mb-4">
            <x-filament::button
                size="sm"
                color="gray"
                icon="heroicon-m-arrow-left"
                x-on:click="tryLeave()">
                Back to scoring list
            </x-filament::button>
        </div>

        {{-- Timer running warning --}}
        <template x-if="confirmOpen">
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
                <div class="rounded-xl border border-warning-300 bg-white dark:bg-slate-800 dark:border-warning-700 p-6 max-w-sm w-full shadow-xl">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white mb-1">Timer is running</p>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-5">A round timer is active. Leaving will reset the current time.</p>
                    <div class="flex gap-3 justify-end">
                        <x-filament::button color="gray" size="sm" @click="confirmOpen = false">Stay here</x-filament::button>
                        <x-filament::button color="warning" size="sm" @click="doLeave()">Leave anyway</x-filament::button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- Alpine timer component definitions --}}
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
                        if (this.overtimeRound < this.overtimeRounds) {
                            this.sdNeeded = true;
                        }
                    } else {
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
                    const active   = this.phase === 'running' || this.phase === 'paused'
                        || (sdMode && (this.phase === 'tb_running' || this.phase === 'tb_paused'));
                    const sdActive = this.sdNeeded || this.phase === 'tb_running' || this.phase === 'tb_paused' || this.phase === 'tb_expired' || this.overtimeTied;
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

    @if ($this->division_id)
        <livewire:org-admin.scoring-panel
            :division-id="$this->division_id"
            :competition-id="$this->competition_id"
            :key="'bracket-panel-' . $this->division_id"
        />
    @endif
</x-filament-panels::page>
