<x-filament-panels::page>
    @php
        $stats = $this->getStats();
        $orgs  = $this->getRecentOrgs();
        $domain = config('app.domain', 'kompetic.com');
    @endphp

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <x-filament::section>
            <div class="text-center">
                <div class="text-3xl font-bold text-gray-800 dark:text-gray-100">{{ $stats['total'] }}</div>
                <div class="text-sm text-gray-500 mt-1">Total organisations</div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <div class="text-3xl font-bold text-green-600">{{ $stats['active'] }}</div>
                <div class="text-sm text-gray-500 mt-1">Active</div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <div class="text-3xl font-bold text-gray-400">{{ $stats['inactive'] }}</div>
                <div class="text-sm text-gray-500 mt-1">Inactive</div>
            </div>
        </x-filament::section>
    </div>

    <x-filament::section heading="Organisations">
        @if ($orgs->isEmpty())
            <p class="text-center text-gray-500 py-8">No organisations yet. <a href="{{ route('filament.admin.resources.organisations.create') }}" class="text-primary-600 underline">Create one</a>.</p>
        @else
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($orgs as $org)
                    <div class="flex items-center justify-between py-3 gap-4">
                        <div>
                            <a href="{{ route('filament.admin.resources.organisations.view', $org) }}" class="font-semibold text-gray-800 dark:text-gray-100 hover:text-primary-600">
                                {{ $org->name }}
                            </a>
                            <div class="text-sm text-gray-400">{{ $org->slug }}.{{ $domain }}</div>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <span class="text-xs text-gray-500">{{ $org->memberships_count }} admin{{ $org->memberships_count === 1 ? '' : 's' }}</span>
                            <span class="text-xs text-gray-500">{{ $org->users_count }} user{{ $org->users_count === 1 ? '' : 's' }}</span>
                            <span class="text-xs text-gray-500">{{ $org->competitions_count }} competition{{ $org->competitions_count === 1 ? '' : 's' }}</span>
                            <span @class([
                                'text-xs font-medium px-2 py-0.5 rounded-full',
                                'bg-green-100 text-green-700' => $org->status === 'active',
                                'bg-gray-100 text-gray-500'   => $org->status !== 'active',
                            ])>{{ ucfirst($org->status) }}</span>
                            <a href="{{ config('app.scheme') }}://{{ $org->slug }}.{{ $domain }}/manage" target="_blank" class="text-xs text-primary-600 hover:underline">Manage →</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
