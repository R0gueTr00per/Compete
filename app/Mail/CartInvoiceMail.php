<?php

namespace App\Mail;

use App\Models\EnrolmentCart;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CartInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly EnrolmentCart $cart,
        public readonly array $invoice,
        public readonly User $recipient,
    ) {}

    public function build(): self
    {
        $org       = $this->cart->competition?->organisation;
        $portalUrl = $org
            ? config('app.scheme') . '://' . $org->slug . '.' . config('app.domain', 'kompetic.com') . '/portal'
            : '';

        $totalGst = array_sum(array_column($this->invoice['items'], 'gst_amount'));

        return $this
            ->to($this->recipient->email, $this->recipient->getFilamentName())
            ->subject('Registration Invoice')
            ->markdown('emails.cart-invoice', [
                'invoice'       => $this->invoice,
                'org'           => $org,
                'portalUrl'     => $portalUrl,
                'totalGst'      => $totalGst,
                'recipientName' => $this->recipient->getFilamentName(),
                'marketingEmail' => false,
            ]);
    }
}
