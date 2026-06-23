<?php

namespace App\Mail;

use App\Models\Enrolment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminCreatedAccountMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Enrolment $enrolment,
        public readonly User $recipient,
        public readonly string $resetToken,
        public readonly ?string $childName = null,
    ) {}

    public function build(): self
    {
        $competition = $this->enrolment->competition;
        $org         = $competition->organisation;
        $portalUrl   = config('app.scheme') . '://' . $org->slug . '.' . config('app.domain', 'kompetic.com') . '/portal';

        $resetUrl = route('filament.portal.auth.password-reset.reset', [
            'token' => $this->resetToken,
            'email' => $this->recipient->email,
        ]);

        $events = $this->enrolment->activeEvents()
            ->with(['competitionEvent', 'division'])
            ->get();

        $orgName = $org->name;
        $subject = $this->childName
            ? "Welcome to {$orgName} — {$this->childName} has been registered"
            : "Welcome to {$orgName} — your account is ready";

        return $this
            ->to($this->recipient->email, $this->recipient->getFilamentName())
            ->subject($subject)
            ->markdown('emails.admin-created-account', [
                'competition'   => $competition,
                'org'           => $org,
                'portalUrl'     => $portalUrl,
                'resetUrl'      => $resetUrl,
                'events'        => $events,
                'recipientName' => $this->recipient->getFilamentName(),
                'childName'     => $this->childName,
                'marketingEmail' => false,
            ]);
    }
}
