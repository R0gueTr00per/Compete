<x-filament-panels::page>
    @php $enrolments = $this->getEnrolments(); @endphp

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
        @foreach ($enrolments as $enrolment)
            <x-filament::section class="mb-6">
                <x-slot name="heading">
                    {{ $enrolment->competition->name }}
                </x-slot>

                <x-slot name="description">
                    {{ $enrolment->competition->competition_date->format('d M Y') }}
                    @if ($enrolment->competition->location_name)
                        &mdash; {{ $enrolment->competition->location_name }}
                    @endif
                    &nbsp;&bull;&nbsp;
                    Fee: <strong>${{ number_format($enrolment->fee_calculated, 2) }}</strong>
                    @if ($enrolment->is_late)
                        <span class="text-warning-600">(includes late surcharge)</span>
                    @endif
                </x-slot>

                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($enrolment->activeEvents as $ee)
                        @php
                            $coCompetitors = $ee->division
                                ? $ee->division->activeEnrolmentEvents
                                    ->where('enrolment.competitor_id', '!=', auth()->id())
                                    ->sortBy(fn ($other) => $other->enrolment->competitor?->competitorProfile?->surname)
                                : collect();
                        @endphp
                        <div class="py-3">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-medium text-sm">
                                        {{ $ee->competitionEvent->name }}
                                        @if ($ee->competitionEvent->location_label)
                                            <span class="text-gray-400 font-normal">({{ $ee->competitionEvent->location_label }})</span>
                                        @endif
                                    </p>
                                    @if ($ee->division)
                                        <p class="text-xs text-gray-500 mt-0.5">{{ $ee->division->full_label }}</p>
                                    @endif
                                    @if ($ee->competitionEvent->requires_partner)
                                        <p class="text-xs mt-0.5 {{ $ee->yakusuko_complete ? 'text-success-600' : 'text-warning-600' }}">
                                            Partner: {{ $ee->yakusuko_complete ? 'Confirmed' : 'Pending partner enrolment' }}
                                        </p>
                                    @endif
                                </div>

                                {{-- Result --}}
                                <div class="text-right text-sm shrink-0">
                                    @if ($ee->result)
                                        @if ($ee->result->placement)
                                            <span class="font-bold text-primary-600">
                                                @switch($ee->result->placement)
                                                    @case(1) 🥇 1st @break
                                                    @case(2) 🥈 2nd @break
                                                    @case(3) 🥉 3rd @break
                                                    @default {{ $ee->result->placement }}th
                                                @endswitch
                                            </span>
                                        @endif
                                        @if ($ee->result->total_score)
                                            <p class="text-gray-500 text-xs">Score: {{ number_format((float) $ee->result->total_score, 2) }}</p>
                                        @endif
                                        @if ($ee->result->win_loss)
                                            <p class="text-xs {{ $ee->result->win_loss === 'win' ? 'text-success-600' : 'text-danger-600' }}">
                                                {{ ucfirst($ee->result->win_loss) }}
                                            </p>
                                        @endif
                                    @else
                                        <span class="text-gray-400 text-xs">Result pending</span>
                                    @endif
                                </div>
                            </div>

                            {{-- Co-competitors in division --}}
                            @if ($ee->division && $coCompetitors->isNotEmpty())
                                <div class="mt-2 ml-0">
                                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">
                                        Also in this division ({{ $coCompetitors->count() }})
                                    </p>
                                    <div class="flex flex-wrap gap-x-4 gap-y-0.5">
                                        @foreach ($coCompetitors as $other)
                                            @php
                                                $op = $other->enrolment->competitor?->competitorProfile;
                                                $otherName = $op
                                                    ? "{$op->first_name} {$op->surname}"
                                                    : ($other->enrolment->competitor?->name ?? '—');
                                                $otherResult = $other->result;
                                            @endphp
                                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $otherName }}
                                                @if ($otherResult?->placement)
                                                    <span class="text-primary-600 font-medium">
                                                        @switch($otherResult->placement)
                                                            @case(1) 🥇 @break
                                                            @case(2) 🥈 @break
                                                            @case(3) 🥉 @break
                                                            @default ({{ $otherResult->placement }})
                                                        @endswitch
                                                    </span>
                                                @endif
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endforeach
    @endif
</x-filament-panels::page>
