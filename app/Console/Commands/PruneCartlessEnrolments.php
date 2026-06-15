<?php

namespace App\Console\Commands;

use App\Models\Enrolment;
use Illuminate\Console\Command;

class PruneCartlessEnrolments extends Command
{
    protected $signature   = 'compete:prune-cartless-enrolments {--dry-run : List affected rows without deleting}';
    protected $description = 'Delete enrolments that have no associated cart (cart_id is null)';

    public function handle(): int
    {
        $query = Enrolment::withTrashed()->whereNull('cart_id');

        $count = $query->count();

        if ($count === 0) {
            $this->info('No cart-less enrolments found.');
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("Dry run: {$count} enrolment(s) would be deleted.");
            $query->select(['id', 'competition_id', 'competitor_profile_id', 'status', 'created_at'])
                ->each(fn ($e) => $this->line("  id={$e->id}  competition_id={$e->competition_id}  status={$e->status}  created={$e->created_at}"));
            return self::SUCCESS;
        }

        if (! $this->confirm("{$count} enrolment(s) will be permanently deleted. Continue?")) {
            return self::SUCCESS;
        }

        $query->forceDelete();

        $this->info("Deleted {$count} cart-less enrolment(s).");
        return self::SUCCESS;
    }
}
