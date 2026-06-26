<x-filament-panels::page>
    <form wire:submit.prevent="save">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit">
                Save preferences
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
