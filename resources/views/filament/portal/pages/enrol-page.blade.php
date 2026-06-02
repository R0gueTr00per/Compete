<x-filament-panels::page>

    @if (! $this->competition_id)
        <x-filament::section>
            <p class="text-sm text-gray-500 py-4">No competition selected. Please return to the dashboard and click <strong>Register now</strong> next to a competition.</p>
            <x-filament::button href="{{ route('filament.portal.pages.dashboard') }}" tag="a" color="gray" size="sm">Back to Dashboard</x-filament::button>
        </x-filament::section>
    @endif

    @if ($this->competition_id)
        @php $competition = $this->getSelectedCompetition(); @endphp

        {{-- Combined context bar --}}
        @if ($competition)
            <div class="mb-5 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 px-4 py-3 flex flex-wrap items-center justify-between gap-x-6 gap-y-1">
                <div>
                    <span class="font-semibold text-sm text-gray-900 dark:text-white">{{ $competition->name }}</span>
                    <span class="text-xs text-gray-400 dark:text-gray-500 ml-2">
                        {{ tenant_date($competition->competition_date) }}@if ($competition->location_name) &middot; {{ $competition->location_name }}@endif
                    </span>
                </div>
                @if ($this->profile_id)
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        Registering: <strong class="text-gray-700 dark:text-gray-300">{{ $this->getSelectedProfileName() }}</strong>
                    </span>
                @endif
            </div>
        @endif

        {{-- Profile picker --}}
        @if (! $this->profile_id)
            @php $profiles = $this->getAvailableProfiles(); @endphp
            <div class="mb-5">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Who is registering?</p>
                @if (empty($profiles))
                    <p class="text-sm text-gray-500">
                        All your profiles are already registered or in your cart for this competition.
                        @if ($this->getCartCount() > 0)
                            <a wire:navigate href="{{ \App\Filament\Portal\Pages\CartPage::getUrl() }}" class="text-primary-600 underline">Go to cart.</a>
                        @endif
                    </p>
                @else
                    <div class="space-y-2">
                        @foreach ($profiles as $profile)
                            <label @class([
                                'flex items-center gap-3 rounded-lg border px-4 py-2.5 cursor-pointer transition-colors',
                                'border-primary-500 bg-primary-50 dark:bg-primary-950' => $this->profile_id == $profile['id'],
                                'border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800' => $this->profile_id != $profile['id'],
                            ])>
                                <input type="radio" wire:model.live="profile_id" value="{{ $profile['id'] }}" class="text-primary-600 focus:ring-primary-500" />
                                <div>
                                    <p class="font-medium text-sm">{{ $profile['name'] }}</p>
                                    @if ($profile['family'])
                                        <p class="text-xs text-gray-400">Family member</p>
                                    @endif
                                </div>
                            </label>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- Entry form --}}
        @if ($this->profile_id)
            {{ $this->form }}

            {{-- Checkbox (toggle) registration questions — rendered natively to avoid Filament state-sync issues --}}
            @if (! $this->details_confirmed)
                @php $checkboxFields = $this->getCheckboxRegistrationFields(); @endphp
                @foreach ($checkboxFields as $field)
                    @php $checked = (bool) ($this->custom_fields[$field['id']] ?? false); @endphp
                    <div class="flex items-start gap-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-4 py-3 mt-4">
                        <button
                            type="button"
                            wire:click="toggleCustomField('{{ $field['id'] }}')"
                            role="switch"
                            aria-checked="{{ $checked ? 'true' : 'false' }}"
                            class="relative mt-0.5 inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 {{ $checked ? 'bg-primary-600' : 'bg-gray-200 dark:bg-gray-600' }}"
                        >
                            <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $checked ? 'translate-x-5' : 'translate-x-0' }}"></span>
                        </button>
                        <p class="text-sm text-gray-700 dark:text-gray-300 flex-1 leading-snug">
                            {{ $field['label'] }}
                            @if (!empty($field['required']))
                                <span class="text-danger-500 ml-0.5">*</span>
                            @endif
                        </p>
                    </div>
                @endforeach
            @endif

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
