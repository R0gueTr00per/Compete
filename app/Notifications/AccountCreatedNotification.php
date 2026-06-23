<?php

namespace App\Notifications;

use App\Mail\AccountSetupMail;
use App\Models\Organisation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AccountCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $resetToken,
        public readonly ?Organisation $org = null,
    ) {
        $this->queue = 'mail';
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): AccountSetupMail
    {
        return new AccountSetupMail($notifiable, $this->resetToken, $this->org);
    }
}
