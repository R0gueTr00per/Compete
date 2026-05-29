<?php

namespace App\Jobs;

use App\Models\Competition;
use App\Services\CompetitionInsightService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

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

        retry(2, fn () => $service->generate($this->competition, $this->generation), 2000);
    }
}
