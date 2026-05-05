<?php

namespace App\Console\Commands;

use App\Domains\Search\Services\PopularSearchService;
use Illuminate\Console\Command;

class RecomputePopularSearchesCommand extends Command
{
    protected $signature = 'search:recompute-popular
                            {--project= : Project ID (all projects if omitted)}
                            {--force   : Skip confirmation}';

    protected $description = 'Recompute popular searches from user_search_logs';

    public function __construct(
        private PopularSearchService $service,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $projectId = $this->option('project')
            ? (int) $this->option('project')
            : null;

        if (! $this->option('force')) {
            $scope = $projectId ? "project {$projectId}" : 'ALL projects';
            if (! $this->confirm("Recompute popular searches for {$scope}?")) {
                $this->info('Cancelled.');

                return self::SUCCESS;
            }
        }

        $this->info('Recomputing popular searches...');
        $startTime = microtime(true);

        $results = $this->service->recompute($projectId);

        $totalProcessed = array_sum(array_column(
            array_column($results, 'stats'), 'processed'
        ));

        $totalUpserted = array_sum(array_column(
            array_column($results, 'stats'), 'upserted'
        ));

        $this->table(
            ['Project', 'Language', 'Processed', 'Upserted', 'Duration'],
            array_map(fn ($r) => [
                $r['project_id'],
                $r['language'],
                $r['stats']['processed'],
                $r['stats']['upserted'],
                $r['stats']['duration_ms'].'ms',
            ], $results)
        );

        $totalMs = round((microtime(true) - $startTime) * 1000, 2);

        $this->info("✓ Done. Total: {$totalProcessed} processed, {$totalUpserted} upserted in {$totalMs}ms");

        return self::SUCCESS;
    }
}
