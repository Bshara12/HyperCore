<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * تحديث الإشارات المشتقّة في الفهرس.
 *
 * ─── ما تغيّر: أين تُحسب الإشارات ───────────────────────────────────
 *
 * كانت الأعمدة الثلاثة — ctr_score و freshness_score و popularity_score —
 * تُحسب هنا ويقرؤها المُرتِّب. وفي ذلك عيب بنيوي: الحداثة كمّية تتغيّر
 * كل ثانية، فتخزينها يعني أن ترتيب المحتوى الجديد يعتمد على متى جرت
 * المهمّة آخر مرّة لا على عمره الفعلي. ومقالٌ نُشر بعد آخر تشغيل يحمل
 * freshness_score صفراً حتى الساعة التالية.
 *
 * الآن يحسب SignalScorer الحداثة ونسبة النقر لحظياً من مصدرهما —
 * published_at و click_count و view_count — وهي أعمدة تُحدَّث فور وقوع
 * الحدث. فلا تقادم ولا انتظار مهمّة دورية.
 *
 * ─── فلماذا تبقى هذه المهمّة ────────────────────────────────────────
 *
 * popularity_score يخدم غرضاً آخر: ترتيب التصفّح حين لا يوجد استعلام
 * نصّي أصلاً (بحث بشروط بنيوية أو استثناءات وحدها). هناك لا توجد درجة
 * صلة تُرتَّب بها النتائج، فيلزم ترتيب مستقرّ محسوب مسبقاً — وحسابه
 * لحظياً على آلاف الصفوف داخل SQL أثقل بكثير من قراءته من عمود مفهرس.
 *
 * والعمودان الآخران يبقيان محدَّثَين للتحليلات ولوحات المتابعة، بالصيغ
 * نفسها التي يستعملها المُرتِّب كي لا يفترق التعريفان.
 */
class UpdateSearchSignalsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public function handle(): void
    {
        $started = microtime(true);

        $halfLife = max(1.0, (float) config('search.ranking.freshness_half_life_days', 45.0));

        DB::connection()->getDriverName() === 'sqlite'
            ? $this->updateWithPhp($halfLife)
            : $this->updateWithSql($halfLife);

        Log::info('UpdateSearchSignalsJob: done', [
            'duration_ms' => round((microtime(true) - $started) * 1000),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('UpdateSearchSignalsJob: failed', ['error' => $e->getMessage()]);
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * المسار السريع: تحديث واحد لكل الصفوف.
     *
     * الحداثة بانحلال أسّي بنصف عمر — الصيغة نفسها التي يستعملها
     * SignalScorer. الصيغة السابقة 1/(days+1) كانت تنهار بسرعة مفرطة:
     * محتوى عمره أسبوع يحتفظ بـ 12% فقط من قيمة محتوى اليوم، فيتحوّل
     * البحث فعلياً إلى ترتيب زمني.
     */
    private function updateWithSql(float $halfLife): void
    {
        DB::statement('
            UPDATE search_indices
            SET
                ctr_score = ROUND(
                    click_count / (view_count + 1.0),
                    4
                ),
                freshness_score = ROUND(
                    POW(
                        2,
                        -1.0 * DATEDIFF(NOW(), COALESCE(published_at, NOW())) / ?
                    ),
                    4
                )
        ', [$halfLife]);

        /*
         | الشعبية تُحسب بعد الحداثة لأنها تعتمد عليها.
         |
         | اللوغاريتم يخفّف هيمنة الأرقام الكبيرة: ألف نقرة لا تساوي
         | عشرة أضعاف مئة نقرة بل نحو مرّة ونصف. بدونه يحتكر المحتوى
         | الأقدم — وقد تراكمت نقراته على مدى شهور — صدارة كل تصفّح.
         */
        DB::statement('
            UPDATE search_indices
            SET popularity_score = ROUND(
                (LOG10(click_count + 1) * 0.6)
                + (LOG10(view_count + 1) * 0.3)
                + (freshness_score * 0.1),
                4
            )
        ');
    }

    /**
     * مسار SQLite للاختبارات: نفس الصيغ، منفَّذةً في PHP.
     *
     * SQLite لا يوفّر DATEDIFF ولا POW ولا LOG10 افتراضياً.
     */
    private function updateWithPhp(float $halfLife): void
    {
        $now = time();

        DB::table('search_indices')
            ->select('id', 'click_count', 'view_count', 'published_at')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($now, $halfLife) {
                foreach ($rows as $row) {
                    $clicks = (float) ($row->click_count ?? 0);
                    $views = (float) ($row->view_count ?? 0);

                    $ctr = round($clicks / ($views + 1.0), 4);
                    $freshness = round($this->freshness($row->published_at, $now, $halfLife), 4);

                    DB::table('search_indices')->where('id', $row->id)->update([
                        'ctr_score' => $ctr,
                        'freshness_score' => $freshness,
                        'popularity_score' => round(
                            (log10($clicks + 1) * 0.6)
                            + (log10($views + 1) * 0.3)
                            + ($freshness * 0.1),
                            4
                        ),
                    ]);
                }
            });
    }

    private function freshness(mixed $publishedAt, int $now, float $halfLife): float
    {
        if ($publishedAt === null) {
            return 0.0;
        }

        $timestamp = is_numeric($publishedAt)
            ? (int) $publishedAt
            : strtotime((string) $publishedAt);

        if ($timestamp === false) {
            return 0.0;
        }

        return 2 ** (-max(0.0, ($now - $timestamp) / 86400.0) / $halfLife);
    }
}
