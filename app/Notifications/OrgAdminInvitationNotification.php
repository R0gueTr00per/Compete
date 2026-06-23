<?php

namespace App\Notifications;

use App\Mail\OrgInvitationMail;
use App\Models\OrganisationMembership;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OrgAdminInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly OrganisationMembership $membership)
    {
        $this->queue = 'mail';
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): OrgInvitationMail
    {
        return new OrgInvitationMail($this->membership, $notifiable);
    }
}
