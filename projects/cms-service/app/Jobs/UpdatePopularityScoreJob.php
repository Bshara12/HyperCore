<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdatePopularityScoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        private ?int $projectId = null  // null = كل المشاريع
    ) {}

    // ─────────────────────────────────────────────────────────────────

    public function handle(): void
    {
        $startTime = microtime(true);

        Log::info('UpdatePopularityScoreJob: starting', [
            'project_id' => $this->projectId ?? 'all',
        ]);

        /*
         * الصيغة:
         *   popularity_score = LOG10(ratings_count + 1) * 2.0
         *                    + LOG10(click_count + 1)   * 1.5
         *                    + ratings_avg * 0.5
         *
         * LOG10 لتخفيف التأثير: 1000 rating لا يطغى على 10 rating
         * ratings_avg لتمييز المحتوى عالي الجودة
         */
        $sql = '
            UPDATE search_indices si
            INNER JOIN data_entries de ON de.id = si.entry_id
            SET si.popularity_score = ROUND(
                (LOG10(COALESCE(de.ratings_count, 0) + 1) * 2.0)
                + (LOG10(si.click_count + 1) * 1.5)
                + (COALESCE(de.ratings_avg, 0) * 0.5),
                4
            )
        ';

        $bindings = [];

        if ($this->projectId !== null) {
            $sql .= ' WHERE si.project_id = ?';
            $bindings[] = $this->projectId;
        }

        $affected = DB::affectingStatement($sql, $bindings);

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('UpdatePopularityScoreJob: completed', [
            'project_id' => $this->projectId ?? 'all',
            'rows_updated' => $affected,
            'duration_ms' => $duration,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('UpdatePopularityScoreJob: failed', [
            'project_id' => $this->projectId ?? 'all',
            'error' => $e->getMessage(),
        ]);
    }
}
