<?php

namespace App\Mail;

use App\Models\EnrolmentCart;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class RefundIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly EnrolmentCart $cart,
        public readonly Collection $refunds,
        public readonly User $recipient,
    ) {}

    public function build(): self
    {
        $comp      = $this->cart->competition;
        $org       = $comp?->organisation;
        $portalUrl = $org
            ? config('app.scheme') . '://' . $org->slug . '.' . config('app.domain', 'kompetic.com') . '/portal'
            : '';

        return $this
            ->to($this->recipient->email, $this->recipient->getFilamentName())
            ->subject('Refund issued — ' . ($comp?->name ?? 'Competition'))
            ->markdown('emails.refund-issued', [
                'cart'          => $this->cart,
                'refunds'       => $this->refunds,
                'comp'          => $comp,
                'org'           => $org,
                'portalUrl'     => $portalUrl,
                'currency'      => tenant_currency(),
                'recipientName' => $this->recipient->getFilamentName(),
                'marketingEmail' => false,
            ]);
    }
}
