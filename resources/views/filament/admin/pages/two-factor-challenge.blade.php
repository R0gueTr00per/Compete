<x-filament-panels::page.simple>
    <x-filament-panels::form wire:submit="verify">
        <p class="text-sm text-center text-gray-600 dark:text-gray-400">
            Open your authenticator app and enter the 6-digit code for <strong>{{ config('app.name') }}</strong>.
        </p>

        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="true"
        />
    </x-filament-panels::form>
</x-filament-panels::page.simple>
