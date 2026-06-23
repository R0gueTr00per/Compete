<?php

namespace App\Notifications;

use App\Mail\AccountApprovedMail;
use App\Models\Organisation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AccountApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected ?Organisation $org = null)
    {
        $this->queue = 'mail';
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): AccountApprovedMail
    {
        return new AccountApprovedMail($notifiable, $this->org);
    }
}
