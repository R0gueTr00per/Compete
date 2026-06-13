<?php

namespace App\Notifications;

use App\Models\EnrolmentCart;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class AccountStatementNotification extends Notification
{
    /**
     * @param Collection<EnrolmentCart> $carts       All submitted carts for this org (with enrolments + refunds loaded)
     * @param float                     $outstanding  Total outstanding fees
     * @param float                     $refundDue    Total pending refunds
     */
    public function __construct(
        public readonly Collection $carts,
        public readonly float      $outstanding,
        public readonly float      $refundDue,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $currency = tenant_currency();
        $net      = $this->outstanding - $this->refundDue;

        $message = (new MailMessage)
            ->subject('Account statement — ' . tenant_name())
            ->greeting('Hi ' . $notifiable->getFilamentName() . ',')
            ->line('Here is your current account summary with ' . tenant_name() . '.');

        // Balance summary
        if (abs($net) < 0.01) {
            $message->line('**Balance: Settled ✓**');
        } elseif ($net > 0) {
            $message->line('**Balance: ' . $currency . ' ' . number_format($net, 2) . ' outstanding**');
        } else {
            $message->line('**Balance: ' . $currency . ' ' . number_format(abs($net), 2) . ' refund due**');
        }

        $message->line('---');

        // Per-cart detail
        foreach ($this->carts as $cart) {
            $comp        = $cart->competition;
            $enrolments  = $cart->enrolments->filter(fn ($e) => ! $e->trashed())->whereNotIn('status', ['draft']);
            $refunds     = $cart->refunds ?? collect();

            if ($enrolments->isEmpty() && $refunds->isEmpty()) continue;

            $message->line('**' . ($comp?->name ?? 'Competition') . '**'
                . ($comp ? ' — ' . tenant_date($comp->competition_date) : ''));

            $isPaid = $cart->isPaid();
            foreach ($enrolments as $enrolment) {
                $status = match (true) {
                    $enrolment->status === 'withdrawn' => 'Withdrawn',
                    $isPaid                            => 'Paid',
                    default                            => 'Outstanding ' . $currency . ' ' . number_format((float) $enrolment->fee_calculated, 2),
                };
                $message->line(($enrolment->competitor?->full_name ?? 'Competitor') . ' — ' . $status);
            }

            foreach ($refunds as $refund) {
                $label = $refund->status === 'issued' ? 'Refunded' : 'Refund pending';
                $message->line(
                    ($refund->enrolment?->competitor?->full_name ?? 'Refund')
                    . ' — ' . $label . ': ' . $currency . ' ' . number_format((float) $refund->amount, 2)
                    . ' (' . ($refund->reason ?? '') . ')'
                );
            }

            $message->line('---');
        }

        $message->line('If you have any questions please contact the organisation directly.');

        return $message->action('View my account', route('filament.portal.pages.my-enrolments'));
    }
}
