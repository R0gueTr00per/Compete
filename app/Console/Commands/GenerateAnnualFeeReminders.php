<?php

namespace App\Console\Commands;

use App\Models\Organisation;
use App\Models\OrganisationAnnualFeeReminder;
use Illuminate\Console\Command;

class GenerateAnnualFeeReminders extends Command
{
    protected $signature   = 'compete:generate-annual-fee-reminders';
    protected $description = 'Create a dismissible reminder 30 days before each Org\'s annual fee is due';

    public function handle(): int
    {
        $created = 0;

        Organisation::active()
            ->whereNotNull('annual_fee')
            ->where('annual_fee', '>', 0)
            ->whereNotNull('annual_fee_anniversary_date')
            ->get()
            ->each(function (Organisation $org) use (&$created) {
                $dueDate = $org->nextAnnualFeeDueDate();
                if (! $dueDate || now()->diffInDays($dueDate, absolute: true) > 30) {
                    return;
                }

                $exists = OrganisationAnnualFeeReminder::where('organisation_id', $org->id)
                    ->whereDate('due_date', $dueDate->toDateString())
                    ->exists();

                if ($exists) {
                    return;
                }

                OrganisationAnnualFeeReminder::create([
                    'organisation_id' => $org->id,
                    'due_date'        => $dueDate->toDateString(),
                    'amount'          => $org->annual_fee,
                ]);
                $created++;
            });

        $this->info("Created {$created} annual fee reminder(s).");

        return self::SUCCESS;
    }
}
