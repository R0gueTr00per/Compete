<?php

namespace App\Notifications;

use App\Mail\PaymentReceivedMail;
use App\Models\EnrolmentCart;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class PaymentReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param Collection<EnrolmentCart> $carts  Carts that were just marked as paid
     * @param string                    $method Payment method used
     */
    public function __construct(
        public readonly Collection $carts,
        public readonly string     $method,
    ) {
        $this->queue = 'mail';
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): PaymentReceivedMail
    {
        return new PaymentReceivedMail($this->carts, $this->method, $notifiable);
    }
}
