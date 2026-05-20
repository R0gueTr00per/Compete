<?php

namespace App\Notifications;

use App\Models\OrganisationMembership;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class OrgAdminInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly OrganisationMembership $membership) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $org       = $this->membership->organisation;
        $role      = $this->membership->role;
        $isNewUser = $notifiable->password === null;

        $acceptUrl = URL::temporarySignedRoute(
            'invite.org-admin.accept',
            now()->addDays(7),
            ['membership' => $this->membership->id]
        );

        $roleLabel = match ($role) {
            'administrator' => 'organisation administrator',
            'official'      => 'official (check-in & scoring)',
            default         => 'competitor',
        };

        $message = (new MailMessage)
            ->subject("You've been invited to {$org->name} on Kompetic");

        if ($isNewUser) {
            return $message
                ->greeting('Hello,')
                ->line("You've been invited to join **{$org->name}** as a {$roleLabel} on Kompetic.")
                ->line('Click the button below to set up your account and get started.')
                ->action('Accept Invitation', $acceptUrl)
                ->line('This invitation expires in 7 days.');
        }

        return $message
            ->greeting('Hello,')
            ->line("You've been added to **{$org->name}** as a {$roleLabel} on Kompetic.")
            ->action('Access Organisation', $acceptUrl)
            ->line('This link expires in 7 days.');
    }
}
