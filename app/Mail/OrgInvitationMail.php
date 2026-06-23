<?php

namespace App\Mail;

use App\Models\OrganisationMembership;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class OrgInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly OrganisationMembership $membership,
        public readonly User $recipient,
    ) {}

    public function build(): self
    {
        $org = $this->membership->organisation;
        $isNewUser = $this->recipient->password === null;

        $acceptUrl = URL::temporarySignedRoute(
            'invite.org-admin.accept',
            now()->addDays(7),
            ['membership' => $this->membership->id]
        );

        $roleLabel = match ($this->membership->role) {
            'administrator' => 'organisation administrator',
            default         => 'competitor',
        };

        $portalUrl = config('app.scheme') . '://' . $org->slug . '.' . config('app.domain', 'kompetic.com') . '/portal';

        return $this
            ->to($this->recipient->email, $this->recipient->getFilamentName())
            ->subject("You've been invited to {$org->name} on Kompetic")
            ->markdown('emails.org-invitation', [
                'org'           => $org,
                'portalUrl'     => $portalUrl,
                'acceptUrl'     => $acceptUrl,
                'roleLabel'     => $roleLabel,
                'isNewUser'     => $isNewUser,
                'recipientName' => $this->recipient->getFilamentName(),
                'marketingEmail' => false,
            ]);
    }
}
