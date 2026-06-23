<?php

namespace App\Mail;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountSetupMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $recipient,
        public readonly string $resetToken,
        public readonly ?Organisation $org = null,
    ) {}

    public function build(): self
    {
        $portalUrl = $this->org
            ? config('app.scheme') . '://' . $this->org->slug . '.' . config('app.domain', 'kompetic.com') . '/portal'
            : null;

        $resetUrl = route('filament.portal.auth.password-reset.reset', [
            'token' => $this->resetToken,
            'email' => $this->recipient->email,
        ]);

        $subject = $this->org
            ? "Welcome to {$this->org->name} — set your password"
            : 'Welcome to Compete — set your password';

        return $this
            ->to($this->recipient->email, $this->recipient->getFilamentName())
            ->subject($subject)
            ->markdown('emails.account-setup', [
                'org'           => $this->org,
                'portalUrl'     => $portalUrl ?? '',
                'resetUrl'      => $resetUrl,
                'recipientName' => $this->recipient->getFilamentName(),
                'marketingEmail' => false,
            ]);
    }
}
