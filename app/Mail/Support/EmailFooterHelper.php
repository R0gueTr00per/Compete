<?php

namespace App\Mail\Support;

use App\Models\Organisation;
use Illuminate\Notifications\Messages\MailMessage;

class EmailFooterHelper
{
    public static function append(
        MailMessage $message,
        ?Organisation $org = null,
        string $portalUrl = '',
        bool $marketing = false,
    ): MailMessage {
        $domain = config('app.scheme') . '://' . config('app.domain', 'kompetic.com');

        $message->line('---');

        if ($org) {
            $header = "**{$org->name}**";
            if ($portalUrl) {
                $header .= " · [Competitor Portal]({$portalUrl})";
            }
            $message->line($header);

            $contacts = [];
            if ($org->contact_email) {
                $contacts[] = "[{$org->contact_email}](mailto:{$org->contact_email})";
            }
            if ($org->contact_phone) {
                $contacts[] = $org->contact_phone;
            }
            if ($org->website) {
                $contacts[] = "[{$org->website}]({$org->website})";
            }
            if ($contacts) {
                $message->line(implode(' · ', $contacts));
            }
        }

        $message->line("[Kompetic]({$domain}) — Tournament Management · [support@kompetic.com](mailto:support@kompetic.com)");

        if ($marketing && $org && $portalUrl) {
            $message->line("*You are receiving this email because you are a member of {$org->name} on [Kompetic]({$domain}). [Update email preferences]({$portalUrl}/preferences)*");
        }

        return $message->salutation(' ');
    }

    public static function portalUrl(Organisation $org): string
    {
        return config('app.scheme') . '://' . $org->slug . '.' . config('app.domain', 'kompetic.com') . '/portal';
    }
}
