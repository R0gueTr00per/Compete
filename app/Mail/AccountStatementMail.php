<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class AccountStatementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Collection $carts,
        public readonly float $outstanding,
        public readonly float $refundDue,
        public readonly User $recipient,
    ) {}

    public function build(): self
    {
        $org       = app('tenant');
        $portalUrl = $org
            ? config('app.scheme') . '://' . $org->slug . '.' . config('app.domain', 'kompetic.com') . '/portal'
            : '';
        $net       = $this->outstanding - $this->refundDue;

        return $this
            ->to($this->recipient->email, $this->recipient->getFilamentName())
            ->subject('Account statement — ' . $org?->name)
            ->markdown('emails.account-statement', [
                'carts'         => $this->carts,
                'outstanding'   => $this->outstanding,
                'refundDue'     => $this->refundDue,
                'net'           => $net,
                'org'           => $org,
                'portalUrl'     => $portalUrl,
                'currency'      => tenant_currency(),
                'recipientName' => $this->recipient->getFilamentName(),
                'marketingEmail' => false,
            ]);
    }
}
