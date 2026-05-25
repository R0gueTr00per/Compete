<x-filament-panels::page>
    <form wire:submit="submit">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit">
                Send request
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
