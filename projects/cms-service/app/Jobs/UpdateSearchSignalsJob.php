<?php

namespace App\Jobs;

use Carbon\Carbon;
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

  public int $tries = 3;

  public int $timeout = 300;

  // public function handle(): void
  // {
  //     $start = microtime(true);

  //     Log::info('UpdateSearchSignalsJob: starting');

  //     $this->updateCtrScore();
  //     $this->updateFreshnessScore();
  //     $this->updatePopularityScore();

  //     Log::info('UpdateSearchSignalsJob: done', [
  //         'duration_ms' => round((microtime(true) - $start) * 1000),
  //     ]);
  // }

  public function handle(): void
  {
    $start = microtime(true);

    Log::info('UpdateSearchSignalsJob: starting');

    $isSqlite = DB::connection()->getDriverName() === 'sqlite';

    if ($isSqlite) {
      // ─── بيئة الاختبار (SQLite): المعالجة عبر PHP لتخطي قيود الدوال ───
      DB::table('search_indices')
        ->select('id', 'click_count', 'view_count', 'published_at')
        ->chunkById(500, function ($rows) {
          foreach ($rows as $row) {
            $clickCount = (float) ($row->click_count ?? 0);
            $viewCount = (float) ($row->view_count ?? 0);

            // 1. حساب CTR Score
            $ctrScore = round($clickCount / ($viewCount + 1.0), 4);

            // 2. حساب Freshness Score
            $now = Carbon::now()->startOfDay();
            $publishedAt = $row->published_at
              ? Carbon::parse($row->published_at)->startOfDay()
              : Carbon::now()->subDays(30)->startOfDay();

            $daysOld = $publishedAt->diffInDays($now);
            $freshnessScore = round(1.0 / ($daysOld + 1), 4);

            // 3. حساب Popularity Score (الدالة log في PHP تمثل اللوغاريتم الطبيعي كالمستخدم في MySQL)
            $popularityScore = round(
              (log($clickCount + 1) * 0.6) +
                (log($viewCount + 1) * 0.3) +
                ($freshnessScore * 0.1),
              4
            );

            DB::table('search_indices')
              ->where('id', $row->id)
              ->update([
                'ctr_score' => $ctrScore,
                'freshness_score' => $freshnessScore,
                'popularity_score' => $popularityScore,
              ]);
          }
        }, 'search_indices.id', 'id');
    } else {
      // ─── بيئة الإنتاج (MySQL): استعلامات Raw سريعة ومباشرة ───
      $this->updateCtrScore();
      $this->updateFreshnessScore();
      $this->updatePopularityScore();
    }

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
    DB::statement('
            UPDATE search_indices
            SET ctr_score = ROUND(
                CAST(click_count AS DECIMAL(10,4))
                / (CAST(view_count AS DECIMAL(10,4)) + 1.0),
                4
            )
        ');
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
    DB::statement('
            UPDATE search_indices
            SET freshness_score = ROUND(
                1.0 / (
                    DATEDIFF(NOW(), COALESCE(published_at, NOW() - INTERVAL 30 DAY))
                    + 1
                ),
                4
            )
        ');
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
    DB::statement('
            UPDATE search_indices
            SET popularity_score = ROUND(
                (LOG(click_count  + 1) * 0.6)
                + (LOG(view_count + 1) * 0.3)
                + (freshness_score     * 0.1),
                4
            )
        ');
  }

  public function failed(\Throwable $e): void
  {
    Log::error('UpdateSearchSignalsJob: failed', ['error' => $e->getMessage()]);
  }
}
