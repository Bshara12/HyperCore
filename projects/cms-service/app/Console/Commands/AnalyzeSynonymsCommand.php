<?php

namespace App\Console\Commands;

use App\Domains\Search\Services\SynonymAnalysisService;
use Illuminate\Console\Command;

class AnalyzeSynonymsCommand extends Command
{
    protected $signature = 'search:analyze-synonyms
                            {--project=  : Project ID}
                            {--lang=en   : Language}
                            {--days=90   : Number of days to analyze}
                            {--min=2     : Minimum search count per keyword}';

    protected $description = 'Analyze search logs to discover synonym candidates';

    public function __construct(
        private SynonymAnalysisService $service,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $projectId = (int) $this->option('project');
        $language = $this->option('lang');
        $days = (int) $this->option('days');
        $minCount = (int) $this->option('min');

        if (! $projectId) {
            $this->error('--project is required');

            return self::FAILURE;
        }

        $this->info("Analyzing synonyms for project {$projectId} ({$language})...");
        $result = $this->service->analyze($projectId, $language, $days, $minCount);

        $this->table(
            ['Metric', 'Value'],
            [
                ['Keywords Analyzed',     $result->keywordsAnalyzed],
                ['Unique Words Found',    $result->uniqueWordsFound],
                ['Pairs Evaluated',       $result->pairsEvaluated],
                ['Suggestions Generated', $result->suggestionsGenerated],
                ['Duration',              round($result->durationMs, 2).'ms'],
            ]
        );

        if (! empty($result->suggestions)) {
            $this->info("\nTop suggestions:");
            $this->table(
                ['Word A', 'Word B', 'Jaccard', 'Co-occur', 'Confidence'],
                array_map(fn ($s) => [
                    $s->wordA,
                    $s->wordB,
                    round($s->jaccardScore, 4),
                    $s->cooccurrenceCount,
                    round($s->confidenceScore, 4),
                ], $result->suggestions)
            );
        }

        return self::SUCCESS;
    }
}
