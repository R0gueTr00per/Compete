<x-filament-panels::page>

    @if (! $this->competition_id)
        <x-filament::section>
            <p class="text-sm text-gray-500 py-4">No competition selected. Please return to the dashboard and click <strong>Enrol now</strong> next to a competition.</p>
            <x-filament::button href="{{ route('filament.portal.pages.dashboard') }}" tag="a" color="gray" size="sm">Back to Dashboard</x-filament::button>
        </x-filament::section>
    @endif

    @if ($this->competition_id)
        {{-- Profile indicator / picker --}}
        @if ($this->profile_id)
            <div class="mb-6 flex items-center justify-between rounded-lg border border-gray-200 dark:border-gray-700 px-4 py-3">
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    Enrolling: <strong>{{ $this->getSelectedProfileName() }}</strong>
                </p>
                @if (!$this->dojo_type && !$this->rank_id)
                    <button type="button" wire:click="changeProfile" class="text-xs text-primary-600 hover:underline">
                        Change
                    </button>
                @endif
            </div>
        @else
            @php $profiles = $this->getAvailableProfiles(); @endphp
            <x-filament::section heading="Who is enrolling?" class="mb-6">
                @if (empty($profiles))
                    <p class="text-sm text-gray-500">
                        All your profiles are already enrolled or in your cart for this competition.
                        @if ($this->getCartCount() > 0)
                            <a wire:navigate href="{{ \App\Filament\Portal\Pages\CartPage::getUrl() }}" class="text-primary-600 underline">Go to cart.</a>
                        @endif
                    </p>
                @else
                    <div class="space-y-2">
                        @foreach ($profiles as $profile)
                            <label @class([
                                'flex items-center gap-3 rounded-lg border px-4 py-3 cursor-pointer transition-colors',
                                'border-primary-500 bg-primary-50 dark:bg-primary-950' => $this->profile_id == $profile['id'],
                                'border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800' => $this->profile_id != $profile['id'],
                            ])>
                                <input type="radio" wire:model.live="profile_id" value="{{ $profile['id'] }}" class="text-primary-600 focus:ring-primary-500" />
                                <div>
                                    <p class="font-medium text-sm">{{ $profile['name'] }}</p>
                                    @if ($profile['family'])
                                        <p class="text-xs text-gray-500">Family member</p>
                                    @endif
                                </div>
                            </label>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>
        @endif

        {{-- Entry form --}}
        @if ($this->profile_id)
            {{ $this->form }}

            <div class="mt-6 flex flex-wrap gap-3">
                @if ($this->details_confirmed)
                    <x-filament::button wire:click="addToCart" size="lg" :disabled="empty($this->selected_entries)">
                        Add to Cart
                    </x-filament::button>
                    <x-filament::button wire:click="backToDetails" color="gray" size="lg">
                        Back to Details
                    </x-filament::button>
                @else
                    <x-filament::button wire:click="confirmDetails" size="lg">
                        Next — Choose Events
                    </x-filament::button>
                @endif
                <x-filament::button wire:click="cancel" color="gray" size="lg" outlined>
                    Cancel
                </x-filament::button>
            </div>
        @endif
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
