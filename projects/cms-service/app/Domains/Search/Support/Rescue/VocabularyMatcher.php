<?php

declare(strict_types=1);

namespace App\Domains\Search\Support\Rescue;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * VocabularyMatcher — تصحيح المصطلح إلى أقرب كلمة موجودة فعلاً.
 *
 * ─── لماذا مفردات المشروع لا قاموس ─────────────────────────────────
 *
 * قاموس التصحيح الثابت يعاني ثلاث علل مجتمعة:
 *
 *   1. يصحّح إلى كلمات لا وجود لها في المحتوى. "smartfone" تُصحَّح
 *      إلى "smartphone" فتُعيد صفر نتائج إن كان المتجر يسمّيها
 *      "mobile" — أي أننا استبدلنا خطأً بخطأ.
 *
 *   2. يجهل أسماء العلم. أسماء المنتجات والماركات والموديلات ليست
 *      في أي قاموس، وهي بالضبط ما يبحث به الناس ويخطئون في كتابته.
 *
 *   3. يحتاج قاموساً لكل لغة، فيعود بنا إلى دعم لغتين لا كل اللغات.
 *
 * المفردات المستخرَجة من الفهرس (search_term_stats) تحلّ الثلاث دفعة
 * واحدة: كل كلمة فيها موجودة في محتوى هذا المشروع بالتحديد، فالتصحيح
 * إليها يجد نتائج بالضرورة. وهي تُبنى تلقائياً لأي لغة بلا بيانات
 * لغوية إطلاقاً — لأنها إحصاء لا معرفة.
 *
 * ─── الترجيح بالتكرار ──────────────────────────────────────────────
 *
 * عند تساوي المسافة يفوز الأشيع. "shose" على مسافة واحدة من "shoes"
 * و"chose" و"hose"؛ ومن يبحث في متجر أحذية يقصد الأولى، والتكرار
 * المستندي هو ما يقول ذلك — بلا أي معرفة بمجال المتجر.
 */
final class VocabularyMatcher
{
    /** أقصى عدد مرشَّحين يُقاسون في PHP لكل مصطلح. */
    private const CANDIDATE_LIMIT = 1500;

    /** أدنى طول يستحقّ التصحيح: ما دونه كل شيء قريب من كل شيء. */
    private const MIN_LENGTH = 4;

    /**
     * ذاكرة داخل النسخة.
     *
     * تحمل شكلين بمفاتيح مختلفة البادئة: قوائم المرشَّحين مقصورةً
     * بالطول، والفهرس الصوتي. الفصل بالبادئة يكفي لأن المفاتيح لا
     * تتقاطع، ويوفّر حقلين منفصلين لغرض واحد.
     *
     * @var array<string, array<array-key, array{term:string, doc_freq:int}>>
     */
    private array $cache = [];

    /**
     * أقرب بديل موجود في المفردات، أو null إن لم يوجد ما يكفي قرباً.
     */
    public function closest(string $term, int $projectId, string $language): ?string
    {
        $length = mb_strlen($term, 'UTF-8');

        if ($length < self::MIN_LENGTH) {
            return null;
        }

        $tolerance = EditDistance::toleranceFor($length);

        if ($tolerance === 0) {
            return null;
        }

        $best = null;
        $bestDistance = $tolerance + 1;
        $bestFrequency = 0;

        foreach ($this->candidates($term, $projectId, $language, $tolerance) as $candidate) {
            $distance = EditDistance::between($term, $candidate['term'], $tolerance);

            if ($distance > $tolerance) {
                continue;
            }

            // الأقرب يفوز؛ وعند التساوي يفوز الأشيع.
            if ($distance < $bestDistance
                || ($distance === $bestDistance && $candidate['doc_freq'] > $bestFrequency)
            ) {
                $best = $candidate['term'];
                $bestDistance = $distance;
                $bestFrequency = $candidate['doc_freq'];
            }
        }

        return $best ?? $this->phoneticMatch($term, $projectId, $language);
    }

    /**
     * المطابقة الصوتية — لما يعجز عنه قياس المسافة.
     *
     * ─── لماذا لا تكفي المسافة التحريرية ───────────────────────────
     *
     * بعض الأخطاء الشائعة بعيدة بالمحارف وقريبة بالنطق:
     *
     *     smartfone  →  smartphones     ثلاث عمليات تحرير
     *     fotografy  →  photography     أربع عمليات
     *
     * ورفع عتبة المسافة لاستيعابها يفتح الباب لتصحيحات خاطئة كثيرة:
     * على مسافة ثلاثة تصير أغلب الكلمات القصيرة "قريبة" من بعضها.
     *
     * المطابقة الصوتية تلتقط هذا الصنف بدقّة لأنها تقيس بُعداً آخر —
     * كيف تُنطق الكلمة لا كيف تُهجّى. و"ph" و"f" لهما الرمز الصوتي
     * نفسه، فتتطابقان مهما بعدت هجائيتهما.
     *
     * ─── حدّها ─────────────────────────────────────────────────────
     *
     * metaphone خوارزمية للإنجليزية وتعمل على ASCII وحدها. فتُقصَر على
     * المصطلحات اللاتينية، وتتدرّج بقيّة اللغات إلى قياس المسافة وحده —
     * وهو كافٍ لها لأن هذا الصنف من الالتباس (ph/f، gh/g) خاصية
     * إملائية إنجليزية لا تشترك فيها العربية ولا الروسية.
     */
    private function phoneticMatch(string $term, int $projectId, string $language): ?string
    {
        if (preg_match('/^[a-z]+$/', $term) !== 1) {
            return null;
        }

        $key = metaphone($term);

        /*
         | الرمز الصوتي القصير جداً لا يميّز شيئاً.
         |
         | metaphone تطوي الحروف المكرَّرة، فسلسلة من مئتَي حرف واحد
         | تُنتج رمزاً من محرف واحد — ومحرف واحد يقع على مسافة واحدة
         | من نصف مفردات أي متن. الحدّ الأدنى يمنع هذا الانهيار.
         */
        if (mb_strlen($key) < 2) {
            return null;
        }

        $termLength = mb_strlen($term, 'UTF-8');
        $best = null;
        $bestDistance = PHP_INT_MAX;
        $bestFrequency = 0;

        foreach ($this->phoneticIndex($projectId, $language) as $candidateKey => $candidate) {
            /*
             | شرطان معاً: تقارب الطول، ثم تقارب الرمز الصوتي.
             |
             | الطول أولاً لأنه أرخص وأقطع: كلمتان متباعدتان في الطول
             | ليستا خطأً مطبعياً في إحداهما مهما تقارب رمزهما.
             |
             | ثم مسافة واحدة على الرمز لا تطابق تامّ، لأن اللواحق
             | الصرفية تغيّره بمقدار محرف:
             |
             |     smartfone    →  SMRTFN
             |     smartphones  →  SMRTFNS      ← حرف الجمع
             |
             | والتطابق التامّ يرفض الزوج رغم أنهما الكلمة نفسها نطقاً.
             | والمحرف الواحد لا يفتح الباب: الرمز مضغوط أصلاً (حروف
             | العلّة محذوفة)، فمحرف فيه فارق جوهري لا انزلاق أصابع.
             */
            if (abs($termLength - mb_strlen($candidate['term'], 'UTF-8')) > 3) {
                continue;
            }

            $distance = EditDistance::between($key, (string) $candidateKey, 1);

            if ($distance > 1) {
                continue;
            }

            if ($distance < $bestDistance
                || ($distance === $bestDistance && $candidate['doc_freq'] > $bestFrequency)
            ) {
                $best = $candidate['term'];
                $bestDistance = $distance;
                $bestFrequency = $candidate['doc_freq'];
            }
        }

        return $best;
    }

    /**
     * هل تعرف مفرداتُ المشروع أيّاً من هذه المصطلحات حرفياً؟
     *
     * ─── لماذا هذا هو الاختبار الصحيح لعكس لوحة المفاتيح ────────────
     *
     * العكس عملية ميكانيكية تنجح على أي نصّ: كل حرف له نظير في الموضع
     * نفسه، فالمخرَج دائماً "كلمات" شكلاً. والسؤال الحقيقي ليس هل نجح
     * العكس بل هل كان النصّ مكتوباً بلوحة خاطئة أصلاً.
     *
     * وقد كان الحارس السابق يكتفي بتبدّل نظام الكتابة — وهو شرط يمرّ
     * دائماً بحكم البناء. فمرّت جملة عربية سليمة وأنتجت "f d hbdt k
     * dgd g fhg"، ثم طابقت البادئتان f* و k* كلمتَي Fashion وKitchen،
     * فعادت نتائج لا صلة لها بالاستعلام إطلاقاً.
     *
     * وجود الكلمة في مفردات المشروع اختبار قاطع: نصّ عُكس صحيحاً يُنتج
     * كلمات موجودة في المحتوى، وقمامةٌ لا تُنتج شيئاً. والكلفة بحث
     * واحد على فهرس مركَّب.
     *
     * @param  string[]  $terms
     */
    public function knowsAny(array $terms, int $projectId, string $language): bool
    {
        $meaningful = array_values(array_filter(
            $terms,
            static fn (string $t): bool => mb_strlen($t, 'UTF-8') >= 3
        ));

        if ($meaningful === []) {
            return false;
        }

        try {
            return DB::table('search_term_stats')
                ->where('project_id', $projectId)
                ->where('language', $language)
                ->whereIn('term', $meaningful)
                ->exists();
        } catch (\Throwable $e) {
            Log::warning('VocabularyMatcher: vocabulary probe failed', [
                'project_id' => $projectId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * تصحيح قائمة مصطلحات. يُعاد null إن لم يتغيّر شيء.
     *
     * @param  string[]  $terms
     * @return string[]|null
     */
    public function correctAll(array $terms, int $projectId, string $language): ?array
    {
        $corrected = [];
        $changed = false;

        foreach ($terms as $term) {
            $replacement = $this->closest($term, $projectId, $language);

            if ($replacement !== null && $replacement !== $term) {
                $changed = true;
                $corrected[] = $replacement;

                continue;
            }

            $corrected[] = $term;
        }

        return $changed ? array_values(array_unique($corrected)) : null;
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * تعبير طول النصّ بالمحارف، مناسباً للمحرّك الحالي.
     *
     * ─── لماذا لا CHAR_LENGTH دائماً ────────────────────────────────
     *
     * CHAR_LENGTH لا وجود لها في SQLite، فيُلقي الاستعلام استثناءً
     * يبتلعه الحارس هنا فتعود المفردات فارغة — أي أن التصحيح الإملائي
     * يتوقّف كلياً وبصمت على أي محرّك غير MySQL، بما فيه بيئة الاختبار.
     *
     * ولا LENGTH دائماً كذلك: في MySQL تعدّ البايتات لا المحارف، والحرف
     * العربي بايتان، فيصير ترشيح الطول خاطئاً لكل اللغات غير اللاتينية.
     * أمّا في SQLite فتعدّ محارف نصوص TEXT، وهو المطلوب.
     *
     * فالاختيار بحسب المحرّك ليس تجميلاً بل شرط صحّة على الطرفين.
     */
    private function lengthExpression(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? 'LENGTH(term)'
            : 'CHAR_LENGTH(term)';
    }

    /**
     * فهرس صوتي للمفردات: الرمز الصوتي => أشيع كلمة تحمله.
     *
     * يُبنى مرّة لكل (مشروع، لغة) ويُحتفظ به داخل النسخة. المصطلحات
     * غير اللاتينية تُستبعَد لأن metaphone لا معنى لها خارج ASCII.
     *
     * @return array<string, array{term:string, doc_freq:int}>
     */
    private function phoneticIndex(int $projectId, string $language): array
    {
        $key = 'phonetic:'.$projectId.':'.$language;

        if (isset($this->cache[$key])) {
            /** @var array<string, array{term:string, doc_freq:int}> */
            return $this->cache[$key];
        }

        try {
            $rows = DB::table('search_term_stats')
                ->select('term', 'doc_freq')
                ->where('project_id', $projectId)
                ->where('language', $language)
                ->whereRaw($this->lengthExpression().' >= ?', [self::MIN_LENGTH])
                ->orderByDesc('doc_freq')
                ->limit(self::CANDIDATE_LIMIT * 2)
                ->get();
        } catch (\Throwable $e) {
            Log::warning('VocabularyMatcher: phonetic index failed', [
                'project_id' => $projectId,
                'error' => $e->getMessage(),
            ]);

            return $this->cache[$key] = [];
        }

        $index = [];

        foreach ($rows as $row) {
            $term = (string) $row->term;

            if (preg_match('/^[a-z]+$/', $term) !== 1) {
                continue;
            }

            $phonetic = metaphone($term);

            if ($phonetic === '') {
                continue;
            }

            $frequency = (int) $row->doc_freq;

            // الصفوف مرتّبة تنازلياً بالتكرار، فالأول لكل رمز هو الأشيع.
            $index[$phonetic] ??= ['term' => $term, 'doc_freq' => $frequency];
        }

        return $this->cache[$key] = $index;
    }

    /**
     * المرشَّحون المحتملون، مقصورين بالطول.
     *
     * الترشيح بالطول في SQL لا في PHP: كلمة تفرق في الطول أكثر من
     * العتبة لا يمكن أن تكون ضمنها مهما كانت حروفها، فقياسها هدر
     * خالص. هذا وحده يُسقط أغلب المفردات قبل أي حساب.
     *
     * @return array<int, array{term:string, doc_freq:int}>
     */
    private function candidates(string $term, int $projectId, string $language, int $tolerance): array
    {
        $length = mb_strlen($term, 'UTF-8');
        $key = $projectId.':'.$language.':'.$length.':'.$tolerance;

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        try {
            $rows = DB::table('search_term_stats')
                ->select('term', 'doc_freq')
                ->where('project_id', $projectId)
                ->where('language', $language)
                ->whereRaw($this->lengthExpression().' BETWEEN ? AND ?', [
                    max(1, $length - $tolerance),
                    $length + $tolerance,
                ])
                ->orderByDesc('doc_freq')
                ->limit(self::CANDIDATE_LIMIT)
                ->get();
        } catch (\Throwable $e) {
            /*
             | تعذّر قراءة المفردات لا يبطل البحث.
             |
             | التصحيح إنقاذ لاستعلام أخفق أصلاً، فالعجز عنه يعيدنا
             | إلى الحالة التي كنّا فيها لا إلى حالة أسوأ.
             */
            Log::warning('VocabularyMatcher: vocabulary lookup failed', [
                'project_id' => $projectId,
                'error' => $e->getMessage(),
            ]);

            return $this->cache[$key] = [];
        }

        $candidates = [];

        foreach ($rows as $row) {
            $candidates[] = [
                'term' => (string) $row->term,
                'doc_freq' => (int) $row->doc_freq,
            ];
        }

        return $this->cache[$key] = $candidates;
    }
}
