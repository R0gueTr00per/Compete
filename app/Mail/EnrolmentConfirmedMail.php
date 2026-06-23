<?php

namespace App\Mail;

use App\Models\Enrolment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EnrolmentConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Enrolment $enrolment,
        public readonly User $recipient,
    ) {}

    public function build(): self
    {
        $competition = $this->enrolment->competition;
        $org         = $competition->organisation;
        $portalUrl   = config('app.scheme') . '://' . $org->slug . '.' . config('app.domain', 'kompetic.com') . '/portal';

        $events = $this->enrolment->activeEvents()
            ->with(['competitionEvent', 'division'])
            ->get();

        $qrImageUrl = $this->enrolment->checkin_code
            ? $portalUrl . '/qr/' . $this->enrolment->checkin_code
            : null;

        return $this
            ->to($this->recipient->email, $this->recipient->getFilamentName())
            ->subject('Registration confirmed — ' . $competition->name)
            ->markdown('emails.enrolment-confirmed', [
                'enrolment'      => $this->enrolment,
                'competition'    => $competition,
                'org'            => $org,
                'portalUrl'      => $portalUrl,
                'events'         => $events,
                'recipientName'  => $this->recipient->getFilamentName(),
                'profileName'    => $this->enrolment->competitor?->full_name ?? $this->recipient->getFilamentName(),
                'qrImageUrl'     => $qrImageUrl,
                'marketingEmail' => false,
            ]);
    }
}
