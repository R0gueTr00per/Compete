<x-filament-panels::page>
    @php
        $profile               = $this->getProfile();
        $enrolments            = $this->getEnrolments();
        $instructorDojos       = $this->getInstructorDojos();
        $instructorCompetitions = $instructorDojos->isNotEmpty() ? $this->getInstructorCompetitions() : collect();
    @endphp

    {{-- Profile summary --}}
    <x-filament::section>
        <x-slot name="heading">My Profile</x-slot>
        <x-slot name="headerEnd">
            <x-filament::button
                href="{{ route('filament.portal.pages.profile') }}"
                tag="a"
                color="gray"
                size="sm"
                icon="heroicon-o-pencil-square">
                Edit profile
            </x-filament::button>
        </x-slot>

        @if (! $profile || ! $profile->profile_complete)
            <div class="flex items-center gap-3 p-4 bg-warning-50 dark:bg-warning-900/20 rounded-lg border border-warning-200 dark:border-warning-800">
                <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-warning-600 shrink-0" />
                <div>
                    <p class="text-sm font-medium text-warning-800 dark:text-warning-200">Profile incomplete</p>
                    <p class="text-xs text-warning-700 dark:text-warning-300 mt-0.5">
                        You must complete your profile before you can enrol in competitions.
                    </p>
                </div>
                <x-filament::button href="{{ route('filament.portal.pages.profile') }}" tag="a" color="warning" size="sm" class="ml-auto shrink-0">
                    Complete now
                </x-filament::button>
            </div>
        @else
            <dl class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-3 text-sm">
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Name</dt>
                    <dd class="mt-0.5 font-medium text-gray-900 dark:text-white">{{ $profile->first_name }} {{ $profile->surname }}</dd>
                </div>
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
            <p class="mt-3 text-xs text-gray-400">Dojo, rank, and weight are entered when you enrol in each competition.</p>
        @endif
    </x-filament::section>

    {{-- Enrolments & results --}}
    <div class="mt-6">
        @if ($enrolments->isEmpty())
            <x-filament::section>
                <p class="text-center text-gray-500 py-8">You have not enrolled in any competitions yet.</p>
                <div class="flex justify-center mt-2">
                    <x-filament::button href="{{ route('filament.portal.pages.enrol') }}" tag="a">
                        Enrol now
                    </x-filament::button>
                </div>
            </x-filament::section>
        @else
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">My Enrolments</h2>
                <x-filament::button href="{{ route('filament.portal.pages.enrol') }}" tag="a" color="primary" size="sm" icon="heroicon-o-plus">
                    Enrol in another competition
                </x-filament::button>
            </div>

            @foreach ($enrolments as $enrolment)
                @php
                    $isDraft      = $enrolment->competition->status === 'draft';
                    $showSchedule = ! $isDraft;
                @endphp
                <x-filament::section class="mb-4">
                    <x-slot name="heading">
                        {{ $enrolment->competition->name }}
                        @if ($isDraft)
                            <span class="ml-2 inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs font-medium text-gray-600 dark:text-gray-300">Draft</span>
                        @endif
                    </x-slot>
                    @if ($showSchedule)
                        <x-slot name="headerEnd">
                            <x-filament::button
                                href="{{ route('filament.portal.pages.schedule-page') }}?competition_id={{ $enrolment->competition->id }}"
                                tag="a"
                                color="warning"
                                size="sm"
                                icon="heroicon-o-calendar-days">
                                View Schedule
                            </x-filament::button>
                        </x-slot>
                    @endif
                    <x-slot name="description">
                        {{ $enrolment->competition->competition_date->format('d M Y') }}
                        @if ($enrolment->competition->location_name)
                            &mdash; {{ $enrolment->competition->location_name }}
                        @endif
                        &bull; Fee: <strong>${{ number_format($enrolment->fee_calculated, 2) }}</strong>
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
                    </x-slot>

                    @if ($isDraft)
                        <div class="mb-3 flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-3 py-2 text-sm text-gray-600 dark:text-gray-400">
                            <x-heroicon-o-lock-closed class="w-4 h-4 shrink-0" />
                            This competition is back in Draft — enrolments are currently locked.
                        </div>
                    @endif

                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($enrolment->activeEvents as $ee)
                            <div class="py-3 flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-medium text-sm text-gray-900 dark:text-white">
                                        {{ $ee->competitionEvent->event_code }}
                                        — {{ $ee->competitionEvent->name }}
                                        @if ($ee->competitionEvent->location_label)
                                            <span class="text-gray-400 font-normal">({{ $ee->competitionEvent->location_label }})</span>
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
                                        @if ($ee->result->total_score !== null)
                                            <p class="text-gray-500 text-xs">Score: {{ number_format($ee->result->total_score, 1) }}</p>
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
                        @endforeach
                    </div>
                    <p class="mt-3 text-xs text-gray-400 italic">Organisers reserve the right to merge or cancel any event on the day.</p>
                </x-filament::section>
            @endforeach
        @endif
    </div>

    {{-- Instructor view --}}
    @if ($instructorDojos->isNotEmpty())
        <div class="mt-6">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-3">
                My Dojo{{ $instructorDojos->count() !== 1 ? 's' : '' }}
            </h2>

            @foreach ($instructorDojos as $dojo)
                @php
                    $dojoCompetitions = $instructorCompetitions->filter(
                        fn ($c) => $c->enrolments->where('dojo_name', $dojo->name)->isNotEmpty()
                    );
                @endphp
                <x-filament::section class="mb-4">
                    <x-slot name="heading">{{ $dojo->name }}</x-slot>

                    @if ($dojoCompetitions->isEmpty())
                        <p class="text-sm text-gray-500 py-2">No active competitions with enrolments from this dojo.</p>
                    @else
                        @foreach ($dojoCompetitions as $competition)
                            @php
                                $dojoEnrolments = $competition->enrolments->where('dojo_name', $dojo->name)->sortBy(
                                    fn ($e) => $e->competitor?->competitorProfile?->surname ?? ''
                                );
                            @endphp
                            <div class="mb-4">
                                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                    {{ $competition->name }}
                                    &mdash; {{ $competition->competition_date->format('d M Y') }}
                                </p>
                                <div class="divide-y divide-gray-100 dark:divide-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                                    @foreach ($dojoEnrolments as $enrolment)
                                        @php
                                            $profile = $enrolment->competitor?->competitorProfile;
                                            $name = $profile
                                                ? trim($profile->first_name . ' ' . $profile->surname)
                                                : $enrolment->competitor?->email ?? '—';
                                        @endphp
                                        <div class="px-4 py-3">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $name }}</p>
                                            <div class="mt-1 flex flex-wrap gap-x-4 gap-y-0.5">
                                                @foreach ($enrolment->activeEvents as $ee)
                                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                                        {{ $ee->competitionEvent->event_code }} — {{ $ee->competitionEvent->name }}
                                                        @if ($ee->division)
                                                            <span class="text-gray-400">({{ $ee->division->full_label }})</span>
                                                        @endif
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @endif
                </x-filament::section>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
