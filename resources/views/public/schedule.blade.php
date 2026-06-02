<x-layouts.public>
    <x-slot name="title">Schedule — {{ $competition->name }}</x-slot>

    <x-slot name="head">
        @if ($competition->status !== 'complete')
            <meta http-equiv="refresh" content="60">
        @endif
    </x-slot>

    {{-- Page header --}}
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
            <h1 class="text-xl font-bold text-gray-900">{{ $competition->name }}</h1>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1 text-sm text-gray-500">
                <span>{{ tenant_date($competition->competition_date) }}</span>
                @if ($competition->location_name)
                    <span>&middot; {{ $competition->location_name }}</span>
                @endif
                @if ($competition->start_time)
                    <span>&middot; Starts {{ tenant_time($competition->start_time) }}</span>
                @endif
                @if ($competition->status !== 'complete')
                    <span class="sm:ml-auto text-xs text-gray-400">Auto-refreshes every 60 s &middot; Updated {{ tenant_time(now()) }}</span>
                @else
                    <span class="sm:ml-auto text-xs text-gray-400">Final results</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Schedule columns --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        @if ($divisions->isEmpty())
            <p class="text-center text-gray-400 py-16">No divisions scheduled yet.</p>
        @else
            <div class="flex gap-4 overflow-x-auto pb-4 items-start">
                @foreach ($divisions as $location => $locationDivisions)
                    <div class="flex-none w-64">
                        <h2 class="text-xs font-bold uppercase tracking-widest text-gray-500 mb-3 pb-2 border-b-2 border-gray-200">
                            {{ $location }}
                        </h2>

                        <div class="space-y-2">
                            @foreach ($locationDivisions as $div)
                                @php
                                    $cardClass = match ($div->status) {
                                        'complete'           => 'bg-green-50 border-green-300',
                                        'assigned','running' => 'bg-blue-50 border-blue-300',
                                        'cancelled'          => 'bg-red-50 border-red-300 opacity-60',
                                        default              => 'bg-white border-gray-200',
                                    };
                                    $badgeClass = match ($div->status) {
                                        'complete'  => 'bg-green-100 text-green-800',
                                        'cancelled' => 'bg-red-100 text-red-800',
                                        default     => null,
                                    };
                                    $placementLabels = ['1st', '2nd', '3rd'];
                                    $placementColors = [
                                        1 => 'bg-yellow-100 text-yellow-800 border border-yellow-300',
                                        2 => 'bg-gray-100 text-gray-700 border border-gray-300',
                                        3 => 'bg-orange-100 text-orange-800 border border-orange-300',
                                    ];
                                @endphp
                                <div class="rounded-lg border {{ $cardClass }} px-3 py-2.5">
                                    <div class="font-mono text-xs font-bold text-gray-700">{{ $div->code }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">{{ $div->competitionEvent->name }}</div>
                                    <div class="text-sm text-gray-800 mt-0.5">{{ $div->label }}</div>
                                    @if ($badgeClass)
                                        <span class="inline-block mt-1.5 px-2 py-0.5 rounded-full text-xs font-semibold {{ $badgeClass }}">
                                            {{ ucfirst($div->status) }}
                                        </span>
                                    @endif

                                    @if ($div->status === 'complete' && $div->results->isNotEmpty())
                                        <div class="mt-2 space-y-1 border-t border-green-200 pt-2">
                                            @foreach ($div->results->whereNotNull('placement')->sortBy('placement')->take(3) as $result)
                                                @php $competitor = $result->enrolmentEvent?->competitor; @endphp
                                                <div class="flex items-center gap-1.5 text-xs">
                                                    <span class="flex-none inline-block px-1.5 py-0.5 rounded text-xs font-bold {{ $placementColors[$result->placement] ?? 'bg-gray-100 text-gray-600' }}">
                                                        {{ $placementLabels[$result->placement - 1] ?? $result->placement . 'th' }}
                                                    </span>
                                                    <span class="truncate text-gray-700 {{ $result->placement === 1 ? 'font-bold' : '' }}">
                                                        @if ($result->disqualified)
                                                            <span class="text-red-600">DQ</span>
                                                        @elseif ($competitor)
                                                            {{ $competitor->first_name }} {{ $competitor->surname }}
                                                        @else
                                                            &mdash;
                                                        @endif
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.public>
