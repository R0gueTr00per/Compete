<x-layouts.public>
    <x-slot name="title">Schedule — {{ $competition->name }}</x-slot>

    <x-slot name="head">
        <meta http-equiv="refresh" content="60">
    </x-slot>

    {{-- Page header --}}
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
            <h1 class="text-xl font-bold text-gray-900">{{ $competition->name }}</h1>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1 text-sm text-gray-500">
                <span>{{ \Carbon\Carbon::parse($competition->competition_date)->format('l j F Y') }}</span>
                @if ($competition->location_name)
                    <span>&middot; {{ $competition->location_name }}</span>
                @endif
                @if ($competition->start_time)
                    <span>&middot; Starts {{ \Carbon\Carbon::parse($competition->start_time)->format('g:i a') }}</span>
                @endif
                <span class="sm:ml-auto text-xs text-gray-400">Auto-refreshes every 60 s &middot; Updated {{ now()->format('g:i a') }}</span>
            </div>
        </div>
    </div>

    {{-- Legend --}}
    <div class="bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex flex-wrap gap-4 text-xs text-gray-600">
            <span class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-green-500 inline-block"></span> Complete
            </span>
            <span class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-blue-500 inline-block"></span> Scheduled
            </span>
            <span class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-gray-400 inline-block"></span> Pending
            </span>
            <span class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-red-500 inline-block"></span> Cancelled
            </span>
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
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.public>
