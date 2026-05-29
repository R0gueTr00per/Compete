<x-filament-panels::page>
    {{-- Competition + Search bar --}}
    <div class="mb-6 rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 dark:border-primary-800 dark:bg-primary-950/30">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-primary-700 dark:text-primary-400">Competition</p>
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <x-filament::input.wrapper class="dark:bg-slate-900">
                    <select
                        wire:model.live="competition_id"
                        class="w-full block border-0 bg-transparent py-1.5 text-gray-900 dark:text-white placeholder:text-gray-400 focus:ring-0 sm:text-sm sm:leading-6 dark:bg-slate-900"
                    >
                        <option value="">— Select competition —</option>
                        @foreach ($this->getCompetitions() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </x-filament::input.wrapper>
            </div>

            <div class="flex-1">
                <div class="flex items-center rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 focus-within:ring-1 focus-within:ring-primary-500">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search competitor name…"
                        inputmode="search"
                        enterkeyhint="search"
                        x-on:keydown.enter="$el.blur()"
                        class="flex-1 bg-transparent py-1.5 pl-3 pr-1 text-base text-gray-900 dark:text-white border-0 focus:outline-none focus:ring-0 min-w-0"
                    />
                    @if ($this->search)
                        <button
                            wire:click="$set('search', '')"
                            class="pr-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                            aria-label="Clear search"
                        >
                            <x-heroicon-m-x-mark class="h-4 w-4" />
                        </button>
                    @endif
                </div>
            </div>
        </div>

        {{-- QR / Code quick-lookup --}}
        <div class="mt-3 pt-3 border-t border-primary-100 dark:border-primary-900">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-primary-700 dark:text-primary-400">Quick lookup</p>
            <div
                x-data="qrScanner()"
                x-on:qr-scanned.window="$wire.set('code', $event.detail.code)"
                class="flex flex-col gap-2"
            >
                {{-- Code input + Scan button --}}
                <div class="flex items-center gap-2">
                    <div class="flex items-center rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 focus-within:ring-1 focus-within:ring-primary-500 flex-1">
                        <input
                            type="text"
                            wire:model.live.debounce.200ms="code"
                            placeholder="Enter check-in code…"
                            inputmode="text"
                            autocomplete="off"
                            class="flex-1 bg-transparent py-1.5 pl-3 pr-1 text-base font-mono uppercase text-gray-900 dark:text-white border-0 focus:outline-none focus:ring-0 min-w-0"
                        />
                        @if ($this->code)
                            <button
                                wire:click="clearCode"
                                class="pr-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                aria-label="Clear code"
                            >
                                <x-heroicon-m-x-mark class="h-4 w-4" />
                            </button>
                        @endif
                    </div>
                    <button
                        type="button"
                        x-on:click="scanning ? stopScan() : startScan()"
                        class="flex items-center gap-1.5 rounded-lg border border-primary-400 bg-primary-50 dark:bg-primary-950/50 dark:border-primary-700 px-3 py-1.5 text-xs font-semibold text-primary-700 dark:text-primary-300 hover:bg-primary-100 dark:hover:bg-primary-900/50 transition-colors shrink-0"
                    >
                        <x-heroicon-m-qr-code class="h-4 w-4" />
                        <span x-text="scanning ? 'Stop' : 'Scan QR'"></span>
                    </button>
                </div>

                {{-- Camera overlay --}}
                <div x-show="scanning" x-cloak class="relative rounded-xl overflow-hidden bg-black aspect-video max-h-56">
                    <video x-ref="video" playsinline muted class="w-full h-full object-cover"></video>
                    <canvas x-ref="canvas" class="hidden"></canvas>
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                        <div class="w-40 h-40 border-2 border-white/60 rounded-xl"></div>
                    </div>
                </div>
                <p x-show="scanning" x-cloak class="text-center text-xs text-gray-400 dark:text-gray-500">Point at competitor's QR code</p>

                {{-- Camera error --}}
                <p x-show="error" x-text="error" class="text-xs text-danger-600 dark:text-danger-400"></p>
            </div>
        </div>
    </div>

    @if (! $this->competition_id)
        <p class="text-center text-gray-400 py-12">Select a competition to begin check-in.</p>
    @elseif (! in_array(($competition = \App\Models\Competition::find($this->competition_id))?->status, ['enrolments_closed', 'check_in', 'running']))
        <p class="text-center text-gray-400 py-12">Check-in is not available yet — enrolments are still open or competition has not closed.</p>
    @else
        @php $enrolments = $this->getEnrolments(); @endphp

        @if ($enrolments->isEmpty())
            <p class="text-center text-gray-400 py-12">No competitors found.</p>
        @else
            <div id="enrolment-list"></div>
            @php
                $notCheckedIn = $enrolments->filter(fn ($e) => ! $e->checked_in && $e->status !== 'withdrawn');
                $checkedIn    = $enrolments->filter(fn ($e) => $e->checked_in);
                $withdrawn    = $enrolments->filter(fn ($e) => $e->status === 'withdrawn');
            @endphp

            @if ($notCheckedIn->isNotEmpty())
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-3">Not checked in ({{ $notCheckedIn->count() }})</h2>
                <div class="space-y-3 mb-8">
                    @foreach ($notCheckedIn as $enrolment)
                        @include('filament.admin.partials.checkin-card', ['enrolment' => $enrolment, 'pendingDivisionChange' => $this->pendingWeightConfirm[$enrolment->id] ?? null, 'competitionStatus' => $competition->status])
                    @endforeach
                </div>
            @endif

            @if ($checkedIn->isNotEmpty())
                <h2 class="text-sm font-semibold uppercase tracking-wide text-success-600 mb-3">Checked in ({{ $checkedIn->count() }})</h2>
                <div class="space-y-3 mb-8">
                    @foreach ($checkedIn as $enrolment)
                        @include('filament.admin.partials.checkin-card', ['enrolment' => $enrolment, 'pendingDivisionChange' => $this->pendingWeightConfirm[$enrolment->id] ?? null, 'competitionStatus' => $competition->status])
                    @endforeach
                </div>
            @endif

            @if ($withdrawn->isNotEmpty())
                <h2 class="text-sm font-semibold uppercase tracking-wide text-danger-600 mb-3">Withdrawn ({{ $withdrawn->count() }})</h2>
                <div class="space-y-3 mb-8">
                    @foreach ($withdrawn as $enrolment)
                        @include('filament.admin.partials.checkin-card', ['enrolment' => $enrolment, 'pendingDivisionChange' => $this->pendingWeightConfirm[$enrolment->id] ?? null, 'competitionStatus' => $competition->status])
                    @endforeach
                </div>
            @endif
        @endif
    @endif

    <style>
        @keyframes card-pulse-found {
            0%   { box-shadow: 0 0 0 0   rgba(99,102,241,0.8); }
            70%  { box-shadow: 0 0 0 10px rgba(99,102,241,0);  }
            100% { box-shadow: 0 0 0 0   rgba(99,102,241,0);  }
        }
        .card-pulse-found { animation: card-pulse-found 0.7s ease-out 4; }
    </style>
    <script>
        function pulseCard(el, cls, done) {
            if (!el) { if (done) done(); return; }
            el.classList.remove('card-pulse-found', 'card-pulse-success');
            void el.offsetWidth;
            el.classList.add(cls);
            el.addEventListener('animationend', function handler() {
                el.classList.remove(cls);
                el.removeEventListener('animationend', handler);
                if (done) done();
            });
        }

        window.addEventListener('checkin-code-matched', function () {
            setTimeout(function () {
                var el = document.getElementById('enrolment-list');
                if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                var card = document.querySelector('[data-enrolment-id]');
                pulseCard(card, 'card-pulse-found');
            }, 80);
        });

        window.addEventListener('checkin-complete', function (e) {
            var id = e.detail && e.detail.id;
            setTimeout(function () {
                if (id) {
                    var card = document.querySelector('[data-enrolment-id="' + id + '"]');
                    if (card) {
                        card.classList.add('card-entering');
                        card.addEventListener('animationend', function h() {
                            card.classList.remove('card-entering');
                            card.removeEventListener('animationend', h);
                        });
                    }
                }
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }, 80);
        });

        window.addEventListener('payment-recorded', function (e) {
            var id = e.detail && e.detail.id;
            if (!id) return;
            setTimeout(function () {
                var card = document.querySelector('[data-enrolment-id="' + id + '"]');
                if (card) {
                    card.classList.remove('payment-flashing');
                    void card.offsetWidth;
                    card.classList.add('payment-flashing');
                    card.addEventListener('animationend', function h() {
                        card.classList.remove('payment-flashing');
                        card.removeEventListener('animationend', h);
                    });
                }
            }, 80);
        });
    </script>
</x-filament-panels::page>
