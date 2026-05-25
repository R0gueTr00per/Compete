<x-filament-panels::page>
    @php
        $profiles           = $this->getProfiles();
        $activeCompetitions = $this->getActiveCompetitions();
    @endphp

    @forelse ($profiles as $profile)
        @php $enrolments = $this->getEnrolmentsForProfile($profile); @endphp

        <x-filament::section class="mb-6">
            <x-slot name="heading">
                {{ $profile->full_name }}
                @if ($profile->profile_type === 'child')
                    <span class="ml-2 text-xs font-normal text-gray-400">(Child)</span>
                @endif
                @unless ($profile->is_active)
                    <span class="ml-2 text-xs font-medium text-warning-600">(Inactive)</span>
                @endunless
            </x-slot>
            <x-slot name="headerEnd">
                <x-filament::button
                    href="{{ route('filament.portal.pages.profile') }}{{ $profile->profile_type === 'child' ? '?profileId=' . $profile->id : '' }}"
                    tag="a"
                    color="gray"
                    size="sm"
                    icon="heroicon-o-pencil-square">
                    Edit profile
                </x-filament::button>
            </x-slot>

            @if (! $profile->profile_complete)
                <div class="flex items-center gap-3 p-4 bg-warning-50 dark:bg-warning-900/20 rounded-lg border border-warning-200 dark:border-warning-800">
                    <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-warning-600 shrink-0" />
                    <div>
                        <p class="text-sm font-medium text-warning-800 dark:text-warning-200">Profile incomplete</p>
                        <p class="text-xs text-warning-700 dark:text-warning-300 mt-0.5">
                            Complete this profile before enrolling in competitions.
                        </p>
                    </div>
                    <x-filament::button
                        href="{{ route('filament.portal.pages.profile') }}{{ $profile->profile_type === 'child' ? '?profileId=' . $profile->id : '' }}"
                        tag="a" color="warning" size="sm" class="ml-auto shrink-0">
                        Complete now
                    </x-filament::button>
                </div>
            @else
                {{-- Profile details --}}
                <div class="flex gap-6 items-start mb-5">
                    <div class="shrink-0">
                        @if ($profile->profile_photo)
                            <img src="{{ asset('storage/' . $profile->profile_photo) }}"
                                 alt="Profile photo"
                                 class="w-16 h-20 rounded-lg object-cover border-2 border-gray-200 dark:border-gray-600" />
                        @else
                            <div class="w-16 h-20 rounded-lg bg-gray-100 dark:bg-gray-700 border-2 border-gray-200 dark:border-gray-600 flex items-center justify-center">
                                <x-heroicon-o-user class="w-8 h-10 text-gray-400 dark:text-gray-500" />
                            </div>
                        @endif
                    </div>

                    <dl class="flex-1 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-3 text-sm">
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Date of birth</dt>
                            <dd class="mt-0.5 text-gray-700 dark:text-gray-300">{{ $profile->date_of_birth->format('d M Y') }} (age {{ $profile->age }})</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Gender</dt>
                            <dd class="mt-0.5 text-gray-700 dark:text-gray-300">{{ $profile->gender === 'M' ? 'Male' : 'Female' }}</dd>
                        </div>
                        @if ($profile->phone)
                            <div>
                                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Phone</dt>
                                <dd class="mt-0.5 text-gray-700 dark:text-gray-300">{{ $profile->phone }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                {{-- Active competitions --}}
                @if ($activeCompetitions->isEmpty())
                    <p class="text-center text-gray-500 py-4">No active competitions at this time.</p>
                @else
                    <div class="space-y-3">
                        @foreach ($activeCompetitions as $competition)
                            @php
                                $enrolment      = $enrolments->get($competition->id);
                                $isEnrolled     = $enrolment !== null;
                                $enrolmentOpen  = $competition->isEnrolmentOpen();
                                $showSchedule   = in_array($competition->status, ['check_in', 'running', 'complete']);

                                $statusLabel = match($competition->status) {
                                    'open'     => 'Open',
                                    'closed'   => 'Enrolments closed',
                                    'check_in' => 'Check-in',
                                    'running'  => 'In progress',
                                    default    => ucfirst($competition->status),
                                };
                                $statusClass = match($competition->status) {
                                    'open'     => 'bg-success-100 dark:bg-success-900/30 text-success-700 dark:text-success-400',
                                    'closed'   => 'bg-warning-100 dark:bg-warning-900/30 text-warning-700 dark:text-warning-400',
                                    'check_in' => 'bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-400',
                                    'running'  => 'bg-danger-100 dark:bg-danger-900/30 text-danger-700 dark:text-danger-400',
                                    default    => 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300',
                                };
                            @endphp

                            <div class="rounded-lg border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800 overflow-hidden">
                                {{-- Competition header --}}
                                <div class="px-4 py-3 border-b border-gray-200 dark:border-slate-700 flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                            {{ $competition->name }}
                                        </p>
                                        <p class="text-xs text-gray-500 mt-0.5">
                                            {{ $competition->competition_date->format('d M Y') }}
                                            @if ($competition->location_name)
                                                &mdash; {{ $competition->location_name }}
                                            @endif
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        @if ($isEnrolled && $showSchedule)
                                            <x-filament::button
                                                href="{{ route('filament.portal.pages.schedule-page') }}?competition_id={{ $competition->id }}"
                                                tag="a" color="warning" size="sm" icon="heroicon-o-calendar-days">
                                                Schedule
                                            </x-filament::button>
                                        @endif
                                        @if (! $isEnrolled || ! $enrolmentOpen)
                                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusClass }}">
                                                {{ $statusLabel }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                @if ($isEnrolled)
                                    {{-- Enrolment summary row --}}
                                    <div class="px-4 py-2 border-b border-gray-100 dark:border-slate-700 text-xs text-gray-500">
                                        Fee: <strong class="text-gray-700 dark:text-gray-300">${{ number_format($enrolment->fee_calculated, 2) }}</strong>
                                        @if ($enrolment->is_late)
                                            <span class="text-warning-600">(includes late surcharge)</span>
                                        @endif
                                        @if ($enrolment->display_rank !== '—')
                                            &bull; Rank: {{ $enrolment->display_rank }}
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

                                    {{-- Enrolled events --}}
                                    <div class="divide-y divide-gray-100 dark:divide-slate-700 px-4">
                                        @forelse ($enrolment->activeEvents as $ee)
                                            <div class="py-3.5 flex items-start justify-between gap-4 leading-relaxed">
                                                <div>
                                                    <p class="font-medium text-sm text-gray-900 dark:text-white">
                                                        {{ $ee->competitionEvent->name }}
                                                        @if ($ee->division?->location_label)
                                                            <span class="text-gray-400 font-normal">({{ $ee->division->location_label }})</span>
                                                        @endif
                                                    </p>
                                                    @if ($ee->division)
                                                        <p class="text-xs text-gray-500 mt-0.5">{{ $ee->division->full_label }}</p>
                                                    @else
                                                        <p class="text-xs text-gray-400 mt-0.5">Division to be confirmed</p>
                                                    @endif
                                                    @if ($ee->competitionEvent->requires_partner)
                                                        <p class="text-xs mt-0.5 {{ $ee->yakusuko_complete ? 'text-success-600' : 'text-warning-600' }}">
                                                            Partner: {{ $ee->yakusuko_complete ? 'Confirmed' : 'Pending partner enrolment' }}
                                                        </p>
                                                    @endif
                                                </div>

                                                <div class="text-right text-sm shrink-0">
                                                    @if ($ee->result)
                                                        @if ($ee->result->disqualified)
                                                            <span class="text-danger-600 font-semibold text-xs">DQ</span>
                                                        @elseif ($ee->result->placement)
                                                            <span class="font-bold text-primary-600">
                                                                @switch($ee->result->placement)
                                                                    @case(1) 🥇 1st @break
                                                                    @case(2) 🥈 2nd @break
                                                                    @case(3) 🥉 3rd @break
                                                                    @default {{ $ee->result->placement }}th
                                                                @endswitch
                                                            </span>
                                                        @endif
                                                        @if ($ee->result->win_loss)
                                                            <p class="text-xs {{ $ee->result->win_loss === 'win' ? 'text-success-600' : ($ee->result->win_loss === 'loss' ? 'text-danger-600' : 'text-gray-500') }}">
                                                                {{ ucfirst($ee->result->win_loss) }}
                                                            </p>
                                                        @endif
                                                        @if (! $ee->result->placement && ! $ee->result->win_loss && ! $ee->result->total_score && ! $ee->result->disqualified)
                                                            <span class="text-gray-400 text-xs">Result pending</span>
                                                        @endif
                                                    @else
                                                        <span class="text-gray-400 text-xs">Result pending</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @empty
                                            <p class="py-3 text-xs text-gray-400">No events in this enrolment.</p>
                                        @endforelse
                                    </div>
                                    <p class="px-4 py-2 text-xs text-gray-400 italic border-t border-gray-100 dark:border-slate-700">Organisers reserve the right to merge or cancel any event on the day.</p>

                                @elseif ($enrolmentOpen && $profile->is_active)
                                    <div class="px-4 py-4 flex justify-center">
                                        <x-filament::button
                                            href="{{ route('filament.portal.pages.enrol') }}?profile_id={{ $profile->id }}&competition_id={{ $competition->id }}"
                                            tag="a" color="primary" size="sm" icon="heroicon-o-plus">
                                            Enrol now
                                        </x-filament::button>
                                    </div>

                                @else
                                    <div class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                        @if ($competition->status === 'closed')
                                            Enrolments are now closed for this competition.
                                        @elseif ($competition->status === 'check_in')
                                            This competition is in the check-in phase.
                                        @elseif ($competition->status === 'running')
                                            This competition is currently underway.
                                        @else
                                            Enrolments are not currently open.
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif
        </x-filament::section>
    @empty
        <x-filament::section>
            <p class="text-center text-gray-500 py-8">Complete your profile to get started.</p>
            <div class="flex justify-center mt-2">
                <x-filament::button href="{{ route('filament.portal.pages.profile') }}" tag="a">
                    Complete profile
                </x-filament::button>
            </div>
        </x-filament::section>
    @endforelse

    {{-- Manage child profiles link --}}
    <div class="mb-6 text-right">
        <x-filament::button href="{{ route('filament.portal.pages.profiles') }}" tag="a" color="gray" size="sm" icon="heroicon-o-users">
            Manage profiles
        </x-filament::button>
    </div>

</x-filament-panels::page>
