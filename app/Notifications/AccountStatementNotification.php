<?php

namespace App\Notifications;

use App\Mail\AccountStatementMail;
use App\Models\EnrolmentCart;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class AccountStatementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param Collection<EnrolmentCart> $carts       All submitted carts for this org (with enrolments + refunds loaded)
     * @param float                     $outstanding  Total outstanding fees
     * @param float                     $refundDue    Total pending refunds
     */
    public function __construct(
        public readonly Collection $carts,
        public readonly float      $outstanding,
        public readonly float      $refundDue,
    ) {
        $this->queue = 'mail';
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): AccountStatementMail
    {
        return new AccountStatementMail($this->carts, $this->outstanding, $this->refundDue, $notifiable);
    }
}
