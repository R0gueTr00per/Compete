<?php

namespace App\Notifications;

use App\Mail\Support\EmailFooterHelper;
use App\Models\OrganisationMembership;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewUserRegisteredNotification extends Notification implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use Queueable;

    public function __construct(
        protected User $newUser,
        protected ?OrganisationMembership $membership = null
    ) {
        $this->queue = 'mail';
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->newUser->getFilamentName();
        $org  = $this->membership?->organisation;

        if ($org) {
            $url     = config('app.scheme') . '://' . $org->slug . '.' . config('app.domain', 'kompetic.com') . '/manage/members';
            $subject = “New member awaiting approval — {$org->name}”;
            $body    = "**{$name}** ({$this->newUser->email}) has registered on the {$org->name} portal and is awaiting your approval.";
        } else {
            $url     = url(route('filament.admin.resources.users.index'));
            $subject = 'Compete: New user awaiting approval';
            $body    = "**{$name}** ({$this->newUser->email}) has registered and is awaiting approval.";
        }

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting('New registration')
            ->line($body)
            ->action('Review in Users', $url);

        $portalUrl = $org ? EmailFooterHelper::portalUrl($org) : '';

        return EmailFooterHelper::append($message, $org, $portalUrl);
    }
}
