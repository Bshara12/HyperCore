<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateSearchSignalsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 300;

    public function handle(): void
    {
        $start = microtime(true);

        Log::info('UpdateSearchSignalsJob: starting');

        $this->updateCtrScore();
        $this->updateFreshnessScore();
        $this->updatePopularityScore();

        Log::info('UpdateSearchSignalsJob: done', [
            'duration_ms' => round((microtime(true) - $start) * 1000),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * CTR Score = click_count / (view_count + 1)
     *
     * +1 يمنع division by zero
     * ROUND لتوفير storage
     */
    private function updateCtrScore(): void
    {
        DB::statement("
            UPDATE search_indices
            SET ctr_score = ROUND(
                CAST(click_count AS DECIMAL(10,4))
                / (CAST(view_count AS DECIMAL(10,4)) + 1.0),
                4
            )
        ");
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * Freshness Score = decay based on age
     *
     * score = 1 / (days_old + 1)
     * اليوم:         1/(0+1) = 1.000
     * أسبوع:         1/(7+1) = 0.125
     * شهر:           1/(30+1) = 0.032
     * NULL published: يُعامل كـ 30 يوم قديم
     */
    private function updateFreshnessScore(): void
    {
        DB::statement("
            UPDATE search_indices
            SET freshness_score = ROUND(
                1.0 / (
                    DATEDIFF(NOW(), COALESCE(published_at, NOW() - INTERVAL 30 DAY))
                    + 1
                ),
                4
            )
        ");
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * Popularity Score - الصيغة المُصلَّحة
     *
     * popularity = LOG(click_count + 1) * 0.6
     *            + LOG(view_count  + 1) * 0.3
     *            + freshness_score      * 0.1
     *
     * LOG لتخفيف هيمنة الأرقام الكبيرة:
     *   click=1000 vs click=100 → ليس 10x بل LOG10(1001)/LOG10(101) ≈ 1.5x
     *
     * freshness_score يُعطي ميزة للمحتوى الجديد
     */
    private function updatePopularityScore(): void
    {
        DB::statement("
            UPDATE search_indices
            SET popularity_score = ROUND(
                (LOG(click_count  + 1) * 0.6)
                + (LOG(view_count + 1) * 0.3)
                + (freshness_score     * 0.1),
                4
            )
        ");
    }

    public function failed(\Throwable $e): void
    {
        Log::error('UpdateSearchSignalsJob: failed', ['error' => $e->getMessage()]);
    }
}