<x-filament-panels::page>
    <form wire:submit="submit">
        {{ $this->form }}

        <div class="mt-6 flex gap-3">
            @if ($this->isReadyToSubmit())
                <x-filament::button type="submit" size="lg">
                    {{ $this->create_new_user ? 'Create Profile & Enrol' : 'Create Enrolment' }}
                </x-filament::button>
                <x-filament::button type="button" wire:click="goBack" color="gray" size="lg">
                    Back
                </x-filament::button>
                <x-filament::button
                    tag="a"
                    href="{{ \App\Filament\Admin\Resources\EnrolmentResource::getUrl() }}"
                    color="gray"
                    size="lg"
                >
                    Cancel
                </x-filament::button>
            @elseif ($this->details_confirmed)
                <x-filament::button type="button" wire:click="goBack" color="gray" size="lg">
                    Back
                </x-filament::button>
            @else
                <x-filament::button type="button" wire:click="nextHint" size="lg">
                    Next
                </x-filament::button>
            @endif
        </div>
    </form>

    <x-filament-actions::modals />
</x-filament-panels::page>
