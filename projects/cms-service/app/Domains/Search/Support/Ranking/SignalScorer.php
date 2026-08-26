<?php

declare(strict_types=1);

namespace App\Domains\Search\Support\Ranking;

use App\Domains\Search\Support\Query\AttributeFilter;
use App\Domains\Search\Support\Query\QueryPlan;

/**
 * SignalScorer — الإشارات غير النصّية: السلوك، الحداثة، مطابقة الشروط.
 *
 * ─── المبدأ الحاكم: الصلة أولاً ─────────────────────────────────────
 *
 * كل ما هنا يُضاف إلى درجة BM25 بمقادير محدودة عمداً. الإصدار السابق
 * كان يضيف log(clicks+1)*2.5 بلا سقف، فمستند بألف نقرة يحصل على 17
 * نقطة إضافية — أكثر مما تمنحه مطابقة نصّية تامّة. والنتيجة أن الشعبية
 * كانت تختطف الترتيب: أكثر المستندات نقراً يتصدّر كل بحث تقريباً، بما
 * في ذلك بحوث لا صلة له بها.
 *
 * وهذا يخلق حلقة راجعة مغلقة: ما يتصدّر يُنقر، وما يُنقر يتصدّر أكثر.
 * فيتجمّد الترتيب على ما كان شائعاً يوماً، ولا يجد المحتوى الجديد
 * طريقاً إلى الظهور أبداً.
 *
 * كل الإشارات هنا مطبَّعة في المدى [0,1] ثم موزونة بمعاملات من الضبط،
 * فيصير سقف مساهمتها مجموع تلك المعاملات — رقم معلوم يمكن الموازنة
 * بينه وبين مدى BM25.
 */
final class SignalScorer
{
    /**
     * @var array<string, float>
     */
    private array $weights;

    private float $freshnessHalfLife;

    public function __construct()
    {
        $this->weights = [
            'ctr' => (float) config('search.ranking.signals.ctr', 1.5),
            'popularity' => (float) config('search.ranking.signals.popularity', 1.0),
            'freshness' => (float) config('search.ranking.signals.freshness', 0.8),
            'attribute' => (float) config('search.ranking.signals.attribute_match', 4.0),
            'intent' => (float) config('search.ranking.signals.intent_match', 1.2),
        ];

        $this->freshnessHalfLife = max(
            1.0,
            (float) config('search.ranking.freshness_half_life_days', 45.0)
        );
    }

    /**
     * مجموع الإشارات لمستند مرشَّح.
     *
     * @param  array<string, array<int, array{value_text:?string, value_num:?float}>>  $attributes
     *                                                                                              سمات المستند مجمَّعة بالمفتاح
     */
    public function score(QueryPlan $plan, object $row, array $attributes = []): float
    {
        return $this->behaviouralScore($row)
            + $this->freshnessScore($row)
            + $this->softFilterScore($plan, $attributes)
            + $this->intentScore($plan, $row);
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * الإشارة السلوكية: هل ينقر الناس على هذا المستند حين يرونه؟
     */
    private function behaviouralScore(object $row): float
    {
        $clicks = max(0, (int) ($row->click_count ?? 0));
        $views = max(0, (int) ($row->view_count ?? 0));

        return $this->weights['ctr'] * $this->wilsonLowerBound($clicks, $views)
            + $this->weights['popularity'] * $this->saturate((float) $clicks, 50.0);
    }

    /**
     * الحدّ الأدنى لفترة الثقة على نسبة النقر (Wilson).
     *
     * ─── لماذا لا نسبة النقر الخام ─────────────────────────────────
     *
     * الصيغة السابقة كانت clicks / (views + 1). ومستند ظهر مرّة
     * ونُقر مرّة يحصل عليها 0.5 — أعلى مما يحصل عليه مستند ظهر ألف
     * مرة ونُقر أربعمئة. أي أن أضعف دليل ممكن كان يتفوّق على أقوى
     * دليل ممكن.
     *
     * حدّ Wilson الأدنى يعالج ذلك بأخذ حجم العيّنة في الحسبان: يقيس
     * أسوأ نسبة يمكن أن تكون حقيقية بثقة 95%. عيّنة صغيرة تعني فترة
     * ثقة واسعة تعني حدّاً أدنى منخفضاً — فيبدأ المستند الجديد
     * متواضعاً ويرتفع مع تراكم الدليل، لا العكس.
     */
    private function wilsonLowerBound(int $successes, int $trials): float
    {
        if ($trials <= 0 || $successes <= 0) {
            return 0.0;
        }

        $successes = min($successes, $trials);

        // z = 1.96 عند ثقة 95%
        $z = 1.96;
        $n = (float) $trials;
        $phat = $successes / $n;

        $numerator = $phat + ($z * $z) / (2 * $n)
            - $z * sqrt(($phat * (1 - $phat) + ($z * $z) / (4 * $n)) / $n);

        $denominator = 1 + ($z * $z) / $n;

        return max(0.0, min(1.0, $numerator / $denominator));
    }

    /**
     * الحداثة بانحلال أسّي.
     *
     * الصيغة السابقة 1/(days+1) كانت تنهار بسرعة مفرطة: مقال عمره
     * أسبوع يحتفظ بـ 12% فقط من قيمة مقال اليوم، وعمره شهر بـ 3%.
     * وبذلك تحوّل البحث فعلياً إلى ترتيب زمني في أي متن يتجدّد.
     *
     * الانحلال الأسّي يُعبَّر عنه بنصف العمر — كمّية لها معنى مباشر
     * يمكن لصاحب الموقع ضبطها بوعي: "بعد 45 يوماً يفقد المحتوى نصف
     * أفضلية حداثته".
     */
    private function freshnessScore(object $row): float
    {
        $publishedAt = $row->published_at ?? null;

        if ($publishedAt === null) {
            return 0.0;
        }

        $timestamp = is_numeric($publishedAt)
            ? (int) $publishedAt
            : strtotime((string) $publishedAt);

        if ($timestamp === false) {
            return 0.0;
        }

        $ageDays = max(0.0, (time() - $timestamp) / 86400.0);

        return $this->weights['freshness'] * (2 ** (-$ageDays / $this->freshnessHalfLife));
    }

    /**
     * مطابقة الشروط المرجِّحة.
     *
     * الشرط القاطع نفّذه المستودع في SQL فلا حاجة لإعادة تقييمه هنا.
     * أمّا المرجِّح — "ايفون 2020" حيث قد يكون الرقم سنةً أو موديلاً —
     * فهذا موضعه: من طابق السنة يتقدّم، ومن لم يطابقها يبقى ظاهراً.
     *
     * وهنا تكمن قيمة التمييز كلّه: بلا هذه الطبقة كان الخياران إمّا
     * إقصاء نتائج صحيحة عند كل رقم، أو تجاهل الأرقام فلا يُفهم
     * الاستعلام أصلاً.
     *
     * @param  array<string, array<int, array{value_text:?string, value_num:?float}>>  $attributes
     */
    private function softFilterScore(QueryPlan $plan, array $attributes): float
    {
        $soft = $plan->softFilters();

        if ($soft === [] || $attributes === []) {
            return 0.0;
        }

        $score = 0.0;

        foreach ($soft as $filter) {
            if ($this->matchesAttribute($filter, $attributes[$filter->key] ?? [])) {
                /*
                 | الترجيح متناسب مع الثقة: شرط بثقة 0.45 يمنح نصف
                 | ما يمنحه شرط بثقة 0.9 تقريباً. فيتدرّج الأثر مع
                 | قوّة الدليل بدل أن يقفز عند عتبة.
                 */
                $score += $this->weights['attribute'] * $filter->confidence;
            }
        }

        return $score;
    }

    /**
     * @param  array<int, array{value_text:?string, value_num:?float}>  $values
     */
    private function matchesAttribute(AttributeFilter $filter, array $values): bool
    {
        foreach ($values as $value) {
            if ($filter->isNumeric()) {
                $number = $value['value_num'] ?? null;

                if ($number !== null && $this->matchesNumeric($filter, (float) $number)) {
                    return true;
                }

                continue;
            }

            if ((string) ($value['value_text'] ?? '') === (string) $filter->value) {
                return true;
            }
        }

        return false;
    }

    private function matchesNumeric(AttributeFilter $filter, float $number): bool
    {
        $value = (float) $filter->value;

        return match ($filter->operator) {
            AttributeFilter::OP_EQUALS => abs($number - $value) < 0.0001,
            AttributeFilter::OP_GTE => $number >= $value,
            AttributeFilter::OP_LTE => $number <= $value,
            AttributeFilter::OP_RANGE => $number >= $value && $number <= (float) $filter->valueTo,
            default => false,
        };
    }

    /**
     * توافق نوع المحتوى مع نيّة الاستعلام.
     *
     * "تصليح شاشة" ينبغي أن يقدّم الخدمات على المقالات. الترجيح
     * متناسب مع ثقة كشف النية فلا يقلب الترتيب عند إشارة ضعيفة.
     */
    private function intentScore(QueryPlan $plan, object $row): float
    {
        $intent = $plan->intent['intent'];
        $confidence = $plan->intent['confidence'];

        if ($intent === 'general' || $confidence < 0.3) {
            return 0.0;
        }

        $slug = (string) ($row->data_type_slug ?? '');

        if ($slug === '') {
            return 0.0;
        }

        return in_array($slug, IntentTargets::slugsFor($intent), true)
            ? $this->weights['intent'] * $confidence
            : 0.0;
    }

    /**
     * تحويل عدّاد غير محدود إلى مدى [0,1).
     *
     * عند القيمة = k تكون النتيجة 0.5. الشكل هو نفسه المستخدم في
     * تفضيلات المستخدم، فيبقى معنى "الإشباع" واحداً في النظام كله.
     */
    private function saturate(float $value, float $k): float
    {
        return $value <= 0.0 ? 0.0 : $value / ($value + $k);
    }
}
