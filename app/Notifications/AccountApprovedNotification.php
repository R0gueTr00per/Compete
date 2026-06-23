<?php

namespace App\Notifications;

use App\Mail\Support\EmailFooterHelper;
use App\Models\Organisation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountApprovedNotification extends Notification implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use Queueable;

    public function __construct(protected ?Organisation $org = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable->getFilamentName();

        $loginUrl = $this->org
            ? config('app.scheme') . '://' . $this->org->slug . '.' . config('app.domain', 'kompetic.com') . '/portal/login'
            : url(route('filament.portal.auth.login'));

        $orgLine = $this->org
            ? "Your membership for **{$this->org->name}** has been approved."
            : 'Great news — your account has been approved and is now active.';

        $message = (new MailMessage)
            ->subject('Your account is now active')
            ->greeting("Hi {$name},")
            ->line($orgLine)
            ->line('You can now log in and enrol in competitions.')
            ->action('Log in now', $loginUrl);

        $portalUrl = $this->org ? EmailFooterHelper::portalUrl($this->org) : '';

        return EmailFooterHelper::append($message, $this->org, $portalUrl);
    }
}
