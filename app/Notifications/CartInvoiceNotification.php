<?php

namespace App\Notifications;

use App\Mail\CartInvoiceMail;
use App\Models\EnrolmentCart;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CartInvoiceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{competition: string, competition_date: string, location_name: ?string, items: array, grand_total: float}  $invoice
     */
    public function __construct(
        public readonly EnrolmentCart $cart,
        private readonly array $invoice
    ) {
        $this->queue = 'mail';
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): CartInvoiceMail
    {
        return new CartInvoiceMail($this->cart, $this->invoice, $notifiable);
    }
}
