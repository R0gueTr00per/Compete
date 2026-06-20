<x-filament-panels::page>
    @php
        $profiles           = $this->getProfiles();
        $activeCompetitions = $this->getActiveCompetitions();
        $cartKeys           = $this->getCartDraftKeys();
        $allEnrolments      = $this->getAllEnrolments();

        // Competition currently in the draft cart (if any), for cross-competition conflict detection
        $cartCompetitionId  = null;
        if (! empty($cartKeys)) {
            $parts             = explode(':', $cartKeys[0]);
            $cartCompetitionId = (int) ($parts[1] ?? 0) ?: null;
        }

        $incompleteProfiles = $profiles->filter(fn ($p) => ! $p->profile_complete);
    @endphp

    {{-- Incomplete profile banner --}}
    @if ($incompleteProfiles->isNotEmpty())
        <div class="flex items-start gap-3 p-4 mb-2 bg-warning-50 dark:bg-warning-900/20 rounded-lg border border-warning-200 dark:border-warning-800">
            <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-warning-600 shrink-0 mt-0.5" />
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-warning-800 dark:text-warning-200">
                    @if ($incompleteProfiles->count() === 1)
                        Profile incomplete — complete it before registering in competitions.
                    @else
                        {{ $incompleteProfiles->count() }} profiles are incomplete.
                    @endif
                </p>
                <div class="flex flex-wrap gap-2 mt-2">
                    @foreach ($incompleteProfiles as $ip)
                        <a href="{{ route('filament.portal.pages.profile') }}{{ $ip->profile_type === 'family_member' ? '?profileId=' . $ip->id : '' }}"
                           class="inline-flex items-center gap-1 text-xs font-medium text-warning-700 dark:text-warning-300 underline underline-offset-2">
                            {{ $ip->full_name }} →
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if ($profiles->isEmpty())
        <x-filament::section>
            <p class="text-center text-gray-500 py-8">Complete your profile to get started.</p>
            <div class="flex justify-center mt-2">
                <x-filament::button href="{{ route('filament.portal.pages.profile') }}" tag="a">
                    Complete profile
                </x-filament::button>
            </div>
        </x-filament::section>
    @elseif ($activeCompetitions->isEmpty())
        <x-filament::section>
            <div class="flex flex-col items-center py-8 gap-5">
                <img src="{{ asset('images/logo2.png') }}" alt="Kompetic" class="w-32 h-32 object-contain opacity-40 dark:opacity-25" />
                <div class="text-center">
                    <p class="text-base font-semibold text-gray-700 dark:text-gray-200">No competitions right now</p>
                    <p class="mt-1 text-sm text-gray-400 dark:text-gray-500 max-w-xs">Your organisation hasn't opened any competitions yet. Check back soon — you'll see them here when registration opens.</p>
                </div>
            </div>
        </x-filament::section>
    @else
        <div class="space-y-4">
        @foreach ($activeCompetitions as $competition)
            @php
                $statusLabel = match($competition->status) {
                    'advertise'         => 'Coming Soon',
                    'open'              => 'Open',
                    'enrolments_closed' => 'Registrations Closed',
                    'running'           => 'In Progress',
                    'complete'          => 'Finished',
                    default             => ucfirst($competition->status),
                };

                $statusBadgeClass = match($competition->status) {
                    'advertise'         => 'bg-indigo-100/60 text-indigo-700 border-indigo-200/60 dark:bg-indigo-900/30 dark:text-indigo-300 dark:border-indigo-700/40',
                    'open'              => 'bg-green-100/60 text-green-700 border-green-200/60 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700/40',
                    'enrolments_closed' => 'bg-gray-100/60 text-gray-500 border-gray-200/60 dark:bg-gray-800/40 dark:text-gray-400 dark:border-gray-700/40',
                    'running'           => 'bg-blue-100/60 text-blue-700 border-blue-200/60 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-700/40',
                    default             => 'bg-gray-100/60 text-gray-500 border-gray-200/60 dark:bg-gray-800/40 dark:text-gray-400 dark:border-gray-700/40',
                };

                $statusIcon = match($competition->status) {
                    'advertise'         => 'heroicon-m-megaphone',
                    'open'              => 'heroicon-m-lock-open',
                    'enrolments_closed' => 'heroicon-m-lock-closed',
                    'running'           => 'heroicon-m-play',
                    'complete'          => 'heroicon-m-check',
                    default             => null,
                };

                $accentColorClass = match($competition->status) {
                    'advertise'         => 'border-l-indigo-400 dark:border-l-indigo-500',
                    'open'              => 'border-l-green-400 dark:border-l-green-500',
                    'enrolments_closed' => 'border-l-gray-300 dark:border-l-slate-600',
                    'running'           => 'border-l-blue-400 dark:border-l-blue-500',
                    'complete'          => 'border-l-gray-300 dark:border-l-slate-600',
                    default             => 'border-l-gray-200 dark:border-l-slate-700',
                };

                $glowClass = match($competition->status) {
                    'advertise' => 'shadow-[0_0_20px_-5px_rgba(129,140,248,0.35)]',
                    'open'      => 'shadow-[0_0_20px_-5px_rgba(74,222,128,0.35)]',
                    'running'   => 'shadow-[0_0_20px_-5px_rgba(96,165,250,0.35)]',
                    default     => '',
                };

                $showSchedule  = $competition->status === 'running';
                $isMultiDay    = $competition->competitionDays->count() > 1;
                $enrolmentOpen = $competition->isEnrolmentOpen();

                $compEnrolments = $profiles->map(fn($p) => $allEnrolments->get($p->id)?->get($competition->id))
                    ->filter(fn($e) => $e && $e->status !== 'withdrawn');
                $enrolledCount = $compEnrolments->count();
                $totalFee = $compEnrolments->sum(fn($e) => $e->fee_calculated + (float)($e->cart?->platform_fee_rate ?? app('tenant')?->platform_fee ?? 0));
            @endphp

            <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 border-l-4 {{ $accentColorClass }} {{ $glowClass }} bg-white dark:bg-gray-900 comp-card-enter"
                style="animation-delay: {{ $loop->index * 80 }}ms"
            >

                {{-- Competition header --}}
                <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 flex items-center gap-3">

                    {{-- Date calendar widget --}}
                    <div class="flex-shrink-0 flex flex-col items-center justify-center w-12 h-12 rounded-lg bg-primary-500 dark:bg-primary-600 text-white text-center leading-none select-none shadow-sm">
                        <span class="text-[0.65rem] font-bold uppercase tracking-wide opacity-90">
                            {{ $competition->competition_date->format('M') }}
                        </span>
                        <span class="text-xl font-bold leading-none mt-0.5">
                            {{ $competition->competition_date->format('j') }}
                        </span>
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $competition->name }}</p>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium border {{ $statusBadgeClass }}">
                                @if ($statusIcon)
                                    <x-dynamic-component :component="$statusIcon" class="w-3 h-3 flex-shrink-0" />
                                @endif
                                {{ $statusLabel }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            @if ($competition->location_name)
                                @if ($competition->location_url)
                                    <a href="{{ $competition->location_url }}" target="_blank" rel="noopener noreferrer"
                                       class="hover:underline"
                                       @if ($competition->location_address) title="{{ $competition->location_address }}" @endif>
                                        <x-heroicon-o-map-pin class="w-3 h-3 inline-block mr-0.5 -mt-px opacity-60" />{{ $competition->location_name }}
                                    </a>
                                @else
                                    <span @if ($competition->location_address) title="{{ $competition->location_address }}" @endif>
                                        <x-heroicon-o-map-pin class="w-3 h-3 inline-block mr-0.5 -mt-px opacity-60" />{{ $competition->location_name }}
                                    </span>
                                    @if ($competition->location_address)
                                        <span class="sm:hidden"> &mdash; {{ $competition->location_address }}</span>
                                    @endif
                                @endif
                            @endif
                            @if ($competition->checkin_time)
                                @if ($competition->location_name) &mdash; @endif
                                Check-in {{ tenant_time($competition->checkin_time) }}
                            @endif
                            @if ($competition->start_time)
                                @if ($competition->location_name || $competition->checkin_time) &mdash; @endif
                                Starts {{ tenant_time($competition->start_time) }}
                            @endif
                            @if ($competition->end_time)
                                &mdash; Ends {{ tenant_time($competition->end_time) }}
                            @endif
                        </p>
                        @if ($enrolledCount > 0 && $competition->status !== 'advertise')
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                {{ $enrolledCount }} {{ Str::plural('athlete', $enrolledCount) }} registered &middot; {{ tenant_money($totalFee) }} total
                            </p>
                        @endif
                    </div>

                </div>

                {{-- Portal messages from organiser --}}
                @php
                    $anyActiveEnrolment = $allEnrolments
                        ->map(fn($byComp) => $byComp->get($competition->id))
                        ->filter(fn($e) => $e && $e->status !== 'withdrawn')
                        ->isNotEmpty();
                @endphp
                @if ($competition->portalMessages->isNotEmpty() && ! in_array($competition->status, ['advertise', 'complete']) && $anyActiveEnrolment)
                    <div class="px-4 py-2 space-y-1 border-b border-gray-100 dark:border-slate-700 bg-primary-50/50 dark:bg-primary-900/10">
                        @foreach ($competition->portalMessages as $msg)
                            <p class="text-sm text-primary-900 dark:text-primary-100">{{ $msg->message }}</p>
                        @endforeach
                    </div>
                @endif

                {{-- Profile rows --}}
                @if ($competition->status !== 'advertise')
                @php
                    $sortedProfiles = $profiles
                        ->sortBy(fn ($p) => $p->first_name . ' ' . $p->surname)
                        ->sortBy(function ($p) use ($allEnrolments, $cartKeys, $competition) {
                            $isRegistered = ($allEnrolments->get($p->id)?->get($competition->id) !== null &&
                                $allEnrolments->get($p->id)->get($competition->id)->status !== 'withdrawn')
                                || in_array("{$p->id}:{$competition->id}", $cartKeys);
                            $isSelf = $p->profile_type === 'self';
                            if ($isRegistered && $isSelf) return 0;
                            if ($isRegistered)              return 1;
                            return 2;
                        });
                @endphp
                <div class="p-3 space-y-2">
                    @foreach ($sortedProfiles as $profile)
                        @php
                            $enrolment     = $allEnrolments->get($profile->id)?->get($competition->id);
                            $isEnrolled    = $enrolment !== null;
                            $isWithdrawn   = $isEnrolled && $enrolment->status === 'withdrawn';
                            $inCart        = in_array("{$profile->id}:{$competition->id}", $cartKeys);
                            $canRegister   = (! $isEnrolled || $isWithdrawn) && ! $inCart && $enrolmentOpen && $profile->is_active && $profile->profile_complete;
                            $isUnpaid      = $isEnrolled && ! $isWithdrawn && $enrolment->cart && ! $enrolment->cart->isPaid();
                            $canShowPayQr  = $isUnpaid && (app('tenant')?->instructorsCanAcceptPayments() ?? false);
                            $canShowQr     = $isEnrolled && ! $isWithdrawn && $enrolment->checkin_code
                                && ($canShowPayQr || in_array($competition->status, ['enrolments_closed', 'running']));
                        @endphp

                        @if (! $isEnrolled && ! $inCart && ! $canRegister) @continue @endif

                        <div x-data="{ qrOpen: false }"
                             class="rounded-lg border border-gray-100 dark:border-gray-700/60 bg-gray-50 dark:bg-gray-800 p-3 hover:bg-gray-100 dark:hover:bg-gray-700/60 transition-colors">

                            {{-- Profile row header --}}
                            <div class="flex items-center gap-3">
                                {{-- Avatar --}}
                                <div class="flex-shrink-0">
                                    @if ($profile->profile_photo)
                                        <img src="{{ asset('storage/' . $profile->profile_photo) }}"
                                             alt="{{ $profile->full_name }}"
                                             class="w-8 h-8 rounded-full object-cover border border-gray-200 dark:border-gray-600" />
                                    @else
                                        <div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 flex items-center justify-center">
                                            <x-heroicon-o-user class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                                        </div>
                                    @endif
                                </div>

                                <div class="flex-1 flex items-center gap-1.5 min-w-0 flex-wrap">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $profile->full_name }}
                                        @unless ($profile->is_active)
                                            <span class="ml-1 text-xs font-normal text-warning-600">(Inactive)</span>
                                        @endunless
                                    </span>
                                    @if ($isEnrolled && ! $isWithdrawn && ! $isMultiDay && $enrolment->status === 'checked_in' && $competition->status !== 'complete')
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-xs font-medium border bg-green-100/60 text-green-700 border-green-200/60 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700/40 flex-shrink-0">
                                            <x-heroicon-m-check class="w-3 h-3" />
                                            Checked in
                                        </span>
                                    @endif
                                </div>

                                {{-- Schedule button --}}
                                @if ($isEnrolled && ! $isWithdrawn && $showSchedule)
                                    <x-filament::button
                                        href="{{ route('filament.portal.pages.schedule-page') }}?competition_id={{ $competition->id }}&profile_id={{ $profile->id }}"
                                        tag="a" color="gray" size="sm" icon="heroicon-o-calendar-days"
                                        class="flex-shrink-0">
                                        Schedule
                                    </x-filament::button>
                                @endif

                                {{-- QR button (desktop) --}}
                                @if ($canShowQr)
                                    <button x-on:click="qrOpen = true"
                                        class="flex-shrink-0 hidden sm:flex items-center justify-center w-8 h-8 rounded-lg border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-700 hover:border-primary-400 dark:hover:border-primary-500 transition-colors overflow-hidden p-0.5">
                                        <x-qr-code :value="$canShowPayQr ? \App\Filament\Portal\Pages\AcceptPaymentPage::getUrl(['code' => $enrolment->checkin_code]) : (url('/manage/check-in') . '?competition_id=' . $competition->id . '&code=' . $enrolment->checkin_code)" :size="24" />
                                    </button>
                                @endif

                                {{-- Register / Cart actions --}}
                                @if ($inCart)
                                    <x-filament::button
                                        href="{{ \App\Filament\Portal\Pages\CartPage::getUrl() }}"
                                        tag="a" color="success" size="sm" icon="heroicon-o-shopping-cart"
                                        class="flex-shrink-0">
                                        Check out
                                    </x-filament::button>
                                @elseif ($canRegister)
                                    @if ($cartCompetitionId && $cartCompetitionId !== $competition->id)
                                        <x-filament::button
                                            wire:click="showCartConflict({{ $cartCompetitionId }})"
                                            color="gray" size="sm" icon="heroicon-o-plus"
                                            class="flex-shrink-0 opacity-50 cursor-not-allowed">
                                            Register
                                        </x-filament::button>
                                    @else
                                        <x-filament::button
                                            href="{{ route('filament.portal.pages.enrol') }}?profile_id={{ $profile->id }}&competition_id={{ $competition->id }}&redirect_to=dashboard"
                                            tag="a" color="primary" size="sm" icon="heroicon-o-plus"
                                            class="flex-shrink-0">
                                            Register
                                        </x-filament::button>
                                    @endif
                                @endif

                                @if ($isEnrolled && ! $isWithdrawn && $this->canWithdraw($enrolment))
                                    <button
                                        wire:click="startWithdraw({{ $enrolment->id }})"
                                        class="text-xs text-danger-600 hover:text-danger-700 dark:text-danger-400 underline flex-shrink-0"
                                    >Withdraw</button>
                                @endif
                            </div>

                            @if ($isEnrolled && ! $isWithdrawn)
                                {{-- Mobile QR --}}
                                @if ($canShowQr)
                                    <div x-on:click="qrOpen = true"
                                         class="qr-reveal sm:hidden flex flex-col items-center gap-1 py-3 border-b border-gray-100 dark:border-slate-700 cursor-pointer active:opacity-70 transition-opacity">
                                        <p class="text-xs text-gray-400 dark:text-gray-500">{{ $canShowPayQr ? 'Payment QR' : 'Check-in QR' }} <span class="text-gray-300 dark:text-gray-600">&middot; tap to enlarge</span></p>
                                        <x-qr-code :value="$canShowPayQr ? \App\Filament\Portal\Pages\AcceptPaymentPage::getUrl(['code' => $enrolment->checkin_code]) : (url('/manage/check-in') . '?competition_id=' . $competition->id . '&code=' . $enrolment->checkin_code)" :size="128" />
                                        <span class="text-xs font-mono font-semibold tracking-widest text-gray-600 dark:text-gray-400">{{ $enrolment->checkin_code }}</span>
                                    </div>
                                @endif

                                @php
                                    $displayFee = $enrolment->fee_calculated + (float) ($enrolment->cart?->platform_fee_rate ?? app('tenant')?->platform_fee ?? 0);
                                @endphp

                                {{-- Fee row --}}
                                <div class="mt-2 sm:ml-11 flex items-center gap-2 flex-wrap">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        Fee: <strong class="text-gray-700 dark:text-gray-300">{{ tenant_money($displayFee) }}</strong>
                                        @if ($enrolment->is_late)
                                            <span class="text-warning-600">(late)</span>
                                        @endif
                                    </span>
                                </div>

                                {{-- Profile attributes row --}}
                                @if ($enrolment->display_rank !== '—' || $enrolment->weight_kg || $enrolment->dojo_name || $enrolment->guest_style)
                                <div class="mt-0.5 sm:ml-11 flex items-center gap-1 text-xs text-gray-400 dark:text-gray-500 flex-wrap">
                                    @if ($enrolment->display_rank !== '—')
                                        <span>{{ $enrolment->display_rank }}</span>
                                    @endif
                                    @if ($enrolment->weight_kg)
                                        @if ($enrolment->display_rank !== '—') <span class="opacity-40">&bull;</span> @endif
                                        <span>{{ $enrolment->weight_kg }} kg</span>
                                    @endif
                                    @if ($enrolment->dojo_name)
                                        <span class="opacity-40">&bull;</span>
                                        <span>{{ $enrolment->dojo_name }}</span>
                                    @elseif ($enrolment->guest_style)
                                        <span class="opacity-40">&bull;</span>
                                        <span>{{ $enrolment->guest_style }} (guest)</span>
                                    @endif
                                </div>
                                @endif

                                {{-- Events as chips (grouped by day for multi-day competitions) --}}
                                @php
                                    $compDays = $competition->competitionDays->sortBy('date');
                                    $byDay    = $isMultiDay
                                        ? $enrolment->activeEvents->groupBy(fn ($ee) => $ee->division?->competition_day_id)
                                        : null;
                                @endphp

                                @if ($isMultiDay)
                                    @foreach ($compDays as $day)
                                        @php
                                            $dayEvents    = $byDay->get($day->id, collect());
                                            $dayCheckedIn = $enrolment->checkedInForDay($day->id);
                                        @endphp
                                        <div class="mt-2 sm:ml-11">
                                            <div class="flex items-center gap-2 mb-1.5">
                                                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ tenant_date($day->date) }}@if($day->label) &mdash; {{ $day->label }}@endif</span>
                                                @if ($dayCheckedIn && in_array($competition->status, ['enrolments_closed', 'running']))
                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-xs font-medium border bg-green-100/60 text-green-700 border-green-200/60 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700/40 flex-shrink-0">
                                                        <x-heroicon-m-check class="w-3 h-3" />Checked in
                                                    </span>
                                                @elseif (! $dayCheckedIn && in_array($competition->status, ['enrolments_closed', 'running']))
                                                    <span class="text-xs text-gray-400 dark:text-gray-500">Not yet checked in</span>
                                                @endif
                                            </div>
                                            @if ($dayEvents->isNotEmpty())
                                                <div class="flex flex-wrap gap-1.5">
                                                    @foreach ($dayEvents as $ee)
                                                        <div class="inline-flex items-stretch rounded-md border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-900 text-xs shadow-sm overflow-hidden">
                                                            @if ($ee->result)
                                                                @if ($ee->result->disqualified)
                                                                    <div class="flex items-center px-2 bg-danger-50 dark:bg-danger-900/20 border-r border-gray-200 dark:border-slate-600 shrink-0">
                                                                        <span class="font-semibold text-danger-600 dark:text-danger-400">DQ</span>
                                                                    </div>
                                                                @elseif ($ee->result->placement)
                                                                    @php $placeEmoji = match($ee->result->placement) { 1 => '🥇', 2 => '🥈', 3 => '🥉', default => $ee->result->placement . 'th' }; @endphp
                                                                    <div class="flex items-center px-2 bg-gray-50 dark:bg-slate-700/50 border-r border-gray-200 dark:border-slate-600 shrink-0">
                                                                        <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $placeEmoji }}</span>
                                                                    </div>
                                                                @elseif ($ee->result->win_loss)
                                                                    <div class="flex items-center px-2 bg-gray-50 dark:bg-slate-700/50 border-r border-gray-200 dark:border-slate-600 shrink-0">
                                                                        <span class="font-semibold {{ $ee->result->win_loss === 'win' ? 'text-success-600 dark:text-success-400' : ($ee->result->win_loss === 'loss' ? 'text-danger-600 dark:text-danger-400' : 'text-gray-500') }}">{{ ucfirst($ee->result->win_loss) }}</span>
                                                                    </div>
                                                                @endif
                                                            @endif
                                                            @if ($ee->division)
                                                                <div class="flex items-center justify-center px-2.5 bg-gray-100 dark:bg-gray-700 border-r border-gray-200 dark:border-gray-600 shrink-0">
                                                                    <span class="font-mono font-bold text-gray-600 dark:text-gray-300">{{ $ee->division->code }}</span>
                                                                </div>
                                                            @endif
                                                            <div class="flex flex-col px-2.5 py-1.5">
                                                                <span class="font-medium text-gray-700 dark:text-gray-300 leading-snug">
                                                                    {{ $ee->competitionEvent->name }}@if ($ee->competitionEvent->requires_partner) <span class="ml-1 {{ $ee->yakusuko_complete ? 'text-success-500' : 'text-warning-500' }}">{{ $ee->yakusuko_complete ? '✓' : '?' }} Partner</span>@endif
                                                                </span>
                                                                @if ($ee->division)
                                                                    <span class="text-[0.65rem] text-gray-400 dark:text-gray-500 mt-0.5 leading-snug">{{ $ee->division->label }}</span>
                                                                @else
                                                                    <span class="text-[0.65rem] italic text-gray-400 dark:text-gray-500 mt-0.5">TBC</span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach

                                    @php $unassigned = $byDay->get(null, collect()); @endphp
                                    @if ($unassigned->isNotEmpty())
                                        <div class="mt-2 sm:ml-11">
                                            <span class="text-xs font-semibold text-gray-400 dark:text-gray-500 mb-1.5 block">Day TBC</span>
                                            <div class="flex flex-wrap gap-1.5">
                                                @foreach ($unassigned as $ee)
                                                    <div class="inline-flex items-stretch rounded-md border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-900 text-xs shadow-sm overflow-hidden">
                                                        @if ($ee->result)
                                                            @if ($ee->result->disqualified)
                                                                <div class="flex items-center px-2 bg-danger-50 dark:bg-danger-900/20 border-r border-gray-200 dark:border-slate-600 shrink-0">
                                                                    <span class="font-semibold text-danger-600 dark:text-danger-400">DQ</span>
                                                                </div>
                                                            @elseif ($ee->result->placement)
                                                                @php $placeEmoji = match($ee->result->placement) { 1 => '🥇', 2 => '🥈', 3 => '🥉', default => $ee->result->placement . 'th' }; @endphp
                                                                <div class="flex items-center px-2 bg-gray-50 dark:bg-slate-700/50 border-r border-gray-200 dark:border-slate-600 shrink-0">
                                                                    <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $placeEmoji }}</span>
                                                                </div>
                                                            @elseif ($ee->result->win_loss)
                                                                <div class="flex items-center px-2 bg-gray-50 dark:bg-slate-700/50 border-r border-gray-200 dark:border-slate-600 shrink-0">
                                                                    <span class="font-semibold {{ $ee->result->win_loss === 'win' ? 'text-success-600 dark:text-success-400' : ($ee->result->win_loss === 'loss' ? 'text-danger-600 dark:text-danger-400' : 'text-gray-500') }}">{{ ucfirst($ee->result->win_loss) }}</span>
                                                                </div>
                                                            @endif
                                                        @endif
                                                        @if ($ee->division)
                                                            <div class="flex items-center justify-center px-2.5 bg-gray-100 dark:bg-gray-700 border-r border-gray-200 dark:border-gray-600 shrink-0">
                                                                <span class="font-mono font-bold text-gray-600 dark:text-gray-300">{{ $ee->division->code }}</span>
                                                            </div>
                                                        @endif
                                                        <div class="flex flex-col px-2.5 py-1.5">
                                                            <span class="font-medium text-gray-700 dark:text-gray-300 leading-snug">
                                                                {{ $ee->competitionEvent->name }}@if ($ee->competitionEvent->requires_partner) <span class="ml-1 {{ $ee->yakusuko_complete ? 'text-success-500' : 'text-warning-500' }}">{{ $ee->yakusuko_complete ? '✓' : '?' }} Partner</span>@endif
                                                            </span>
                                                            @if ($ee->division)
                                                                <span class="text-[0.65rem] text-gray-400 dark:text-gray-500 mt-0.5 leading-snug">{{ $ee->division->label }}</span>
                                                            @else
                                                                <span class="text-[0.65rem] italic text-gray-400 dark:text-gray-500 mt-0.5">TBC</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                @else
                                    <div class="mt-2 sm:ml-11 flex flex-wrap gap-1.5">
                                        @forelse ($enrolment->activeEvents as $ee)
                                            <div class="inline-flex items-stretch rounded-md border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-900 text-xs shadow-sm overflow-hidden">
                                                @if ($ee->result)
                                                    @if ($ee->result->disqualified)
                                                        <div class="flex items-center px-2 bg-danger-50 dark:bg-danger-900/20 border-r border-gray-200 dark:border-slate-600 shrink-0">
                                                            <span class="font-semibold text-danger-600 dark:text-danger-400">DQ</span>
                                                        </div>
                                                    @elseif ($ee->result->placement)
                                                        @php $placeEmoji = match($ee->result->placement) { 1 => '🥇', 2 => '🥈', 3 => '🥉', default => $ee->result->placement . 'th' }; @endphp
                                                        <div class="flex items-center px-2 bg-gray-50 dark:bg-slate-700/50 border-r border-gray-200 dark:border-slate-600 shrink-0">
                                                            <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $placeEmoji }}</span>
                                                        </div>
                                                    @elseif ($ee->result->win_loss)
                                                        <div class="flex items-center px-2 bg-gray-50 dark:bg-slate-700/50 border-r border-gray-200 dark:border-slate-600 shrink-0">
                                                            <span class="font-semibold {{ $ee->result->win_loss === 'win' ? 'text-success-600 dark:text-success-400' : ($ee->result->win_loss === 'loss' ? 'text-danger-600 dark:text-danger-400' : 'text-gray-500') }}">{{ ucfirst($ee->result->win_loss) }}</span>
                                                        </div>
                                                    @endif
                                                @endif
                                                @if ($ee->division)
                                                    <div class="flex items-center justify-center px-2.5 bg-gray-100 dark:bg-gray-700 border-r border-gray-200 dark:border-gray-600 shrink-0">
                                                        <span class="font-mono font-bold text-gray-600 dark:text-gray-300">{{ $ee->division->code }}</span>
                                                    </div>
                                                @endif
                                                <div class="flex flex-col px-2.5 py-1.5">
                                                    <span class="font-medium text-gray-700 dark:text-gray-300 leading-snug">
                                                        {{ $ee->competitionEvent->name }}@if ($ee->competitionEvent->requires_partner) <span class="ml-1 {{ $ee->yakusuko_complete ? 'text-success-500' : 'text-warning-500' }}">{{ $ee->yakusuko_complete ? '✓' : '?' }} Partner</span>@endif
                                                    </span>
                                                    @if ($ee->division)
                                                        <span class="text-[0.65rem] text-gray-400 dark:text-gray-500 mt-0.5 leading-snug">{{ $ee->division->label }}</span>
                                                    @else
                                                        <span class="text-[0.65rem] italic text-gray-400 dark:text-gray-500 mt-0.5">TBC</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @empty
                                            <p class="text-xs text-gray-400">No events in this enrolment.</p>
                                        @endforelse
                                    </div>
                                @endif

                                {{-- AI summary --}}
                                @if ($enrolment->ai_summary)
                                    <div class="sm:ml-11 mt-2 flex items-start gap-1.5 rounded-lg border border-primary-200/70 dark:border-primary-600/30 bg-primary-50/30 dark:bg-primary-900/10 px-2.5 py-2" style="box-shadow:0 0 12px 4px rgba(99,102,241,0.25)">
                                        <x-heroicon-m-sparkles class="w-3.5 h-3.5 text-primary-400 dark:text-primary-500 shrink-0 mt-0.5" />
                                        <p class="text-xs italic text-gray-500 dark:text-gray-400 whitespace-pre-line">{!! nl2br(e($enrolment->ai_summary)) !!}</p>
                                    </div>
                                @elseif ($this->isSummaryGenerating($competition->id))
                                    <div wire:poll.10s class="sm:ml-11 mt-2 flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500">
                                        <svg class="w-3.5 h-3.5 animate-spin text-primary-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg>
                                        <span class="italic">Generating your summary…</span>
                                    </div>
                                @endif

                                {{-- QR modal --}}
                                @if ($canShowQr)
                                    <div x-show="qrOpen" x-cloak x-transition.opacity
                                         x-on:click="qrOpen = false"
                                         class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 sm:p-4">
                                        <div class="flex flex-col items-center justify-center gap-4
                                                    w-full h-full bg-white dark:bg-slate-900
                                                    sm:w-64 sm:h-auto sm:rounded-2xl sm:p-6 sm:shadow-xl">
                                            <p class="font-semibold text-gray-900 dark:text-white">{{ $profile->full_name }}</p>
                                            <p class="text-xs text-gray-500 -mt-2">{{ $competition->name }}</p>
                                            <div class="flex flex-col items-center gap-1 text-center">
                                                <span class="text-xs font-semibold uppercase tracking-widest text-primary-600 dark:text-primary-400">{{ $canShowPayQr ? 'Payment QR code' : 'Check-in QR code' }}</span>
                                                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $canShowPayQr ? 'Show this to your instructor to pay' : 'Show this to the official at the door' }}</span>
                                            </div>
                                            <x-qr-code :value="$canShowPayQr ? \App\Filament\Portal\Pages\AcceptPaymentPage::getUrl(['code' => $enrolment->checkin_code]) : (url('/manage/check-in') . '?competition_id=' . $competition->id . '&code=' . $enrolment->checkin_code)" :size="240" />
                                            <span class="text-2xl font-mono font-bold tracking-widest text-gray-700 dark:text-gray-300">{{ $enrolment->checkin_code }}</span>
                                            <p class="text-xs text-gray-400 dark:text-gray-500">Tap anywhere to close</p>
                                        </div>
                                    </div>
                                @endif
                            @endif

                            {{-- Withdrawal confirmation modal --}}
                            @if ($enrolment && $this->withdrawingId === $enrolment->id)
                                @php
                                    $isPaidW      = $enrolment->cart?->isPaid();
                                    $withinCutoff = $enrolment->isWithinCancellationCutoff();
                                @endphp
                                <div class="mt-3 rounded-lg border border-danger-200 dark:border-danger-700 bg-danger-50 dark:bg-danger-950 p-4">
                                    <p class="text-sm font-semibold text-danger-800 dark:text-danger-200 mb-1">
                                        Withdraw {{ $profile->full_name }} from {{ $competition->name }}?
                                    </p>
                                    @if ($isPaidW && $withinCutoff)
                                        <p class="text-xs text-danger-700 dark:text-danger-300 mb-3">
                                            A refund of {{ tenant_money($enrolment->fee_calculated) }} will be created and the organisation will contact you to arrange the return.
                                        </p>
                                    @elseif (! $isPaidW)
                                        <p class="text-xs text-danger-700 dark:text-danger-300 mb-3">This action cannot be undone.</p>
                                    @endif
                                    <div class="flex items-center gap-3">
                                        <x-filament::button color="danger" size="sm" wire:click="confirmWithdraw">Confirm withdrawal</x-filament::button>
                                        <button wire:click="cancelWithdraw" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">Cancel</button>
                                    </div>
                                </div>
                            @endif

                        </div>{{-- /profile sub-card --}}
                    @endforeach
                </div>
                @endif{{-- /advertise guard --}}

            </div>{{-- /competition card --}}
        @endforeach
        </div>
    @endif

    {{-- Manage profiles link --}}
    <div class="mt-4 text-right">
        <x-filament::button href="{{ route('filament.portal.pages.profiles') }}" tag="a" color="gray" size="sm" icon="heroicon-o-users">
            Manage profiles
        </x-filament::button>
    </div>

</x-filament-panels::page>
