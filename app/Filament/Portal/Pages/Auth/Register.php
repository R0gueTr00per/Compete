<?php

namespace App\Filament\Portal\Pages\Auth;

use App\Models\OrganisationMembership;
use App\Notifications\NewUserRegisteredNotification;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse;
use Illuminate\Validation\Rule;
use App\Notifications\Notification;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

class Register extends BaseRegister
{
    public function mount(): void
    {
        parent::mount();

        if (request()->query('no_membership')) {
            Notification::make()
                ->title('Not a member')
                ->body('You don\'t have access to this organisation. Register below to request access.')
                ->warning()
                ->persistent()
                ->send();
        }
    }

    public function register(): ?RegistrationResponse
    {
        try {
            $this->rateLimit(2);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();
            return null;
        }

        $tenant = app('tenant');
        $data   = $this->form->getState();

        // Org portal: create account as pending, awaiting org admin approval
        if ($tenant) {
            $data['status']          = 'pending';
            $data['organisation_id'] = $tenant->id;

            try {
                $membership = $this->wrapInDatabaseTransaction(function () use ($data, $tenant) {
                    $user = $this->handleRegistration($data);

                    $membership = OrganisationMembership::create([
                        'organisation_id' => $tenant->id,
                        'user_id'         => $user->id,
                        'role'            => 'competitor',
                        'status'          => 'pending',
                    ]);

                    // Notify all active org administrators
                    $admins = OrganisationMembership::where('organisation_id', $tenant->id)
                        ->where('role', 'administrator')
                        ->where('status', 'active')
                        ->with('user')
                        ->get();

                    foreach ($admins as $admin) {
                        $admin->user->notify(new NewUserRegisteredNotification($user, $membership));
                    }

                    return $membership;
                });
            } catch (UniqueConstraintViolationException) {
                $this->addError('data.email', 'An account already exists for this email address.');
                return null;
            }

            $this->redirect(route('filament.portal.auth.login') . '?registered=1', navigate: false);
            return null;
        }

        // Non-org (legacy) registration — pending approval flow
        $data['status'] = 'pending';

        try {
            $user = $this->wrapInDatabaseTransaction(fn () => $this->handleRegistration($data));
        } catch (UniqueConstraintViolationException) {
            $this->addError('data.email', 'An account already exists for this email address.');
            return null;
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable) {
            // Non-fatal
        }

        $this->redirect(route('filament.portal.auth.login') . '?registered=1', navigate: true);
        return null;
    }

    protected function handleRegistration(array $data): Model
    {
        return $this->getUserModel()::create($data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getEmailFormComponent()
                    ->autofocus()
                    ->rules([
                        Rule::unique('users', 'email')
                            ->where('organisation_id', app('tenant')?->id),
                    ])
                    ->validationMessages(['unique' => 'An account already exists for this email address.']),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }

    protected function getFormActions(): array
    {
        return [
            $this->getRegisterFormAction(),
            Action::make('cancel')
                ->label(__('Cancel'))
                ->url(route('filament.portal.auth.login'))
                ->color('gray')
                ->outlined(),
        ];
    }
}
