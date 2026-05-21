<?php

namespace App\Filament\Admin\Pages;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;
use PragmaRX\Google2FA\Google2FA as Google2FACore;

class TwoFactorSetup extends Page
{
    use InteractsWithFormActions;
    protected static string $view = 'filament.admin.pages.two-factor-setup';

    protected static ?string $navigationIcon  = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'System';
    protected static ?string $navigationLabel = 'Two-Factor Auth';
    protected static ?int    $navigationSort  = 99;

    public ?array $data = [];

    public ?string $pendingSecret = null;

    public function mount(): void
    {
        if (! auth()->user()->hasTwoFactorEnabled()) {
            $this->pendingSecret = (new Google2FACore())->generateSecretKey();
        }

        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('code')
                    ->label('Verification code')
                    ->placeholder('000 000')
                    ->required()
                    ->numeric()
                    ->length(6)
                    ->helperText('Enter the 6-digit code from your authenticator app to activate 2FA.')
                    ->visible(fn () => ! auth()->user()->hasTwoFactorEnabled()),
            ])
            ->statePath('data');
    }

    public function getQrCodeSvg(): HtmlString
    {
        $user   = auth()->user();
        $secret = $user->hasTwoFactorEnabled()
            ? $user->twoFactorSecretDecrypted()
            : $this->pendingSecret;

        $otpauthUrl = (new Google2FACore())->getQRCodeUrl(
            config('app.name', 'Compete'),
            $user->email,
            $secret,
        );

        $renderer = new ImageRenderer(
            new RendererStyle(192),
            new SvgImageBackEnd(),
        );

        return new HtmlString((new Writer($renderer))->writeString($otpauthUrl));
    }

    public function confirmSetup(): void
    {
        $data  = $this->form->getState();
        $valid = (new Google2FACore())->verifyKey($this->pendingSecret, $data['code']);

        if (! $valid) {
            Notification::make()
                ->title('Invalid code')
                ->body('The code is incorrect or has expired. Ensure your device clock is accurate and try again.')
                ->danger()
                ->send();

            return;
        }

        auth()->user()->enableTwoFactor($this->pendingSecret);
        request()->session()->put('2fa_authenticated', true);

        Notification::make()
            ->title('Two-factor authentication enabled')
            ->body('Your account is now protected with 2FA.')
            ->success()
            ->send();

        $this->redirect(static::getUrl());
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('confirmSetup')
                ->label('Activate Two-Factor Authentication')
                ->submit('confirmSetup')
                ->visible(fn () => ! auth()->user()->hasTwoFactorEnabled()),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('disable')
                ->label('Disable 2FA')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Disable two-factor authentication')
                ->modalDescription('This will remove 2FA from your account. You will be required to set it up again on next login.')
                ->modalSubmitActionLabel('Yes, disable 2FA')
                ->visible(fn () => auth()->user()->hasTwoFactorEnabled())
                ->action(function () {
                    auth()->user()->disableTwoFactor();
                    request()->session()->forget('2fa_authenticated');

                    Notification::make()
                        ->title('Two-factor authentication disabled')
                        ->warning()
                        ->send();

                    $this->redirect(static::getUrl());
                }),
        ];
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'Two-Factor Authentication';
    }
}
