<?php

namespace App\Notifications;

use App\Mail\Support\EmailFooterHelper;
use App\Models\EnrolmentCart;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class PaymentReceivedNotification extends Notification
{
    /**
     * @param Collection<EnrolmentCart> $carts  Carts that were just marked as paid
     * @param string                    $method Payment method used
     */
    public function __construct(
        public readonly Collection $carts,
        public readonly string     $method,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $org      = app('tenant');
        $currency = tenant_currency();
        $total    = $this->carts->sum(fn ($c) => (float) ($c->payment_amount ?? $c->total_amount));

        $message = (new MailMessage)
            ->subject('Payment received — ' . $org?->name)
            ->greeting('Hi ' . $notifiable->getFilamentName() . ',')
            ->line('Your payment has been recorded. Thank you!');

        foreach ($this->carts as $cart) {
            $active = $cart->enrolments->filter(fn ($e) => ! $e->trashed())->whereNotIn('status', ['draft', 'withdrawn']);
            if ($active->isEmpty()) continue;

            $comp = $active->first()?->competition;
            $message->line('---')
                ->line('**' . ($comp?->name ?? 'Competition') . '**'
                    . ($comp ? ' — ' . tenant_date($comp->competition_date) : ''));

            foreach ($active as $enrolment) {
                $message->line($enrolment->competitor?->full_name ?? 'Competitor');
            }
        }

        $message->line('---')
            ->line('**Total received: ' . $currency . ' ' . number_format($total, 2) . '**')
            ->line('Method: ' . ucfirst($this->method))
            ->line('If you have any questions please contact the organisation directly.')
            ->action('View my account', route('filament.portal.pages.account'));

        $portalUrl = $org ? EmailFooterHelper::portalUrl($org) : '';

        return EmailFooterHelper::append($message, $org ?: null, $portalUrl);
    }
}
