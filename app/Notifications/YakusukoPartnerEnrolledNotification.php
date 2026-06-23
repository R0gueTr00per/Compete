<?php

namespace App\Notifications;

use App\Mail\YakusukoPartnerEnrolledMail;
use App\Models\Competition;
use App\Models\CompetitionEvent;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class YakusukoPartnerEnrolledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Competition $competition,
        private readonly CompetitionEvent $event,
        private readonly User $partner,
    ) {
        $this->queue = 'mail';
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): YakusukoPartnerEnrolledMail
    {
        return new YakusukoPartnerEnrolledMail(
            $this->competition,
            $this->event,
            $this->partner,
            $notifiable,
        );
    }
}
