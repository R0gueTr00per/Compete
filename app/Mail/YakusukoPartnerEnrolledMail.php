<?php

namespace App\Mail;

use App\Models\Competition;
use App\Models\CompetitionEvent;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class YakusukoPartnerEnrolledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Competition $competition,
        public readonly CompetitionEvent $event,
        public readonly User $partner,
        public readonly User $recipient,
    ) {}

    public function build(): self
    {
        $org       = $this->competition->organisation;
        $portalUrl = config('app.scheme') . '://' . $org->slug . '.' . config('app.domain', 'kompetic.com') . '/portal';

        return $this
            ->to($this->recipient->email, $this->recipient->getFilamentName())
            ->subject('Yakusuko partner confirmed — ' . $this->competition->name)
            ->markdown('emails.yakusuko-partner-enrolled', [
                'competition'    => $this->competition,
                'event'          => $this->event,
                'partner'        => $this->partner,
                'org'            => $org,
                'portalUrl'      => $portalUrl,
                'recipientName'  => $this->recipient->getFilamentName(),
                'marketingEmail' => false,
            ]);
    }
}
