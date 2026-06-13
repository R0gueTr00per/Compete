<?php

namespace App\Notifications;

use App\Models\EnrolmentCart;
use App\Models\Refund;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class RefundIssuedNotification extends Notification
{
    public function __construct(
        public readonly EnrolmentCart $cart,
        public readonly Collection $refunds
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $comp      = $this->cart->competition;
        $compName  = $comp?->name ?? 'Competition';
        $compDate  = $comp?->competition_date ? tenant_date($comp->competition_date) : '';
        $currency  = tenant_currency();

        $message = (new MailMessage)
            ->subject("Refund issued — {$compName}")
            ->greeting("Hi {$notifiable->getFilamentName()},")
            ->line("A refund has been issued for your registration at **{$compName}**" . ($compDate ? " ({$compDate})" : '') . '.');

        foreach ($this->refunds as $refund) {
            $profileName = $refund->enrolment?->competitor?->full_name ?? 'Unknown competitor';
            $typeLabel   = $refund->typeLabel();
            $amount      = number_format((float) $refund->amount, 2);
            $method      = ucfirst($refund->payment_method ?? 'cash');

            $message->line('---')
                ->line("**{$profileName}** — {$typeLabel}")
                ->line("Amount: **{$currency} {$amount}**")
                ->line("Method: {$method}");

            if ($refund->reason) {
                $message->line("Reason: {$refund->reason}");
            }
        }

        $totalRefunded = $this->refunds->sum('amount');
        $message->line('---')
            ->line('**Total refunded: ' . $currency . ' ' . number_format((float) $totalRefunded, 2) . '**');

        $message->line('If you have any questions, please contact the organisation directly.');

        return $message->action('View my registrations', route('filament.portal.pages.my-enrolments'));
    }
}
