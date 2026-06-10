<?php

namespace App\Jobs;

use App\Models\Competition;
use App\Models\User;
use App\Services\EnrolmentSummaryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class GenerateEnrolmentSummariesJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 120;

    public function __construct(
        public Competition $competition,
    ) {}

    public static function dispatchFor(Competition $competition): void
    {
        self::dispatch($competition);
    }

    public function handle(EnrolmentSummaryService $service): void
    {
        if (! config('services.google_ai.api_key')) {
            return;
        }

        try {
            $service->generateForCompetition($this->competition);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[EnrolmentSummary] job failed', ['error' => $e->getMessage()]);
            $this->notifySysadmin('Enrolment summaries generation failed', $e);
        } finally {
            \Illuminate\Support\Facades\Cache::forget("summaries_generating_{$this->competition->id}");
        }
    }

    private function notifySysadmin(string $subject, \Throwable $e): void
    {
        $emails = User::role('system_admin')->where('status', 'active')->pluck('email')->toArray();
        if (empty($emails)) {
            return;
        }

        $body = implode("\n\n", [
            "Competition: {$this->competition->name} (ID {$this->competition->id})",
            "Error: " . $e->getMessage(),
            "Trace:\n" . $e->getTraceAsString(),
        ]);

        try {
            Mail::raw($body, fn ($m) => $m->to($emails)->subject("[Kompetic] {$subject}"));
        } catch (\Throwable) {}
    }
}
