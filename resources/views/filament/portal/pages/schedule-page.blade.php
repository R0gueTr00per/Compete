<x-filament-panels::page>
    @php
        $competition    = $this->getCompetition();
        $locations      = $this->getLocations();
        $divisions      = $this->getDivisions();
        $myDivisionIds  = $this->getMyDivisionIds();
        $shareUrl       = $competition?->isPublicScheduleAvailable()
            ? config('app.scheme') . '://' . app('tenant')->slug . '.' . config('app.domain') . '/schedule/' . $competition->id
            : null;
    @endphp

    @if (! $competition)
        <x-filament::section>
            <p class="text-center text-gray-500 py-8">No active competition found.</p>
        </x-filament::section>
    @else
        <div x-data="{
            shareOpen: false,
            copied: false,
            selected: null,
            async copyQr() {
                const svg = this.$refs.qrcode.querySelector('svg');
                const svgData = new XMLSerializer().serializeToString(svg);
                const blob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
                const url = URL.createObjectURL(blob);
                const img = new Image();
                await new Promise(resolve => { img.onload = resolve; img.src = url; });
                const canvas = document.createElement('canvas');
                canvas.width = img.naturalWidth;
                canvas.height = img.naturalHeight;
                canvas.getContext('2d').drawImage(img, 0, 0);
                URL.revokeObjectURL(url);
                canvas.toBlob(async png => {
                    await navigator.clipboard.write([new ClipboardItem({ 'image/png': png })]);
                    this.copied = true;
                    setTimeout(() => this.copied = false, 2000);
                }, 'image/png');
            }
        }">

        {{-- Competition header --}}
        <x-filament::section>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-500">
                <span>{{ tenant_date($competition->competition_date) }}</span>
                @if ($competition->location_name)
                    <span>&middot; {{ $competition->location_name }}</span>
                @endif
                @if ($competition->start_time)
                    <span>&middot; Starts {{ tenant_time($competition->start_time) }}</span>
                @endif
                <span class="sm:ml-auto flex items-center gap-2 text-xs text-gray-400">
                    Updated {{ tenant_time(now()) }}
                    <x-filament::button size="xs" color="gray" wire:click="$refresh">
                        Refresh
                    </x-filament::button>
                    @if ($shareUrl)
                        <button
                            type="button"
                            x-on:click="shareOpen = true"
                            title="Share schedule"
                            class="inline-flex items-center gap-1 px-2 py-1 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 transition text-xs"
                        >
                            <x-heroicon-o-arrow-up-on-square class="w-3.5 h-3.5" />
                            Share
                        </button>
                    @endif
                </span>
            </div>

            <p class="mt-2 text-xs text-gray-400 italic">Organisers reserve the right to merge or cancel any event on the day.</p>

        </x-filament::section>

        {{-- Share modal --}}
        @if ($shareUrl)
            <div
                x-show="shareOpen"
                x-on:click.self="shareOpen = false"
                x-on:keydown.escape.window="shareOpen = false"
                x-transition
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                style="display: none;"
            >
                <div class="bg-white rounded-xl shadow-xl p-6 max-w-sm w-full space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900">Share Schedule &amp; Results</h3>
                        <button type="button" x-on:click="shareOpen = false" class="text-gray-400 hover:text-gray-600 -mr-1 p-1">
                            <x-heroicon-o-x-mark class="w-5 h-5" />
                        </button>
                    </div>
                    <div x-ref="qrcode" class="flex justify-center">
                        <x-qr-code :value="$shareUrl" :size="220" />
                    </div>
                    <div class="rounded-lg bg-gray-100 border border-gray-200 px-3 py-2 text-center">
                        <a href="{{ $shareUrl }}" target="_blank" style="color: #2563eb; font-size: 0.875rem; word-break: break-all;" class="hover:underline">
                            {{ $shareUrl }}
                        </a>
                    </div>
                    <div class="flex justify-center">
                        <button
                            type="button"
                            x-on:click="copyQr()"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 transition"
                        >
                            <svg x-show="!copied" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-4 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                            <svg x-show="copied" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            <span x-text="copied ? 'Copied!' : 'Copy QR code'"></span>
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Schedule --}}
        @if ($divisions->isEmpty())
            <x-filament::section>
                <p class="text-center text-gray-400 py-12">No divisions scheduled yet.</p>
            </x-filament::section>
        @else
            @php
                $activeLocations = collect($locations)->filter(fn ($l) => $divisions->has($l))->values();
                $allDivisions    = $divisions->flatten(1);
                $placementLabels = ['1st', '2nd', '3rd'];
                $placementColors = [
                    1 => 'bg-yellow-100 text-yellow-800 border border-yellow-300',
                    2 => 'bg-gray-100 text-gray-700 border border-gray-300',
                    3 => 'bg-orange-100 text-orange-800 border border-orange-300',
                ];
            @endphp

            {{-- Legend --}}
            <div class="mb-3 flex flex-wrap gap-4 text-xs text-gray-600 dark:text-gray-400">
                <span class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-sm inline-block bg-green-100 dark:bg-green-900/40 border border-green-300 dark:border-green-700"></span> Complete
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-sm inline-block bg-indigo-100 dark:bg-indigo-900/40 border border-indigo-300 dark:border-indigo-700"></span> Scheduled
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-sm inline-block bg-white dark:bg-slate-800 border border-gray-200 dark:border-gray-600 ring-2 ring-gray-800 dark:ring-white"></span> My division
                </span>
            </div>

            {{-- ── Mobile: compact all-mats grid ── --}}
            <div class="sm:hidden" :class="selected !== null ? 'pb-56' : 'pb-2'">
                <div class="flex gap-1.5">
                    @foreach ($activeLocations as $location)
                        <div class="flex-1 min-w-0">
                            <div class="text-center text-xs font-bold text-gray-500 dark:text-gray-400 truncate mb-2 pb-1.5 border-b border-gray-200 dark:border-gray-700">
                                {{ $location }}
                            </div>
                            <div class="space-y-1">
                                @foreach ($divisions[$location] as $div)
                                    @php
                                        $isMyDiv = in_array($div->id, $myDivisionIds);
                                        $cardBg  = $div->status === 'complete'
                                            ? 'bg-green-100 border-green-300'
                                            : 'bg-indigo-100 border-indigo-200';
                                        if ($isMyDiv) $cardBg .= ' ring-2 ring-gray-800 dark:ring-white';
                                    @endphp
                                    <button
                                        type="button"
                                        @click="selected = selected === {{ $div->id }} ? null : {{ $div->id }}"
                                        :class="selected === {{ $div->id }} ? 'ring-2 ring-offset-1 ring-blue-500' : ''"
                                        class="w-full rounded border {{ $cardBg }} px-1.5 py-1.5 text-left transition-shadow"
                                    >
                                        <div class="flex items-center justify-between gap-1">
                                            <span class="font-mono text-xs font-bold leading-none text-gray-800 dark:text-white">{{ $div->code }}</span>
                                            @if ($div->status === 'complete')
                                                <svg class="flex-none h-2.5 w-2.5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                            @endif
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 leading-tight mt-0.5 truncate">{{ $div->competitionEvent->name }}</div>
                                        <div class="text-gray-600 dark:text-gray-300 leading-tight truncate" style="font-size:10px">{{ $div->label }}</div>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- ── Mobile: slide-up detail panel ── --}}
            <div
                x-show="selected !== null"
                x-cloak
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="translate-y-full"
                x-transition:enter-end="translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="translate-y-0"
                x-transition:leave-end="translate-y-full"
                class="sm:hidden fixed bottom-0 inset-x-0 z-20 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 shadow-xl rounded-t-xl"
            >
                <div class="flex items-center justify-between px-4 pt-3 pb-2 border-b border-gray-100 dark:border-gray-700">
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Division Details</span>
                    <button type="button" @click="selected = null" class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="px-4 py-4 overflow-y-auto max-h-48">
                    @foreach ($allDivisions as $div)
                        <div x-show="selected === {{ $div->id }}" x-cloak>
                            <div class="flex items-start gap-3">
                                <div class="font-mono text-xl font-bold text-gray-900 dark:text-white leading-none pt-0.5">{{ $div->code }}</div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $div->competitionEvent->name }}</div>
                                    <div class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $div->label }}</div>
                                    @if (in_array($div->id, $myDivisionIds))
                                        <span class="inline-block mt-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-800 text-white dark:bg-white dark:text-gray-900">Registered</span>
                                    @endif
                                </div>
                            </div>

                            @if ($div->status === 'complete')
                                @php
                                    $placements = $div->activeEnrolmentEvents
                                        ->filter(fn ($ee) => $ee->result?->placement)
                                        ->sortBy(fn ($ee) => $ee->result->placement)
                                        ->take(3);
                                @endphp
                                @if ($placements->isNotEmpty())
                                    <div class="mt-3 space-y-1.5 border-t border-gray-100 dark:border-gray-700 pt-3">
                                        @foreach ($placements as $ee)
                                            @php
                                                $pName = $ee->enrolment->competitor?->full_name ?? '—';
                                            @endphp
                                            <div class="flex items-center gap-2 text-sm">
                                                <span class="flex-none inline-block px-2 py-0.5 rounded text-xs font-bold {{ $placementColors[$ee->result->placement] ?? 'bg-gray-100 text-gray-600' }}">
                                                    {{ $placementLabels[$ee->result->placement - 1] ?? $ee->result->placement . 'th' }}
                                                </span>
                                                <span class="text-gray-700 dark:text-gray-300 {{ $ee->result->placement === 1 ? 'font-bold' : '' }}">
                                                    {{ $pName }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- ── Desktop: full-detail horizontal scroll ── --}}
            <div class="hidden sm:block w-full overflow-x-auto px-1 pt-1 pb-4">
                <div class="flex gap-4 items-start" style="min-width: max-content;">
                    @foreach ($activeLocations as $location)
                        <div class="flex-none w-64">
                            <h2 class="text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-3 pb-2 border-b-2 border-gray-200 dark:border-gray-700">
                                {{ $location }}
                            </h2>

                            <div class="space-y-2">
                                @foreach ($divisions[$location] as $div)
                                    @php
                                        $isMyDiv   = in_array($div->id, $myDivisionIds);
                                        $cardClass = $div->status === 'complete'
                                            ? 'bg-green-100 dark:bg-green-900/40 border-green-300 dark:border-green-700'
                                            : 'bg-indigo-100 dark:bg-indigo-900/40 border-indigo-200 dark:border-indigo-700';
                                        if ($isMyDiv) $cardClass .= ' ring-2 ring-gray-800 dark:ring-white';
                                    @endphp
                                    <div class="rounded-md border px-3 py-2 shadow-sm {{ $cardClass }}">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="font-mono text-xs font-bold text-gray-900 dark:text-white">{{ $div->code }}</span>
                                            @if ($div->status === 'complete')
                                                <x-heroicon-m-check-circle class="h-4 w-4 text-green-600 dark:text-green-400" />
                                            @endif
                                        </div>
                                        <div class="text-xs text-gray-600 dark:text-gray-300 mt-0.5">{{ $div->competitionEvent->name }}</div>
                                        <div class="text-xs font-medium text-gray-800 dark:text-gray-200 mt-0.5">{{ $div->label }}</div>
                                        @if ($div->status === 'complete')
                                            @php
                                                $placements = $div->activeEnrolmentEvents
                                                    ->filter(fn ($ee) => $ee->result?->placement)
                                                    ->sortBy(fn ($ee) => $ee->result->placement)
                                                    ->take(3);
                                            @endphp
                                            @foreach ($placements as $ee)
                                                @php
                                                    $pName = $ee->enrolment->competitor?->full_name ?? '—';
                                                    $medal = match($ee->result->placement) { 1 => '🥇', 2 => '🥈', 3 => '🥉', default => $ee->result->placement . '.' };
                                                @endphp
                                                <div class="text-xs text-gray-700 dark:text-gray-300 mt-0.5">{{ $medal }} {{ $pName }}</div>
                                            @endforeach
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

        @endif
        </div>{{-- /x-data --}}
    @endif
</x-filament-panels::page>
