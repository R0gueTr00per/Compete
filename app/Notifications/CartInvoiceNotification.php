<?php

namespace App\Notifications;

use App\Mail\Support\EmailFooterHelper;
use App\Models\EnrolmentCart;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
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
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $org = $this->cart->competition?->organisation;

        $message = (new MailMessage)
            ->subject('Registration Invoice')
            ->greeting("Hi {$notifiable->getFilamentName()},")
            ->line('Your registration has been submitted. See the summary below.');

        foreach ($this->invoice['items'] as $item) {
            $message->line('---')
                ->line("**{$item['profile_name']}** — {$item['competition']} ({$item['competition_date']})");

            if ($item['is_official']) {
                $message->line('_Official rate applied._');
            }

            foreach ($item['events'] as $event) {
                $division = $event['division_label'] ? " / {$event['division_label']}" : '';
                $message->line("• **{$event['event_name']}**{$division}: \$" . number_format($event['fee'], 2));
            }

            if ($item['late_surcharge'] !== null) {
                $message->line('Late surcharge: $' . number_format($item['late_surcharge'], 2));
            }

            if ($item['platform_fee'] > 0) {
                $message->line('Service fee: $' . number_format($item['platform_fee'], 2));
            }

            if (($item['gst_amount'] ?? 0) > 0) {
                $message->line('GST: $' . number_format($item['gst_amount'], 2));
            }

            $message->line('**Subtotal: $' . number_format($item['subtotal'], 2) . '**');
        }

        $totalGst = array_sum(array_column($this->invoice['items'], 'gst_amount'));

        $message->line('---')
            ->line('**Total payable: $' . number_format($this->invoice['grand_total'], 2) . '**');

        if ($totalGst > 0) {
            $message->line('Includes GST of $' . number_format($totalGst, 2));
        }

        $message->line('Payment is collected at the competition check-in desk.')
            ->action('View portal', url('/portal'));

        $portalUrl = $org ? EmailFooterHelper::portalUrl($org) : '';

        return EmailFooterHelper::append($message, $org, $portalUrl);
    }
}
