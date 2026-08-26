<?php

declare(strict_types=1);

namespace App\Domains\Search\Support\Retrieval;

/**
 * CandidateWindow — كم صفّاً نسحب، ومن أين تُقتطع الصفحة منها.
 *
 * ─── المفاضلة التي تحكم هذا الصنف ───────────────────────────────────
 *
 * إعادة الترتيب تجري في PHP، فلا يمكنها أن ترى إلا ما سُحب. نافذة
 * ضيّقة تعني أن مستنداً كان يستحقّ المركز الأول لن يصعد إليه لأنه لم
 * يدخل النافذة أصلاً. ونافذة واسعة تعني نقل صفوف أكثر وتفكيك نصّها.
 *
 * الحلّ أن تُشتقّ النافذة من الصفحة المطلوبة لا أن تكون ثابتة. وهنا
 * كانت العلّة السابقة: ثابت DB_FETCH_LIMIT = 100 مع OFFSET صفر دائماً،
 * فكانت الصفحة الثامنة فما بعدها تعود فارغة بينما يعلن total وجود
 * مئات النتائج — بلا خطأ ولا تحذير.
 *
 * ─── الترقيم العميق ─────────────────────────────────────────────────
 *
 * النافذة مسقوفة، وإلا صار طلب الصفحة الألف يسحب عشرات الآلاف من
 * الصفوف ويفكّك نصّها كلّه. وحين تتجاوز الصفحةُ السقفَ نتخلّى عن إعادة
 * الترتيب ونعتمد ترتيب قاعدة البيانات مع إزاحة حقيقية.
 *
 * وهذه مقايضة مقبولة: من يتصفّح الصفحة الخمسين لا يبحث عن الأوثق
 * صلةً — لو كان يبحث عنه لوجده في الأولى. المهمّ أن النتائج تُعرض
 * وأن الترقيم متّسق، لا أن يكون ترتيبها الأمثل.
 */
final readonly class CandidateWindow
{
    private function __construct(
        public int $size,
        public int $sqlOffset,
        public int $sliceOffset,
        public bool $rerank,
    ) {}

    public static function forPage(int $page, int $perPage): self
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $multiplier = max(1, (int) config('search.retrieval.candidate_multiplier', 4));
        $minimum = max($perPage, (int) config('search.retrieval.min_candidates', 200));
        $maximum = max($minimum, (int) config('search.retrieval.max_candidates', 1000));

        $needed = $page * $perPage;
        $desired = max($minimum, $needed * $multiplier);

        /*
         | الترقيم العميق: الصفحة المطلوبة تقع خارج ما يمكن سحبه.
         |
         | نسحب صفحة واحدة بإزاحتها الحقيقية بلا إعادة ترتيب. البديل —
         | وهو ما كان يقع — إعادة صفحة فارغة، وهو أسوأ من ترتيب دون
         | الأمثل بكثير.
         */
        if ($needed > $maximum) {
            return new self(
                size: $perPage,
                sqlOffset: ($page - 1) * $perPage,
                sliceOffset: 0,
                rerank: false,
            );
        }

        return new self(
            size: min($maximum, $desired),
            sqlOffset: 0,
            sliceOffset: ($page - 1) * $perPage,
            rerank: true,
        );
    }

    /**
     * اقتطاع الصفحة من المرشَّحين بعد ترتيبهم.
     *
     * @param  array<int, object>  $ranked
     * @return array<int, object>
     */
    public function slice(array $ranked, int $perPage): array
    {
        return array_slice($ranked, $this->sliceOffset, max(1, $perPage));
    }
}
