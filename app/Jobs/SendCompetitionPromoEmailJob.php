<?php

namespace App\Jobs;

use App\Mail\CompetitionPromoMail;
use App\Models\Competition;
use App\Models\CompetitorProfile;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendCompetitionPromoEmailJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries = 1;

    public function __construct(public Competition $competition) {}

    public function handle(): void
    {
        $orgId = $this->competition->organisation_id;

        $profiles = CompetitorProfile::where('organisation_id', $orgId)
            ->where('is_active', true)
            ->get();

        $userIds = $profiles
            ->map(fn ($p) => $p->user_id ?? $p->owner_user_id)
            ->filter()
            ->unique();

        User::whereIn('id', $userIds)
            ->where('status', 'active')
            ->where('receive_competition_emails', true)
            ->get()
            ->each(fn (User $user) => Mail::to($user)->queue(
                new CompetitionPromoMail($this->competition)
            ));
    }
}
