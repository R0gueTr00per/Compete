<?php

namespace App\Jobs;

use App\Mail\CompetitionReminderMail;
use App\Models\Competition;
use App\Models\CompetitorProfile;
use App\Models\Enrolment;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendCompetitionReminderEmailJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries = 1;

    public function __construct(public Competition $competition) {}

    public function handle(): void
    {
        $orgId         = $this->competition->organisation_id;
        $competitionId = $this->competition->id;

        $orgProfileIds = CompetitorProfile::where('organisation_id', $orgId)
            ->where('is_active', true)
            ->pluck('id');

        // Profile IDs that already have an enrolment for this competition
        $enrolledProfileIds = Enrolment::where('competition_id', $competitionId)
            ->whereIn('competitor_profile_id', $orgProfileIds)
            ->pluck('competitor_profile_id');

        // User IDs who have at least one enrolled profile (they've "registered")
        $registeredUserIds = CompetitorProfile::whereIn('id', $enrolledProfileIds)
            ->get()
            ->flatMap(fn ($p) => array_filter([$p->user_id, $p->owner_user_id]))
            ->unique();

        // All user IDs from org profiles
        $allUserIds = CompetitorProfile::whereIn('id', $orgProfileIds)
            ->get()
            ->flatMap(fn ($p) => array_filter([$p->user_id, $p->owner_user_id]))
            ->unique();

        $reminderUserIds = $allUserIds->diff($registeredUserIds);

        User::whereIn('id', $reminderUserIds)
            ->where('status', 'active')
            ->where('receive_competition_emails', true)
            ->get()
            ->each(fn (User $user) => Mail::to($user)->queue(
                new CompetitionReminderMail($this->competition)
            ));
    }
}
