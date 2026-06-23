<?php

namespace App\Notifications;

use App\Mail\AdminCreatedAccountMail;
use App\Models\Enrolment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AdminCreatedParentAccountNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Enrolment $enrolment,
        public readonly string $resetToken,
    ) {
        $this->queue = 'mail';
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): AdminCreatedAccountMail
    {
        $childName = $this->enrolment->competitor?->full_name ?? 'your child';

        return new AdminCreatedAccountMail($this->enrolment, $notifiable, $this->resetToken, $childName);
    }
}
