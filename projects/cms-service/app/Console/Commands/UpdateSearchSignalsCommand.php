<?php

namespace App\Console\Commands;

use App\Jobs\UpdateSearchSignalsJob;
use Illuminate\Console\Command;

class UpdateSearchSignalsCommand extends Command
{
    protected $signature   = 'search:update-signals {--sync : Run synchronously}';
    protected $description = 'Recompute ctr_score, freshness_score, popularity_score';

    public function handle(): int
    {
        if ($this->option('sync')) {
            $this->info('Running synchronously...');
            (new UpdateSearchSignalsJob())->handle();
            $this->info('Done.');
            return self::SUCCESS;
        }

        UpdateSearchSignalsJob::dispatch()->onQueue('search-maintenance');
        $this->info('Job dispatched to search-maintenance queue.');

        return self::SUCCESS;
    }
}