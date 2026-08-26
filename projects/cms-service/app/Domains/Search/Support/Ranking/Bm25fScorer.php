<?php

declare(strict_types=1);

namespace App\Domains\Search\Support\Ranking;

use App\Domains\Search\Support\Query\QueryPlan;
use App\Domains\Search\Support\Text\Segmenter;

/**
 * Bm25fScorer — درجة الصلة النصّية.
 *
 * ─── ما الذي حلّ محلّه ──────────────────────────────────────────────
 *
 * كانت الصلة تُشتقّ من قيمة MATCH() الخام في MySQL مضروبة في ثابت،
 * ثم تُضاف إليها مكافآت مكتوبة يدوياً:
 *
 *     الكلمة في العنوان   +2.0
 *     الكلمة في المتن     +0.5
 *     العبارة في العنوان  +8.0
 *
 * وفي هذا ثلاث علل:
 *
 *   1. أرقام بلا معنى. لماذا 2.0 لا 3.0؟ لا أحد يعرف، ولا سبيل
 *      لمعرفته: لا تعبّر الأرقام عن كمّية قابلة للقياس.
 *
 *   2. لا حسّ بالندرة. مطابقة "the" في العنوان تساوي مطابقة
 *      "titanium" فيه — رغم أن الأولى لا تخبرنا شيئاً والثانية
 *      تكاد تحدّد المستند وحدها.
 *
 *   3. لا تطبيع للطول. مقال من ألف كلمة يذكر الكلمة عرضاً يتفوّق
 *      على منتج عنوانه الكلمة نفسها، لمجرّد امتلاكه مساحة أكبر
 *      لتكرارها.
 *
 * ─── BM25F ──────────────────────────────────────────────────────────
 *
 * الصيغة القياسية في استرجاع المعلومات منذ عقود، والحرف F للحقول:
 *
 *     score(q,d) = Σ  idf(t) · ( tf'(t,d) · (k1+1) ) / ( tf'(t,d) + k1 )
 *                 t∈q
 *
 *     tf'(t,d)   = Σ  w_f · tf(t,f) / ( 1 - b + b · len(f)/avglen(f) )
 *                 f
 *
 * والفرق الجوهري عن BM25 العادي أن التشبّع يُطبَّق بعد جمع الحقول لا
 * قبله. لو حُسب كل حقل على حدة ثم جُمعت النتائج، لأمكن لمستند أن
 * يكدّس الدرجات بتكرار الكلمة في كل حقل — وهو بالضبط الباب الذي
 * تدخل منه حشوةُ الكلمات المفتاحية.
 */
final class Bm25fScorer
{
    /**
     * @var array<string, float>
     */
    private array $fieldWeights;

    private float $k1;

    private float $b;

    public function __construct()
    {
        $this->k1 = (float) config('search.ranking.bm25.k1', 1.2);
        $this->b = (float) config('search.ranking.bm25.b', 0.75);

        $this->fieldWeights = [
            'title' => (float) config('search.ranking.field_weights.title', 5.0),
            'content' => (float) config('search.ranking.field_weights.content', 1.0),
            'meta' => (float) config('search.ranking.field_weights.meta', 2.0),
        ];
    }

    /**
     * درجة الصلة بين خطة ومستند مرشَّح.
     *
     * @param  object  $row  صفّ الفهرس كما عاد من قاعدة البيانات
     */
    public function score(QueryPlan $plan, object $row, CorpusStatistics $stats): float
    {
        $fields = $this->fieldTokens($row);

        if ($fields === []) {
            return 0.0;
        }

        $lengths = [
            'title' => max(1, (int) ($row->title_terms ?? count($fields['title'] ?? []))),
            'content' => max(1, (int) ($row->content_terms ?? count($fields['content'] ?? []))),
            'meta' => max(1, (int) ($row->meta_terms ?? count($fields['meta'] ?? []))),
        ];

        $score = 0.0;

        foreach ($plan->terms as $term) {
            $score += $this->termScore($term, $fields, $lengths, $stats, 1.0);
        }

        /*
         | التوسعات بنصف الوزن.
         |
         | من كتب "ايفون" يريد الآيفون، لكن مطابقة "iphone" استنتاجٌ
         | منّا لا نصٌّ منه. مساواتها بما كتبه فعلاً تجعل مستنداً
         | يطابق استنتاجنا وحده يتفوّق على مستند يطابق كلماته هو.
         */
        foreach ($plan->expansions as $expansion) {
            $score += $this->termScore($expansion, $fields, $lengths, $stats, 0.5);
        }

        return $score;
    }

    /**
     * مكافأة تجاور العبارة.
     *
     * BM25 لا يرى الترتيب — يعدّ التكرارات فقط. فمستند يذكر "iphone"
     * في مقدّمته و"pro" في خاتمته يتساوى عنده مع مستند عنوانه
     * "iPhone Pro". هذه المكافأة تعوّض ذلك الفارق تحديداً.
     *
     * التطبيق على العنوان بضعف قوّته: عبارة كاملة في العنوان أقوى
     * دليل صلة يمكن أن يقدّمه مستند.
     */
    public function phraseBonus(QueryPlan $plan, object $row): float
    {
        if ($plan->phrases === []) {
            return 0.0;
        }

        $weight = (float) config('search.ranking.signals.exact_phrase', 3.0);
        $title = (string) ($row->title_fold ?? '');
        $content = (string) ($row->content_fold ?? '');

        foreach ($plan->phrases as $phrase) {
            if ($phrase === '') {
                continue;
            }

            if (str_contains($title, $phrase)) {
                return $weight * 2.0;
            }

            if (str_contains($content, $phrase)) {
                return $weight;
            }
        }

        return 0.0;
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * درجة مصطلح واحد عبر كل الحقول.
     *
     * @param  array<string, array<string, int>>  $fields  الحقل => (الوحدة => تكرارها)
     * @param  array<string, int>  $lengths
     */
    private function termScore(
        string $term,
        array $fields,
        array $lengths,
        CorpusStatistics $stats,
        float $weight
    ): float {
        $accumulated = 0.0;

        foreach ($this->fieldWeights as $field => $fieldWeight) {
            $frequency = $fields[$field][$term] ?? 0;

            if ($frequency === 0) {
                continue;
            }

            /*
             | تطبيع الطول: يقسم التكرار على طول الحقل نسبةً إلى
             | متوسّطه. بدونه تفوز المستندات الطويلة دائماً لأن
             | فرصة ورود أي كلمة فيها أكبر — لا لأنها أوثق صلة.
             */
            $normalization = 1.0 - $this->b
                + $this->b * ($lengths[$field] / $stats->averageLengthFor($field));

            $accumulated += $fieldWeight * ($frequency / max(0.01, $normalization));
        }

        if ($accumulated <= 0.0) {
            return 0.0;
        }

        // التشبّع يُطبَّق بعد الجمع — وهو ما يمنع تكديس الدرجات عبر الحقول.
        $saturated = ($accumulated * ($this->k1 + 1.0)) / ($accumulated + $this->k1);

        return $stats->inverseDocumentFrequency($term) * $saturated * $weight;
    }

    /**
     * تكرارات الوحدات لكل حقل في المستند.
     *
     * الحقول الثلاثة مخزَّنة مطبَّعة ومنفصلة، فيكفي تقسيم كل منها.
     * وهذا الفصل هو ما يجعل أوزان الحقول ذات أثر فعلي: لو دُمج
     * meta في content لصار الوزن الثالث حبراً على ورق.
     *
     * @return array<string, array<string, int>>
     */
    private function fieldTokens(object $row): array
    {
        $title = (string) ($row->title_fold ?? '');
        $content = (string) ($row->content_fold ?? '');
        $meta = (string) ($row->meta_fold ?? '');

        if ($title === '' && $content === '' && $meta === '') {
            return [];
        }

        return [
            'title' => array_count_values(Segmenter::tokenize($title)),
            'content' => array_count_values(Segmenter::tokenize($content)),
            'meta' => array_count_values(Segmenter::tokenize($meta)),
        ];
    }
}
