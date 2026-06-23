<?php

namespace App\Mail;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $recipient,
        public readonly ?Organisation $org = null,
    ) {}

    public function build(): self
    {
        $portalUrl = $this->org
            ? config('app.scheme') . '://' . $this->org->slug . '.' . config('app.domain', 'kompetic.com') . '/portal'
            : null;

        $loginUrl = $portalUrl
            ? $portalUrl . '/login'
            : url(route('filament.portal.auth.login'));

        return $this
            ->to($this->recipient->email, $this->recipient->getFilamentName())
            ->subject('Your account is now active')
            ->markdown('emails.account-approved', [
                'org'           => $this->org,
                'portalUrl'     => $portalUrl ?? '',
                'loginUrl'      => $loginUrl,
                'recipientName' => $this->recipient->getFilamentName(),
                'marketingEmail' => false,
            ]);
    }
}
