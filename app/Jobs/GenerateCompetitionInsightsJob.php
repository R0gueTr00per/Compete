<?php

namespace App\Jobs;

use App\Mail\CompetitionInsightsMail;
use App\Models\Competition;
use App\Models\CompetitionInsight;
use App\Services\CompetitionInsightService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class GenerateCompetitionInsightsJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(
        public Competition $competition,
        public int $generation = 0,
    ) {}

    public static function dispatchFor(Competition $competition): void
    {
        $generation = (int) Cache::increment("insights_gen_{$competition->id}");
        self::dispatchSync($competition, $generation);
    }

    public function handle(CompetitionInsightService $service): void
    {
        if (! config('services.google_ai.api_key')) {
            return;
        }

        $insight = retry(2, fn () => $service->generate($this->competition, $this->generation), 2000);

        if ($insight && ($this->competition->organisation->auto_email_insights ?? true)) {
            $this->emailOrgAdmins($insight);
        }
    }

    private function emailOrgAdmins(CompetitionInsight $insight): void
    {
        $this->competition->organisation
            ->memberships()
            ->where('role', 'administrator')
            ->where('status', 'active')
            ->with('user')
            ->get()
            ->each(fn ($membership) =>
                Mail::to($membership->user)->send(
                    new CompetitionInsightsMail($this->competition, $insight)
                )
            );
    }
}
