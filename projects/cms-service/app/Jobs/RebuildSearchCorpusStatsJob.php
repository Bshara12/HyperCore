<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\Search\Support\Text\Segmenter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * إعادة حساب إحصاءات المتن — مدخلات BM25.
 *
 * ─── لماذا مهمّة دورية لا حساب لحظي ────────────────────────────────
 *
 * IDF يحتاج عدد المستندات الحاوية لكل مصطلح. حسابه لحظياً يعني عبارة
 * COUNT لكل مصطلح في كل بحث — ستّة استعلامات تجميعية قبل أن يبدأ
 * البحث الفعلي، على جدول قد يبلغ ملايين الصفوف.
 *
 * والإحصاءات تتغيّر بمعدّل تغيّر المحتوى لا بمعدّل البحث. مشروع يُنشر
 * فيه عشرة مقالات يومياً وتُجرى فيه مئة ألف عملية بحث يحسبها لحظياً
 * مئة ألف مرة ليحصل على القيمة نفسها. فمكانها الطبيعي مهمّة دورية.
 *
 * ─── دقّة الإحصاءات ─────────────────────────────────────────────────
 *
 * الإحصاء المتأخّر بساعات لا يضرّ: IDF يقيس ندرة نسبية، وندرة مصطلح
 * لا تنقلب بإضافة عشرة مستندات إلى عشرة آلاف. المهمّ ألّا تكون
 * الإحصاءات غائبة كلياً — ولهذا يوفّر CorpusStatistics بديلاً معقولاً
 * يمنع انهيار الترتيب إلى صفر في المشروع الجديد.
 */
class RebuildSearchCorpusStatsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 1800;

    /**
     * كل المصطلحات تُخزَّن، بما فيها ما ورد في مستند واحد.
     *
     * ─── لماذا لا نُسقط الذيل الطويل ────────────────────────────────
     *
     * كانت هنا عتبة MIN_DOCUMENT_FREQUENCY = 2، ومبرّرها من زاوية IDF
     * سليم: مصطلح بتكرار 1 يحصل على القيمة نفسها التي يمنحها
     * CorpusStatistics للمصطلح المجهول، فتخزينه لا يضيف شيئاً للترتيب.
     *
     * لكن الجدول يخدم غرضاً ثانياً: هو مفردات التصحيح الإملائي. ومن
     * زاوية التصحيح ينقلب الحكم تماماً — المصطلحات النادرة هي بالضبط
     * ما يخطئ الناس في كتابته: أسماء المنتجات والماركات والموديلات.
     *
     * وقد ظهر الأثر عملياً: "smartphoen" لم تُصحَّح إلى "smartphones"
     * لأن الأخيرة وردت في مستند واحد فأُسقطت من المفردات — أي أننا
     * حذفنا الجواب ثم عجزنا عن إيجاده.
     *
     * وكلفة الاحتفاظ محدودة: حجم المفردات ينمو بجذر عدد المستندات لا
     * خطّياً معه (قانون Heaps)، فمليون مستند يعطي مئات الآلاف من
     * المصطلحات لا الملايين.
     */
    private const MIN_DOCUMENT_FREQUENCY = 1;

    /**
     * سقف المصطلحات لكل (مشروع، لغة).
     *
     * حارس ضدّ المحتوى المولَّد آلياً أو التالف الذي قد يُنتج مفردات
     * غير محدودة. تُبقى الأعلى تكراراً، وهي الأنفع للترتيب وللتصحيح معاً.
     */
    private const MAX_TERMS_PER_PARTITION = 200000;

    private const CHUNK_SIZE = 500;

    public function __construct(
        private readonly ?int $projectId = null,
    ) {}

    public function handle(): void
    {
        $started = microtime(true);

        foreach ($this->partitions() as $partition) {
            $this->rebuildPartition(
                (int) $partition->project_id,
                (string) $partition->language
            );
        }

        Log::info('RebuildSearchCorpusStatsJob: done', [
            'project_id' => $this->projectId,
            'duration_ms' => round((microtime(true) - $started) * 1000),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('RebuildSearchCorpusStatsJob: failed', ['error' => $e->getMessage()]);
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * أزواج (مشروع، لغة) الموجودة في الفهرس.
     *
     * @return Collection<int, \stdClass>
     */
    private function partitions()
    {
        $query = DB::table('search_indices')
            ->select('project_id', 'language')
            ->where('status', 'published')
            ->groupBy('project_id', 'language');

        if ($this->projectId !== null) {
            $query->where('project_id', $this->projectId);
        }

        return $query->get();
    }

    private function rebuildPartition(int $projectId, string $language): void
    {
        $documentCount = 0;
        $titleTerms = 0;
        $contentTerms = 0;
        $metaTerms = 0;

        /** @var array<string, int> $frequencies */
        $frequencies = [];

        DB::table('search_indices')
            ->select('id', 'title_fold', 'content_fold', 'meta_fold', 'title_terms', 'content_terms', 'meta_terms')
            ->where('project_id', $projectId)
            ->where('language', $language)
            ->where('status', 'published')
            ->orderBy('id')
            ->chunkById(self::CHUNK_SIZE, function ($rows) use (
                &$documentCount, &$titleTerms, &$contentTerms, &$metaTerms, &$frequencies
            ) {
                foreach ($rows as $row) {
                    $documentCount++;
                    $titleTerms += (int) $row->title_terms;
                    $contentTerms += (int) $row->content_terms;
                    $metaTerms += (int) $row->meta_terms;

                    /*
                     | التكرار مستندي لا إجمالي: كل مصطلح يُحتسب مرّة
                     | واحدة لكل مستند مهما تكرّر فيه. هذا هو تعريف df
                     | في BM25، والخلط بينه وبين التكرار الإجمالي يجعل
                     | مستنداً واحداً يكرّر كلمة مئة مرة يبدو كأن مئة
                     | مستند تحتويها — فتفقد الكلمة تمييزها كلّه.
                     */
                    $seen = [];

                    foreach ([$row->title_fold, $row->content_fold, $row->meta_fold] as $text) {
                        if ($text === null || $text === '') {
                            continue;
                        }

                        foreach (Segmenter::tokenize((string) $text) as $token) {
                            $seen[$token] = true;
                        }
                    }

                    foreach (array_keys($seen) as $token) {
                        $frequencies[$token] = ($frequencies[$token] ?? 0) + 1;
                    }
                }
            });

        if ($documentCount === 0) {
            $this->clearPartition($projectId, $language);

            return;
        }

        $this->writeCorpusStats(
            $projectId,
            $language,
            $documentCount,
            $titleTerms / $documentCount,
            $contentTerms / $documentCount,
            $metaTerms / $documentCount
        );

        $this->writeTermStats($projectId, $language, $frequencies);
    }

    private function writeCorpusStats(
        int $projectId,
        string $language,
        int $documentCount,
        float $avgTitle,
        float $avgContent,
        float $avgMeta
    ): void {
        DB::table('search_corpus_stats')->updateOrInsert(
            ['project_id' => $projectId, 'language' => $language],
            [
                'doc_count' => $documentCount,
                'avg_title_terms' => round($avgTitle, 4),
                'avg_content_terms' => round($avgContent, 4),
                'avg_meta_terms' => round($avgMeta, 4),
                'computed_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    /**
     * @param  array<string, int>  $frequencies
     */
    private function writeTermStats(int $projectId, string $language, array $frequencies): void
    {
        /*
         | الاستبدال الكامل لا التحديث التزايدي.
         |
         | التحديث وحده يترك مصطلحات المحتوى المحذوف قائمةً بتكرارها
         | القديم إلى الأبد، فتبدو نادرةً أو شائعةً خطأً. والحذف ثم
         | الإدراج داخل معاملة واحدة يبقي الجدول متّسقاً طوال العملية.
         */
        DB::transaction(function () use ($projectId, $language, $frequencies) {
            DB::table('search_term_stats')
                ->where('project_id', $projectId)
                ->where('language', $language)
                ->delete();

            $rows = [];
            $now = now();

            // الأعلى تكراراً أولاً كي يقع القصّ على الذيل لا على الرأس.
            arsort($frequencies);
            $frequencies = array_slice($frequencies, 0, self::MAX_TERMS_PER_PARTITION, true);

            foreach ($frequencies as $term => $frequency) {
                if ($frequency < self::MIN_DOCUMENT_FREQUENCY) {
                    continue;
                }

                $rows[] = [
                    'project_id' => $projectId,
                    'language' => $language,
                    'term' => mb_substr((string) $term, 0, 64, 'UTF-8'),
                    'doc_freq' => $frequency,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::table('search_term_stats')->insert($chunk);
            }
        });
    }

    private function clearPartition(int $projectId, string $language): void
    {
        DB::table('search_corpus_stats')
            ->where('project_id', $projectId)
            ->where('language', $language)
            ->delete();

        DB::table('search_term_stats')
            ->where('project_id', $projectId)
            ->where('language', $language)
            ->delete();
    }
}
