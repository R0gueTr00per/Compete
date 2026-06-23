<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class PaymentReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Collection $carts,
        public readonly string $method,
        public readonly User $recipient,
    ) {}

    public function build(): self
    {
        $org       = app('tenant');
        $portalUrl = $org
            ? config('app.scheme') . '://' . $org->slug . '.' . config('app.domain', 'kompetic.com') . '/portal'
            : '';
        $total     = $this->carts->sum(fn ($c) => (float) ($c->payment_amount ?? $c->total_amount));

        return $this
            ->to($this->recipient->email, $this->recipient->getFilamentName())
            ->subject('Payment received — ' . $org?->name)
            ->markdown('emails.payment-received', [
                'carts'         => $this->carts,
                'method'        => $this->method,
                'org'           => $org,
                'portalUrl'     => $portalUrl,
                'total'         => $total,
                'currency'      => tenant_currency(),
                'recipientName' => $this->recipient->getFilamentName(),
                'marketingEmail' => false,
            ]);
    }
}
