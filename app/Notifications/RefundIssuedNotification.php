<?php

namespace App\Notifications;

use App\Mail\RefundIssuedMail;
use App\Models\EnrolmentCart;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class RefundIssuedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly EnrolmentCart $cart,
        public readonly Collection $refunds
    ) {
        $this->queue = 'mail';
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): RefundIssuedMail
    {
        return new RefundIssuedMail($this->cart, $this->refunds, $notifiable);
    }
}
