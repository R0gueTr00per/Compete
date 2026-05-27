<?php

namespace App\Filament\Shared\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use App\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use PragmaRX\Google2FALaravel\Facade as Google2FA;

abstract class BaseTwoFactorChallenge extends Page
{
    use InteractsWithFormActions;

    protected static string $view = 'filament.admin.pages.two-factor-challenge';

    protected static string $layout = 'filament-panels::components.layout.simple';

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public function mount(): void
    {
        if (request()->session()->get('2fa_authenticated')) {
            $this->redirect(filament()->getCurrentPanel()->getUrl());
        }

        $this->form->fill();
    }

    protected function getLayoutData(): array
    {
        return [
            'hasTopbar' => false,
            'maxWidth'  => null,
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('code')
                    ->label('Authentication code')
                    ->placeholder('000 000')
                    ->required()
                    ->numeric()
                    ->length(6)
                    ->autofocus(),
            ])
            ->statePath('data');
    }

    public function verify(): void
    {
        $data = $this->form->getState();
        $user = auth()->user();

        $valid = Google2FA::verifyKey(
            $user->twoFactorSecretDecrypted(),
            $data['code'],
        );

        if (! $valid) {
            Notification::make()
                ->title('Invalid code')
                ->body('The code you entered is incorrect or has expired. Please try again.')
                ->danger()
                ->send();

            return;
        }

        request()->session()->put('2fa_authenticated', true);
        $this->redirect(filament()->getCurrentPanel()->getUrl());
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('verify')
                ->label('Verify')
                ->submit('verify'),
        ];
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'Two-Factor Authentication';
    }

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'Two-Factor Authentication';
    }

    public function hasLogo(): bool
    {
        return true;
    }
}
