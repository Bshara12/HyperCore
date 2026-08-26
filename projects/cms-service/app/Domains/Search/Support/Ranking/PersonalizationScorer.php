<?php

declare(strict_types=1);

namespace App\Domains\Search\Support\Ranking;

use App\Domains\Search\DTOs\UserPreferenceDTO;
use App\Domains\Search\Support\Text\Segmenter;

/**
 * PersonalizationScorer — ترجيح النتائج بحسب تاريخ المستخدم.
 *
 * ─── مضاعِف محدود، لا إضافة مفتوحة ──────────────────────────────────
 *
 * الإصدار السابق كان يضيف termAffinity × 6.0 لكل مصطلح مطابق، بلا
 * سقف ولا تناسب مع درجة الصلة. ومع عشرين مصطلحاً في مفردات المستخدم
 * يبلغ السقف النظري 120 نقطة — أضعاف ما يمنحه أي تطابق نصّي.
 *
 * والأثر العملي أن التخصيص كان يلغي البحث: من نقر على الهواتف مرّات
 * يرى الهواتف مهما بحث. وهذا انقلاب في العلاقة — التخصيص يُفترض أن
 * يرجّح بين نتائج متقاربة الصلة، لا أن يقرّر الصلة.
 *
 * هنا يُطبَّق كنسبة من الدرجة الأصلية بسقف من الضبط (0.25 افتراضاً):
 *
 *     final = base × (1 + min(affinity, max_boost))
 *
 * فمستند صلته ضعيفة يبقى ضعيفاً مهما وافق ذوق المستخدم، ومستندان
 * متقاربان يفصل بينهما التاريخ الشخصي. وهذا بالضبط دور التخصيص.
 *
 * ─── علّة الاضمحلال المعكوس ─────────────────────────────────────────
 *
 * كان الإصدار السابق يحسب:
 *
 *     $daysAgo = now()->diffInDays($row->last_searched);
 *     $weight  = exp(-$daysAgo / 7.0);
 *
 * وفي Carbon 3 صار diffInDays يعيد فرقاً موقَّعاً لا مطلقاً. وبما أن
 * التاريخ في الماضي فالنتيجة سالبة، فيصير الأسّ موجباً:
 *
 *     بحث اليوم       exp(0)    = 1.0
 *     بحث قبل 14 يوماً exp(+2)   = 7.4     ← سبعة أضعاف
 *     بحث قبل 30 يوماً exp(+4.3) = 73.7    ← أربعة وسبعون ضعفاً
 *
 * أي أن الاضمحلال انقلب نموّاً: كلما قدم البحث زاد وزنه أسّياً.
 * فكان التخصيص يقدّم أقدم اهتمامات المستخدم على أحدثها — عكس ما
 * كُتب له تماماً، وبلا أي عرَض ظاهر سوى نتائج تبدو "غريبة".
 *
 * الحلّ هنا لا يستدعي حساب الفروق أصلاً: نمرّر عمر السجلّ بالأيام
 * كعدد موجب صريح، ويُحسب الاضمحلال بنصف عمر مفهوم.
 */
final class PersonalizationScorer
{
    private bool $enabled;

    private float $maxBoost;

    private float $halfLifeDays;

    public function __construct()
    {
        $this->enabled = (bool) config('search.ranking.personalization.enabled', true);
        $this->maxBoost = max(0.0, (float) config('search.ranking.personalization.max_boost', 0.25));
        $this->halfLifeDays = max(0.5, (float) config('search.ranking.personalization.half_life_days', 7.0));
    }

    /**
     * تطبيق الترجيح الشخصي على درجة أساسية.
     *
     * @param  array<int, array{term:string, age_days:float}>  $recentTerms
     *                                                                       مصطلحات بحث المستخدم الأخيرة وأعمارها بالأيام
     */
    public function apply(
        float $baseScore,
        object $row,
        UserPreferenceDTO $preference,
        array $recentTerms = []
    ): float {
        if (! $this->enabled || $baseScore <= 0.0 || ! $preference->hasHistory) {
            return $baseScore;
        }

        $affinity = $this->affinity($row, $preference, $recentTerms);

        return $baseScore * (1.0 + min($affinity, $this->maxBoost));
    }

    /**
     * قوّة الميل الشخصي نحو مستند، في المدى [0,1].
     *
     * @param  array<int, array{term:string, age_days:float}>  $recentTerms
     */
    public function affinity(
        object $row,
        UserPreferenceDTO $preference,
        array $recentTerms = []
    ): float {
        /*
         | ─── الأوزان: لماذا ليست متساوية ──────────────────────────
         |
         | الإشارات الثلاث لا تحمل القدر نفسه من المعلومة عن الفرد:
         |
         |   المفردات  ما ينقر عليه هذا المستخدم بعينه من كلمات.
         |             هي الإشارة المميِّزة الحقيقية.
         |
         |   الحداثة   صدى بحثه الأخير — قوي لكنه عابر.
         |
         |   نوع المحتوى  خشن جداً. من يتصفّح متجراً ينقر على
         |             "منتجات"، وكذلك كل مستخدم آخر في المتجر.
         |
         | وقد ظهر أثر التسوية بينها في القياس: مستخدمان بذوقين
         | متعاكسين تماماً حصلا على المضاعِف نفسه (×1.25) في كل
         | استعلام. السبب أن ميل النوع بلغ 0.87 و0.80 عندهما، فاستهلك
         | وحده أكثر من نصف ميزانية الترجيح قبل أن تُحسب أي إشارة
         | مميِّزة، فبلغ المجموع سقفَه عند الجميع.
         |
         | ووزنه المنخفض هنا ليس تقليلاً من شأنه بل اعترافاً بطبيعته:
         | من يتصفّح متجراً ينقر على "منتجات"، وكذلك كل مستخدم آخر
         | في المتجر. فالإشارة تكاد تكون ثابتة عبر كل النتائج، وما
         | لا يتغيّر بين النتائج لا يرتّبها — يستهلك الميزانية فقط.
         |
         | يبقى النوع نافعاً حين تمتدّ النتائج عبر أنواع مختلفة
         | (منتج مقابل مقال مقابل خدمة)، وهناك تظهر قيمته الحقيقية.
         */
        $signals = [
            [$this->vocabularyAffinity($row, $preference), 1.00],
            [$this->recencyAffinity($row, $recentTerms), 0.50],
            [$this->dataTypeAffinity($row, $preference), 0.05],
        ];

        /*
         | الدمج بمكمّل الاحتمال لا بالجمع.
         |
         |     combined = 1 - Π (1 - signal × weight)
         |
         | إشارتان ضعيفتان تُنتجان أقوى منهما ولا تبلغان مجموعهما،
         | وإشارة قوية وحدها لا تتجاوز نفسها. الجمع المباشر كان يسمح
         | لعدّة إشارات متوسطة بتجاوز الواحد الصحيح فيُقصّ عند السقف،
         | فتضيع التدرّجات كلها فوقه.
         */
        $complement = 1.0;

        foreach ($signals as [$signal, $weight]) {
            $complement *= (1.0 - max(0.0, min(1.0, $signal)) * $weight);
        }

        return 1.0 - $complement;
    }

    // ─────────────────────────────────────────────────────────────────

    private function dataTypeAffinity(object $row, UserPreferenceDTO $preference): float
    {
        return $preference->affinityFor((int) ($row->data_type_id ?? 0));
    }

    /**
     * تطابق مفردات المستند مع مفردات ما ينقر عليه المستخدم.
     *
     * يُقتصر على العنوان: مفردات المتن واسعة بما يكفي لتطابق أي ميل،
     * فتصير الإشارة ضوضاء. العنوان هو ما يصف المستند فعلاً.
     *
     * ─── التغطية لا أقوى مطابقة ────────────────────────────────────
     *
     * كان القياس يأخذ أعلى ميل بين الكلمات المتطابقة. وأثره أن كلمة
     * واحدة مشتركة تكفي لبلوغ السقف:
     *
     *     "MacBook Pro 16"  لمن ينقر أجهزة Apple   → macbook, pro, 16
     *     "MacBook Pro 16"  لمن ينقر أدوات الرياضة → pro فقط
     *
     * وكلاهما كان يبلغ الحدّ الأقصى، لأن "pro" وحدها في ملفَّي الاثنين.
     * فيتساوى المضاعِف ويصير التخصيص تضخيماً موحَّداً لا تمييزاً.
     *
     * التغطية — مجموع الأميال منسوباً إلى عدد كلمات العنوان — تفرّق
     * بينهما: من طابق ثلاث كلمات من ثلاث أوثق صلةً بميله ممّن طابق
     * واحدة. وهي أيضاً أقرب إلى معنى "هذا المستند من نوع ما أحب".
     */
    private function vocabularyAffinity(object $row, UserPreferenceDTO $preference): float
    {
        if ($preference->termAffinities === []) {
            return 0.0;
        }

        $tokens = Segmenter::tokenize((string) ($row->title_fold ?? ''));

        if ($tokens === []) {
            return 0.0;
        }

        $matched = 0.0;

        foreach ($tokens as $token) {
            $matched += (float) ($preference->termAffinities[$token] ?? 0.0);
        }

        return min(1.0, $matched / count($tokens));
    }

    /**
     * صدى عمليات البحث الأخيرة.
     *
     * @param  array<int, array{term:string, age_days:float}>  $recentTerms
     */
    private function recencyAffinity(object $row, array $recentTerms): float
    {
        if ($recentTerms === []) {
            return 0.0;
        }

        $title = (string) ($row->title_fold ?? '');

        if ($title === '') {
            return 0.0;
        }

        $strongest = 0.0;

        foreach ($recentTerms as $entry) {
            $term = $entry['term'];

            if ($term === '' || ! str_contains($title, $term)) {
                continue;
            }

            /*
             | العمر موجب دائماً، والاضمحلال بنصف عمر: بحث اليوم بوزن
             | كامل، وبحث قبل أسبوع بنصفه، وقبل أسبوعين بربعه. هذا هو
             | السلوك الذي كان مقصوداً وانقلب إلى نقيضه.
             */
            $ageDays = max(0.0, $entry['age_days']);
            $strongest = max($strongest, 2 ** (-$ageDays / $this->halfLifeDays));
        }

        return $strongest;
    }
}
