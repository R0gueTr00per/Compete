<?php

namespace App\Console\Commands;

use App\Models\EnrolmentCart;
use Illuminate\Console\Command;

class BackfillPlatformFee extends Command
{
    protected $signature   = 'compete:backfill-platform-fee {--dry-run}';
    protected $description = 'Recalculate total_amount on submitted carts so it correctly includes their stored platform_fee_rate';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $carts = EnrolmentCart::where('status', 'submitted')
            ->with(['competition.organisation', 'enrolments'])
            ->get();

        $updated = 0;

        foreach ($carts as $cart) {
            $rate = (float) ($cart->platform_fee_rate ?? $cart->competition?->organisation?->platform_fee ?? 0);

            $activeEnrolments = $cart->enrolments->whereNotIn('status', ['draft', 'withdrawn']);
            $expectedTotal    = (float) $activeEnrolments->sum('fee_calculated') + ($activeEnrolments->count() * $rate);
            $currentTotal     = (float) $cart->total_amount;

            if (abs($expectedTotal - $currentTotal) < 0.01) {
                continue;
            }

            $this->line(sprintf(
                'Cart #%d (org: %s, rate %.2f): total_amount %.2f -> %.2f',
                $cart->id,
                $cart->competition?->organisation?->name ?? '?',
                $rate,
                $currentTotal,
                $expectedTotal
            ));

            if (! $dryRun) {
                $cart->forceFill(['total_amount' => $expectedTotal])->save();
            }

            $updated++;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Updated {$updated} of {$carts->count()} submitted cart(s).");

        return self::SUCCESS;
    }
}
