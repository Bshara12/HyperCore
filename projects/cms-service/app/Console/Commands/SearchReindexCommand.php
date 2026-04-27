<?php

namespace App\Console\Commands;

use App\Domains\Search\Actions\ReindexSearchAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SearchReindexCommand extends Command
{
    protected $signature = 'search:reindex
                            {--force : Skip confirmation prompt}
                            {--chunk= : Override default chunk size (default: 100)}';

    protected $description = 'Rebuild the entire search index from published data entries';

    public function __construct(
        private ReindexSearchAction $reindexAction,
    ) {
        parent::__construct();
    }

    // ─────────────────────────────────────────────────────────────────

    public function handle(): int
    {
        $this->displayHeader();

        // ─── تحقق من الـ DB connection ───────────────────────────────
        if (!$this->checkDatabaseConnection()) {
            return self::FAILURE;
        }

        // ─── تأكيد قبل التنفيذ ───────────────────────────────────────
        if (!$this->option('force') && !$this->confirmReindex()) {
            $this->info('Reindex cancelled.');
            return self::SUCCESS;
        }

        // ─── تنفيذ إعادة الفهرسة ─────────────────────────────────────
        return $this->runReindex();
    }

    // ─────────────────────────────────────────────────────────────────

    private function runReindex(): int
    {
        $this->info('');
        $this->info('Starting reindex...');
        $this->info('');

        $startTime   = microtime(true);
        $progressBar = null;

        try {
            // ─── Progress callback ────────────────────────────────────
            $onProgress = function (int $processed, int $total) use (&$progressBar) {

                // أنشئ الـ progress bar عند أول استدعاء (لما نعرف الـ total)
                if ($progressBar === null && $total > 0) {
                    $progressBar = $this->output->createProgressBar($total);
                    $progressBar->setFormat(
                        ' %current%/%max% [%bar%] %percent:3s%% — %message%'
                    );
                    $progressBar->start();
                }

                if ($progressBar !== null) {
                    $progressBar->setMessage("Processed {$processed} of {$total}");
                    $progressBar->setProgress($processed);
                }
            };

            $stats = $this->reindexAction->execute($onProgress);

            // أنهِ الـ progress bar
            if ($progressBar !== null) {
                $progressBar->finish();
                $this->info('');
            }

            // ─── عرض النتائج ─────────────────────────────────────────
            $this->displayResults($stats, microtime(true) - $startTime);

            return self::SUCCESS;

        } catch (Throwable $e) {
            if ($progressBar !== null) {
                $progressBar->finish();
                $this->info('');
            }

            $this->error('');
            $this->error('Reindex failed: ' . $e->getMessage());

            if ($this->getOutput()->isVerbose()) {
                $this->error($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Display Helpers
    // ─────────────────────────────────────────────────────────────────

    private function displayHeader(): void
    {
        $this->info('');
        $this->info('╔══════════════════════════════════════╗');
        $this->info('║      Search Index Rebuilder          ║');
        $this->info('╚══════════════════════════════════════╝');
        $this->info('');

        // معلومات البيئة
        $this->table(
            ['Setting', 'Value'],
            [
                ['Environment', app()->environment()],
                ['Database',    config('database.connections.' . config('database.default') . '.database')],
                ['Queue',       config('queue.default')],
            ]
        );
    }

    private function confirmReindex(): bool
    {
        $this->warn('');
        $this->warn('⚠  This will TRUNCATE the search_indices table and rebuild it.');
        $this->warn('   All existing search data will be lost during the process.');
        $this->warn('');

        return $this->confirm('Are you sure you want to continue?');
    }

    private function displayResults(array $stats, float $elapsedSeconds): void
    {
        $this->info('');
        $this->info('✓ Reindex completed successfully.');
        $this->info('');

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total entries processed', number_format($stats['total'])],
                ['Successfully indexed',    number_format($stats['indexed'])],
                ['Skipped (no content)',    number_format($stats['skipped'])],
                ['Time elapsed',            round($elapsedSeconds, 2) . 's'],
                ['Throughput',              round($stats['total'] / max($elapsedSeconds, 0.01)) . ' entries/sec'],
            ]
        );

        // تحقق من الـ search_indices
        $indexedCount = DB::table('search_indices')->count();
        $this->info("Search index now contains: {$indexedCount} records.");
        $this->info('');
    }

    private function checkDatabaseConnection(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (Throwable $e) {
            $this->error('Cannot connect to database: ' . $e->getMessage());
            return false;
        }
    }
}