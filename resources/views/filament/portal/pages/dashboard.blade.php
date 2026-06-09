<x-filament-panels::page>
    @php
        $profiles           = $this->getProfiles();
        $activeCompetitions = $this->getActiveCompetitions();
        $cartKeys           = $this->getCartDraftKeys();
        $allEnrolments      = $this->getAllEnrolments();

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
            <p class="text-center text-gray-500 py-8">No active competitions at this time.</p>
        </x-filament::section>
    @else
        <div class="space-y-4">
        @foreach ($activeCompetitions as $competition)
            @php
                $statusLabel = match($competition->status) {
                    'open'              => 'Open',
                    'enrolments_closed' => 'Registrations Closed',
                    'check_in'          => 'Check-in',
                    'running'           => 'In Progress',
                    'complete'          => 'Finished',
                    default             => ucfirst($competition->status),
                };

                $statusBadgeClass = match($competition->status) {
                    'open'              => 'bg-green-100/60 text-green-700 border-green-200/60 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700/40',
                    'enrolments_closed' => 'bg-gray-100/60 text-gray-500 border-gray-200/60 dark:bg-gray-800/40 dark:text-gray-400 dark:border-gray-700/40',
                    'check_in'          => 'bg-amber-100/60 text-amber-700 border-amber-200/60 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-700/40',
                    'running'           => 'bg-blue-100/60 text-blue-700 border-blue-200/60 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-700/40',
                    default             => 'bg-gray-100/60 text-gray-500 border-gray-200/60 dark:bg-gray-800/40 dark:text-gray-400 dark:border-gray-700/40',
                };

                $statusIcon = match($competition->status) {
                    'open'              => 'heroicon-m-lock-open',
                    'enrolments_closed' => 'heroicon-m-lock-closed',
                    'check_in'         => 'heroicon-m-qr-code',
                    'running'           => 'heroicon-m-play',
                    'complete'          => 'heroicon-m-check',
                    default             => null,
                };

                $borderClass = match($competition->status) {
                    'open'              => 'border-l-green-500',
                    'check_in'          => 'border-l-amber-400',
                    'running'           => 'border-l-blue-500',
                    'enrolments_closed' => 'border-l-gray-400 dark:border-l-gray-600',
                    default             => 'border-l-gray-300 dark:border-l-gray-700',
                };

                $showSchedule   = in_array($competition->status, ['check_in', 'running', 'complete']);
                $enrolmentOpen  = $competition->isEnrolmentOpen();
            @endphp

            <div class="rounded-lg border border-l-4 {{ $borderClass }} border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm">

                {{-- Competition header --}}
                <div class="px-4 py-3 border-b border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-900 flex items-center gap-3">

                    {{-- Date calendar widget --}}
                    <div class="flex-shrink-0 flex flex-col items-center justify-center w-11 h-11 rounded-lg bg-primary-500 dark:bg-primary-600 text-white text-center leading-none select-none">
                        <span class="text-[0.6rem] font-bold uppercase tracking-wide opacity-90">
                            {{ $competition->competition_date->format('M') }}
                        </span>
                        <span class="text-lg font-bold leading-none mt-0.5">
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
                                {{ $competition->location_name }}
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
                    </div>

                </div>

                {{-- Portal messages from organiser --}}
                @if ($competition->portalMessages->isNotEmpty())
                    <div class="px-4 py-2 space-y-1 border-b border-gray-100 dark:border-slate-700 bg-primary-50/50 dark:bg-primary-900/10">
                        @foreach ($competition->portalMessages as $msg)
                            <p class="text-sm text-primary-900 dark:text-primary-100">{{ $msg->message }}</p>
                        @endforeach
                    </div>
                @endif

                {{-- Profile rows --}}
                <div class="divide-y divide-gray-100 dark:divide-slate-700">
                    @foreach ($profiles as $profile)
                        @php
                            $enrolment     = $allEnrolments->get($profile->id)?->get($competition->id);
                            $isEnrolled    = $enrolment !== null;
                            $inCart        = in_array("{$profile->id}:{$competition->id}", $cartKeys);
                            $canRegister   = ! $isEnrolled && ! $inCart && $enrolmentOpen && $profile->is_active && $profile->profile_complete;
                        @endphp

                        @if (! $isEnrolled && ! $inCart && ! $canRegister) @continue @endif
                        <div x-data="{ qrOpen: false }" class="px-4 py-3">

                            {{-- Profile row header --}}
                            <div class="flex items-center gap-3 mb-2">
                                {{-- Avatar --}}
                                <div class="flex-shrink-0">
                                    @if ($profile->profile_photo)
                                        <img src="{{ asset('storage/' . $profile->profile_photo) }}"
                                             alt="{{ $profile->full_name }}"
                                             class="w-8 h-8 rounded-full object-cover border border-gray-200 dark:border-gray-600" />
                                    @else
                                        <div class="w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 flex items-center justify-center">
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
                                    @if ($isEnrolled && $enrolment->status === 'checked_in')
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-xs font-medium border bg-green-100/60 text-green-700 border-green-200/60 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700/40 flex-shrink-0">
                                            <x-heroicon-m-check class="w-3 h-3" />
                                            Checked in
                                        </span>
                                    @endif
                                </div>

                                {{-- Schedule button (enrolled + schedule-visible phase) --}}
                                @if ($isEnrolled && $showSchedule)
                                    <x-filament::button
                                        href="{{ route('filament.portal.pages.schedule-page') }}?competition_id={{ $competition->id }}&profile_id={{ $profile->id }}"
                                        tag="a" color="gray" size="sm" icon="heroicon-o-calendar-days"
                                        class="flex-shrink-0">
                                        Schedule
                                    </x-filament::button>
                                @endif

                                {{-- QR button (check-in / running phases) --}}
                                @if ($isEnrolled && in_array($competition->status, ['check_in', 'running']) && $enrolment->checkin_code)
                                    <button x-on:click="qrOpen = true"
                                        class="flex-shrink-0 hidden sm:flex items-center justify-center w-8 h-8 rounded-lg border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-700 hover:border-primary-400 dark:hover:border-primary-500 transition-colors overflow-hidden p-0.5">
                                        <x-qr-code :value="url('/manage/check-in') . '?competition_id=' . $competition->id . '&code=' . $enrolment->checkin_code" :size="24" />
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
                                    <x-filament::button
                                        href="{{ route('filament.portal.pages.enrol') }}?profile_id={{ $profile->id }}&competition_id={{ $competition->id }}&redirect_to=dashboard"
                                        tag="a" color="primary" size="sm" icon="heroicon-o-plus"
                                        class="flex-shrink-0">
                                        Register
                                    </x-filament::button>
                                @endif
                            </div>

                            @if ($isEnrolled)
                                {{-- Mobile QR: front and centre, tap to enlarge --}}
                                @if (in_array($competition->status, ['check_in', 'running']) && $enrolment->checkin_code)
                                    <div x-on:click="qrOpen = true"
                                         class="qr-reveal sm:hidden flex flex-col items-center gap-1 py-3 border-b border-gray-100 dark:border-slate-700 cursor-pointer active:opacity-70 transition-opacity">
                                        <p class="text-xs text-gray-400 dark:text-gray-500">Check-in QR <span class="text-gray-300 dark:text-gray-600">&middot; tap to enlarge</span></p>
                                        <x-qr-code :value="url('/manage/check-in') . '?competition_id=' . $competition->id . '&code=' . $enrolment->checkin_code" :size="128" />
                                        <span class="text-xs font-mono font-semibold tracking-widest text-gray-600 dark:text-gray-400">{{ $enrolment->checkin_code }}</span>
                                    </div>
                                @endif

                                {{-- Enrolment summary (fee, rank, weight, dojo) --}}
                                @php
                                    $displayFee = $enrolment->fee_calculated + (float) ($enrolment->cart?->platform_fee_rate ?? app('tenant')?->platform_fee ?? 0);
                                @endphp
                                <div class="ml-10 text-xs text-gray-500 dark:text-gray-400 mb-2">
                                    Fee: <strong class="text-gray-700 dark:text-gray-300">{{ tenant_money($displayFee) }}</strong>
                                    @if ($enrolment->is_late)
                                        <span class="text-warning-600">(late)</span>
                                    @endif
                                    @if ($enrolment->payment_status === 'received')
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">Paid</span>
                                    @else
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300">Unpaid</span>
                                    @endif
                                    @if ($enrolment->display_rank !== '—')
                                        &bull; {{ $enrolment->display_rank }}
                                    @endif
                                    @if ($enrolment->weight_kg)
                                        &bull; {{ $enrolment->weight_kg }} kg
                                    @endif
                                    @if ($enrolment->dojo_name)
                                        &bull; {{ $enrolment->dojo_name }}
                                    @elseif ($enrolment->guest_style)
                                        &bull; {{ $enrolment->guest_style }} (guest)
                                    @endif
                                </div>

                                {{-- Events list --}}
                                <div class="ml-10 space-y-1">
                                    @forelse ($enrolment->activeEvents as $ee)
                                        <div class="min-w-0 sm:flex sm:items-center sm:gap-2">
                                            <div class="flex items-center gap-2 min-w-0 sm:flex-1">
                                                <span class="text-sm text-gray-800 dark:text-gray-200 flex-1 min-w-0 sm:truncate">{{ $ee->competitionEvent->name }}</span>
                                                @if ($ee->result)
                                                    @if ($ee->result->disqualified)
                                                        <span class="text-danger-600 font-semibold text-xs shrink-0">DQ</span>
                                                    @elseif ($ee->result->placement)
                                                        <span class="font-bold text-xs text-primary-600 shrink-0">
                                                            @switch($ee->result->placement)
                                                                @case(1) 🥇 1st @break
                                                                @case(2) 🥈 2nd @break
                                                                @case(3) 🥉 3rd @break
                                                                @default {{ $ee->result->placement }}th
                                                            @endswitch
                                                        </span>
                                                    @elseif ($ee->result->win_loss)
                                                        <span class="text-xs shrink-0 {{ $ee->result->win_loss === 'win' ? 'text-success-600' : ($ee->result->win_loss === 'loss' ? 'text-danger-600' : 'text-gray-500') }}">{{ ucfirst($ee->result->win_loss) }}</span>
                                                    @endif
                                                @endif
                                                @if ($ee->competitionEvent->requires_partner)
                                                    <span class="text-xs shrink-0 {{ $ee->yakusuko_complete ? 'text-success-600' : 'text-warning-600' }}">Partner: {{ $ee->yakusuko_complete ? 'Confirmed' : 'Pending' }}</span>
                                                @endif
                                            </div>
                                            <div class="text-xs text-gray-400 dark:text-gray-500 mt-0.5 sm:mt-0 sm:shrink-0">
                                                @if ($ee->division)
                                                    {{ $ee->division->code }} &mdash; {{ $ee->division->label }}
                                                @else
                                                    TBC
                                                @endif
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-xs text-gray-400">No events in this enrolment.</p>
                                    @endforelse
                                </div>

                                {{-- QR modal --}}
                                @if (in_array($competition->status, ['check_in', 'running']) && $enrolment->checkin_code)
                                    <div x-show="qrOpen" x-cloak x-transition.opacity
                                         x-on:click="qrOpen = false"
                                         class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 sm:p-4">
                                        <div class="flex flex-col items-center justify-center gap-4
                                                    w-full h-full bg-white dark:bg-slate-900
                                                    sm:w-64 sm:h-auto sm:rounded-2xl sm:p-6 sm:shadow-xl">
                                            <p class="font-semibold text-gray-900 dark:text-white">{{ $profile->full_name }}</p>
                                            <p class="text-xs text-gray-500 -mt-2">{{ $competition->name }}</p>
                                            <div class="flex flex-col items-center gap-1 text-center">
                                                <span class="text-xs font-semibold uppercase tracking-widest text-primary-600 dark:text-primary-400">Check-in QR code</span>
                                                <span class="text-xs text-gray-400 dark:text-gray-500">Show this to the official at the door</span>
                                            </div>
                                            <x-qr-code :value="url('/manage/check-in') . '?competition_id=' . $competition->id . '&code=' . $enrolment->checkin_code" :size="240" />
                                            <span class="text-2xl font-mono font-bold tracking-widest text-gray-700 dark:text-gray-300">{{ $enrolment->checkin_code }}</span>
                                            <p class="text-xs text-gray-400 dark:text-gray-500">Tap anywhere to close</p>
                                        </div>
                                    </div>
                                @endif
                            @endif

                        </div>{{-- /profile row --}}
                    @endforeach
                </div>

                @if ($allEnrolments->contains(fn ($byComp) => $byComp->has($competition->id)))
                    <p class="px-4 py-2 text-xs text-gray-400 italic border-t border-gray-100 dark:border-slate-700">Organisers reserve the right to merge or cancel any event on the day.</p>
                @endif

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
