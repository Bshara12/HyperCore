<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PruneNotificationFailedJobsCommand extends Command
{
    protected $signature = 'notifications:prune-failed {--days=14}';

    protected $description = 'Prune failed queue jobs using the configured queue failer';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $before = now()->subDays($days);

        $failer = app('queue.failer');

        // if (! method_exists($failer, 'prune')) {
        //     $this->warn('Configured failed-job provider does not expose prune().');

        //     return self::SUCCESS;
        // }

        $deleted = $failer->prune($before);

        $this->info("Pruned failed jobs: {$deleted}");

        return self::SUCCESS;
    }
}
