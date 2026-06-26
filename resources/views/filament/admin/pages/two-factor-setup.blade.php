<x-filament-panels::page>
    @php $user = auth()->user(); @endphp

    @if ($user->hasTwoFactorEnabled())
        <x-filament::section
            heading="Status"
            icon="heroicon-o-check-badge"
        >
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Two-factor authentication is <strong class="text-success-600 dark:text-success-400">active</strong> on your account.
                To disable it, use the <strong>Disable 2FA</strong> button in the page header above.
            </p>
        </x-filament::section>

        <x-filament::section
            heading="Enrolled Device"
            icon="heroicon-o-device-phone-mobile"
        >
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Your authenticator app is enrolled. To re-enrol a new device, disable 2FA and set it up again.
            </p>
        </x-filament::section>
    @else
        <x-filament::section
            heading="Set Up Two-Factor Authentication"
            icon="heroicon-o-shield-check"
        >
            <div class="space-y-6">
                <ol class="list-decimal list-inside text-sm text-gray-600 dark:text-gray-400 space-y-1">
                    <li>Install <strong>Microsoft Authenticator</strong>, <strong>Google Authenticator</strong>, or any TOTP-compatible app.</li>
                    <li>Scan the QR code below, or enter the secret key manually.</li>
                    <li>Enter the 6-digit code shown in the app to confirm and activate 2FA.</li>
                </ol>

                <div class="flex flex-col items-center gap-4 p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 w-fit mx-auto">
                    {!! $this->getQrCodeSvg() !!}
                </div>

                <div>
                    <p class="text-xs font-medium tracking-wide uppercase text-gray-500 dark:text-gray-400 mb-1">Manual entry key</p>
                    <code class="block text-sm font-mono bg-gray-100 dark:bg-gray-800 px-3 py-2 rounded select-all break-all">{{ $this->pendingSecret }}</code>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                        Issuer: <strong>{{ config('app.name') }}</strong> &mdash; Account: {{ auth()->user()->email }}
                    </p>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section heading="Verify and Activate">
            <form wire:submit.prevent="confirmSetup">
                {{ $this->form }}
                <x-filament::actions
                    :actions="$this->getCachedFormActions()"
                />
            </form>
        </x-filament::section>
    @endif
</x-filament-panels::page>
