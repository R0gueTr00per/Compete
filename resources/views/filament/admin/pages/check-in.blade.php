<x-filament-panels::page>
    {{-- Competition + Search bar --}}
    <div class="flex flex-col sm:flex-row gap-3 mb-6">
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

    @if (! $this->competition_id)
        <p class="text-center text-gray-400 py-12">Select a competition to begin check-in.</p>
    @else
        @php $enrolments = $this->getEnrolments(); @endphp

        @if ($enrolments->isEmpty())
            <p class="text-center text-gray-400 py-12">No competitors found.</p>
        @else
            @php
                $pending   = $enrolments->filter(fn ($e) => $e->status === 'pending');
                $confirmed = $enrolments->filter(fn ($e) => $e->status === 'confirmed');
                $checkedIn = $enrolments->filter(fn ($e) => $e->status === 'checked_in');
                $withdrawn = $enrolments->filter(fn ($e) => $e->status === 'withdrawn');
            @endphp

            @if ($pending->isNotEmpty())
                <h2 class="text-sm font-semibold uppercase tracking-wide text-warning-600 mb-3">Pending ({{ $pending->count() }})</h2>
                <div class="space-y-3 mb-8">
                    @foreach ($pending as $enrolment)
                        @include('filament.admin.partials.checkin-card', ['enrolment' => $enrolment])
                    @endforeach
                </div>
            @endif

            @if ($confirmed->isNotEmpty())
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-3">Confirmed — not yet checked in ({{ $confirmed->count() }})</h2>
                <div class="space-y-3 mb-8">
                    @foreach ($confirmed as $enrolment)
                        @include('filament.admin.partials.checkin-card', ['enrolment' => $enrolment])
                    @endforeach
                </div>
            @endif

            @if ($checkedIn->isNotEmpty())
                <h2 class="text-sm font-semibold uppercase tracking-wide text-success-600 mb-3">Checked in ({{ $checkedIn->count() }})</h2>
                <div class="space-y-3 mb-8">
                    @foreach ($checkedIn as $enrolment)
                        @include('filament.admin.partials.checkin-card', ['enrolment' => $enrolment])
                    @endforeach
                </div>
            @endif

            @if ($withdrawn->isNotEmpty())
                <h2 class="text-sm font-semibold uppercase tracking-wide text-danger-600 mb-3">Withdrawn ({{ $withdrawn->count() }})</h2>
                <div class="space-y-3 mb-8">
                    @foreach ($withdrawn as $enrolment)
                        @include('filament.admin.partials.checkin-card', ['enrolment' => $enrolment])
                    @endforeach
                </div>
            @endif
        @endif
    @endif
</x-filament-panels::page>
