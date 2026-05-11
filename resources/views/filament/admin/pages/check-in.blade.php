<x-filament-panels::page>
    {{-- Competition + Search bar --}}
    <div class="mb-6 rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 dark:border-primary-800 dark:bg-primary-950/30">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-primary-700 dark:text-primary-400">Competition</p>
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <x-filament::input.wrapper>
                    <select
                        wire:model.live="competition_id"
                        class="w-full block border-0 bg-transparent py-1.5 text-gray-900 dark:text-white placeholder:text-gray-400 focus:ring-0 sm:text-sm sm:leading-6"
                    >
                        <option value="">— Select competition —</option>
                        @foreach ($this->getCompetitions() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </x-filament::input.wrapper>
            </div>

            <div class="flex-1">
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search competitor name…"
                    />
                </x-filament::input.wrapper>
            </div>
        </div>
    </div>

    @if (! $this->competition_id)
        <p class="text-center text-gray-400 py-12">Select a competition to begin check-in.</p>
    @elseif (! in_array(($competition = \App\Models\Competition::find($this->competition_id))?->status, ['closed', 'check_in', 'running']))
        <p class="text-center text-gray-400 py-12">Check-in is not available yet — enrolments are still open or competition has not closed.</p>
    @else
        @php $enrolments = $this->getEnrolments(); @endphp

        @if ($enrolments->isEmpty())
            <p class="text-center text-gray-400 py-12">No competitors found.</p>
        @else
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
</x-filament-panels::page>
