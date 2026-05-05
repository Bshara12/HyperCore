<?php

namespace App\Console\Commands;

use App\Domains\Search\Repositories\Interfaces\SuggestionRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BuildSuggestionsCommand extends Command
{
    protected $signature = 'search:build-suggestions {--project=* : Project IDs to process}';

    protected $description = 'Build search suggestions table from existing search logs';

    public function __construct(
        private SuggestionRepositoryInterface $repository,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $projectIds = $this->option('project');

        if (empty($projectIds)) {
            $projectIds = DB::table('user_search_logs')
                ->distinct()
                ->pluck('project_id')
                ->toArray();
        }

        if (empty($projectIds)) {
            $this->warn('No projects found in user_search_logs.');

            return self::SUCCESS;
        }

        $this->info('Building suggestions for '.count($projectIds).' project(s)...');

        foreach ($projectIds as $projectId) {
            $this->info("Processing project {$projectId}...");

            $stats = $this->repository->buildFromSearchLogs((int) $projectId);

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Logs Processed', $stats['processed']],
                    ['Suggestions Upserted', $stats['upserted']],
                ]
            );
        }

        $this->info('✓ Done.');

        return self::SUCCESS;
    }
}
