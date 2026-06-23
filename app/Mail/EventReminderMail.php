<?php

namespace App\Mail;

use App\Models\Competition;
use App\Models\Enrolment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EventReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Competition $competition,
        public readonly Enrolment $enrolment,
        public readonly User $recipient,
    ) {}

    public function build(): self
    {
        $org       = $this->competition->organisation;
        $portalUrl = config('app.scheme') . '://' . $org->slug . '.' . config('app.domain', 'kompetic.com') . '/portal';
        $events    = $this->enrolment->activeEvents()->with('competitionEvent', 'division')->get();
        $checkinTime = $this->competition->competitionDays()->orderBy('date')->first()?->checkin_time;

        return $this
            ->to($this->recipient->email, $this->recipient->getFilamentName())
            ->subject("Reminder: {$this->competition->name} is in 7 days")
            ->markdown('emails.event-reminder', [
                'competition'   => $this->competition,
                'enrolment'     => $this->enrolment,
                'org'           => $org,
                'portalUrl'     => $portalUrl,
                'events'        => $events,
                'checkinTime'   => $checkinTime,
                'recipientName' => $this->recipient->getFilamentName(),
                'marketingEmail' => true,
            ]);
    }
}
