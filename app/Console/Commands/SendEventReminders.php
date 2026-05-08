<?php
namespace App\Console\Commands;

use App\Models\Competition;
use App\Notifications\EventReminderNotification;
use Illuminate\Console\Command;

class SendEventReminders extends Command
{
    protected $signature   = 'compete:send-reminders';
    protected $description = 'Send 7-day reminder emails to enrolled competitors';

    public function handle(): int
    {
        $targetDate = now()->addDays(7)->toDateString();

        $competitions = Competition::where('competition_date', $targetDate)
            ->whereIn('status', ['open', 'running'])
            ->with(['enrolments.competitor'])
            ->get();

        if ($competitions->isEmpty()) {
            $this->info('No competitions in 7 days.');
            return self::SUCCESS;
        }

        $sent = 0;
        foreach ($competitions as $competition) {
            foreach ($competition->enrolments as $enrolment) {
                if ($enrolment->competitor && $enrolment->activeEvents()->exists()) {
                    $enrolment->competitor->notify(
                        new EventReminderNotification($competition, $enrolment)
                    );
                    $sent++;
                }
            }
        }

        $this->info("Sent {$sent} reminder(s) for " . $competitions->count() . " competition(s).");
        return self::SUCCESS;
    }
}
