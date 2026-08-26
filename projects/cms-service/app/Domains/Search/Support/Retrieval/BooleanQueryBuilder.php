<?php

declare(strict_types=1);

namespace App\Domains\Search\Support\Retrieval;

use App\Domains\Search\Support\Query\QueryPlan;

/**
 * BooleanQueryBuilder — تحويل خطة إلى تعبير BOOLEAN MODE.
 *
 * ─── لماذا صنف مستقلّ ───────────────────────────────────────────────
 *
 * لغة BOOLEAN MODE في MySQL لها نحوها الخاص: "+" للإلزام، و"-" للنفي،
 * و"*" للبادئة، و"(" للتجميع، و"\"" للعبارة. وأي محرف من هذه يرد في
 * مصطلح مستخدم يغيّر دلالة الاستعلام كلّه.
 *
 * وقد كان الإصدار السابق يبني هذا التعبير في ثلاثة مواضع — المعالج،
 * والمستودع، والمسار الاحتياطي الذكي — ويلصق مخرَج النموذج اللغوي فيه
 * مباشرةً. أي أن نصّاً يولّده طرف خارجي كان يصل إلى نحو الاستعلام بلا
 * أي تعقيم؛ ويكفي أن يُخرج النموذج "-" ليقلب معنى الشرط.
 *
 * هنا موضع واحد، ولا يصل إليه إلا مصطلحات مرّت بالتقسيم — أي حروف
 * وأرقام فقط بحكم البناء. والتنظيف أدناه حزام أمان ثانٍ لا أول.
 *
 * ─── سلّم التراخي ───────────────────────────────────────────────────
 *
 * يُبنى تعبيران لا واحد:
 *
 *   الصارم  +iphone* +pro*   كل المصطلحات مطلوبة — دقّة عالية
 *   المتراخي iphone* pro*     أيّها يكفي — استرجاع أوسع
 *
 * يُجرَّب الصارم أولاً، ولا يُنزَل إلى المتراخي إلا عند العدم. البدء
 * بالمتراخي يعني إغراق المستخدم بنتائج تطابق كلمة واحدة من ثلاث.
 */
final class BooleanQueryBuilder
{
    /** الحدّ الأقصى لطول التعبير — حماية من استعلامات مُصطنَعة الطول. */
    private const MAX_LENGTH = 1000;

    /** أدنى طول مصطلح يدخل التعبير المنطقي. */
    private const MIN_TERM_LENGTH = 2;

    /**
     * تعبيرا البحث مرتّبَين من الأصرم إلى الأكثر تراخياً.
     *
     * @return string[] قد تعود فارغة إذا لم تكن الخطة نصّية أصلاً
     */
    public function build(QueryPlan $plan): array
    {
        $terms = $this->sanitizeAll($plan->terms);
        $expansions = $this->sanitizeAll($plan->expansions);
        $exclusions = $this->sanitizeAll($plan->mustNot);

        if ($terms === [] && $expansions === []) {
            return [];
        }

        $suffix = $this->exclusionClause($exclusions);

        $strict = $this->strict($terms, $expansions);
        $loose = $this->loose($terms, $expansions);

        $queries = [];

        foreach ([$strict, $loose] as $query) {
            $query = trim($query.$suffix);

            if ($query !== '' && ! in_array($query, $queries, true)) {
                $queries[] = mb_substr($query, 0, self::MAX_LENGTH, 'UTF-8');
            }
        }

        return $queries;
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * كل مصطلحات المستخدم مطلوبة، والتوسعات اختيارية.
     *
     * التوسعة استنتاجُنا لا نصُّ المستخدم، فإلزامها يعني إقصاء مستندات
     * تطابق ما كتبه فعلاً لمجرّد أنها لا تطابق ترجمتنا الصوتية.
     *
     * @param  string[]  $terms
     * @param  string[]  $expansions
     */
    private function strict(array $terms, array $expansions): string
    {
        if ($terms === []) {
            return $this->loose($terms, $expansions);
        }

        $parts = [];

        foreach ($terms as $index => $term) {
            $alternatives = $this->alternativesFor($index, $terms, $expansions);

            /*
             | المصطلح ومقابله الصوتي في مجموعة واحدة مطلوبة.
             |
             | "+(ايفون* iphone*)" يعني: يجب أن يحتوي المستند أحدهما.
             | فصلهما إلى شرطين مُلزَمين كان سيتطلّب أن يحتوي المستند
             | الكلمة بالعربية والإنجليزية معاً — وهو ما لا يقع.
             */
            $parts[] = $alternatives === []
                ? '+'.$term.'*'
                : '+('.$term.'* '.implode(' ', array_map(
                    static fn (string $alt): string => $alt.'*',
                    $alternatives
                )).')';
        }

        return implode(' ', $parts);
    }

    /**
     * @param  string[]  $terms
     * @param  string[]  $expansions
     */
    private function loose(array $terms, array $expansions): string
    {
        $all = array_values(array_unique([...$terms, ...$expansions]));

        return implode(' ', array_map(
            static fn (string $term): string => $term.'*',
            $all
        ));
    }

    /**
     * التوسعات التي تخصّ مصطلحاً بعينه.
     *
     * لا يوجد ربط صريح بين المصطلح وتوسعته في الخطة، فنُلحق كل
     * التوسعات بالمصطلح الأول فقط. الإلحاق بكلّ مصطلح كان سيجعل أي
     * توسعة تُرضي أي شرط مُلزَم، فينهار الوضع الصارم إلى المتراخي.
     *
     * @param  string[]  $terms
     * @param  string[]  $expansions
     * @return string[]
     */
    private function alternativesFor(int $index, array $terms, array $expansions): array
    {
        return $index === 0 ? $expansions : [];
    }

    /**
     * @param  string[]  $exclusions
     */
    private function exclusionClause(array $exclusions): string
    {
        if ($exclusions === []) {
            return '';
        }

        return ' '.implode(' ', array_map(
            static fn (string $term): string => '-'.$term,
            $exclusions
        ));
    }

    /**
     * @param  string[]  $terms
     * @return string[]
     */
    private function sanitizeAll(array $terms): array
    {
        $clean = [];

        foreach ($terms as $term) {
            $sanitized = $this->sanitize((string) $term);

            if ($sanitized !== '') {
                $clean[$sanitized] = true;
            }
        }

        return array_keys($clean);
    }

    /**
     * إبقاء الحروف والأرقام فقط، ورفض ما هو أقصر من حدّ المطابقة.
     *
     * ─── لماذا تسقط الوحدة المفردة ─────────────────────────────────
     *
     * كل مصطلح يُلحَق بـ * فيصير مطابقة بادئة. والبادئة المفردة تطابق
     * كل كلمة تبدأ بذلك الحرف: "f*" تطابق Fashion وFitness وFlip،
     * و"k*" تطابق Kitchen وKitchenAid. فالمصطلح لا يضيّق البحث بل
     * يفتحه على المتن كله.
     *
     * وقد ظهر الأثر حين أنتج عكسُ لوحة المفاتيح وحداتٍ مفردة، فعادت
     * نتائج لا صلة لها بالاستعلام تصدّرتها كلمات تشترك في حرف أول.
     *
     * ملاحظة: هذا الحدّ للتعبير المنطقي وحده. الوحدة المفردة تبقى
     * في الخطة ويحسب لها BM25F وزناً، وتبقى قابلة للبحث في اللغات
     * الآسيوية عبر فهرس الـ ngram الذي لا يمرّ من هنا.
     */
    private function sanitize(string $term): string
    {
        $clean = preg_replace('/[^\p{L}\p{N}\p{M}]+/u', '', $term) ?? '';

        return mb_strlen($clean, 'UTF-8') >= self::MIN_TERM_LENGTH ? $clean : '';
    }
}
