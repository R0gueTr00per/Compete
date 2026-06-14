<x-filament-panels::page>
    @if(auth()->user()->pending_email)
        <x-filament::section>
            <x-slot name="heading">Change email</x-slot>

            <div class="space-y-3">
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    A verification email has been sent to
                    <strong>{{ auth()->user()->pending_email }}</strong>.
                    Click the link in that email to confirm the change.
                    Your current address (<strong>{{ auth()->user()->email }}</strong>) remains active until then.
                </p>

                <div class="flex flex-wrap gap-2 pt-1">
                    <x-filament::button wire:click="resendEmailVerification" color="gray" size="sm">
                        Resend verification email
                    </x-filament::button>

                    <x-filament::button wire:click="cancelEmailChange" color="danger" size="sm">
                        Cancel email change
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>
    @else
        <form wire:submit="requestEmailChange">
            {{ $this->emailForm }}

            <div class="mt-4">
                <x-filament::button type="submit">
                    Update email
                </x-filament::button>
            </div>
        </form>
    @endif

    <form wire:submit="savePassword">
        {{ $this->passwordForm }}

        <div class="mt-4">
            <x-filament::button type="submit">
                Change password
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
