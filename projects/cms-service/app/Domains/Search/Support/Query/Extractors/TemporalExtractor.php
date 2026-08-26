<?php

declare(strict_types=1);

namespace App\Domains\Search\Support\Query\Extractors;

use App\Domains\Search\Support\Lexicon\Lexicon;
use App\Domains\Search\Support\Query\AttributeFilter;

/**
 * TemporalExtractor — تحويل الزمن في الاستعلام إلى شرط على سنة الإصدار.
 *
 * هذا هو المستخرِج الذي يجعل "الايفون يلي نزل بال 2020" يعمل.
 *
 * ─── لماذا يعمل مع كل اللغات ─────────────────────────────────────────
 *
 *   السنة نفسها لا تحتاج معجماً: TextFolder وحّد أرقام Unicode كلها
 *   إلى ASCII قبل الوصول إلى هنا، فـ "٢٠٢٠" الهندية-العربية و"۲۰۲۰"
 *   الفارسية و"٢٠٢٠" و"２０２０" الكاملة العرض تصل كلها بالصورة "2020".
 *   أي أن كشف السنة يعمل في كل لغة حتى بلا ملف موارد.
 *
 *   الدالّ الزمني ("نزل"، "released"، "発売") هو وحده ما يحتاج معجماً،
 *   وأثره محصور في رفع الثقة من ترجيح إلى إقصاء. لغة بلا موارد تحصل
 *   على الترجيح — أي على سلوك صحيح وإن كان أقل حزماً.
 *
 * ─── سُلّم الثقة ─────────────────────────────────────────────────────
 *
 *   0.95  زمن نسبي صريح          "السنة الماضية"، "last year"
 *   0.90  سنة مع دالّ زمني        "نزل بال 2020"، "released in 2020"
 *   0.90  نطاق سنوات صريح         "من 2018 الى 2020"، "2018-2020"
 *   0.45  سنة مجرّدة              "ايفون 2020"  ← ترجيح لا إقصاء
 */
final class TemporalExtractor
{
    public const KEY = 'year';

    /** نطاق يُكتب بشرطة: 2018-2020 */
    private const RANGE_PATTERN = '/\b(1[89]\d{2}|20\d{2}|21\d{2})\s*[-–—]\s*(1[89]\d{2}|20\d{2}|21\d{2})\b/u';

    public function __construct(
        private readonly Lexicon $lexicon,
    ) {}

    /**
     * @param  string[]  $tokens  الوحدات المطبَّعة
     * @param  string[]  $scripts
     */
    public function extract(string $foldedQuery, array $tokens, array $scripts): ExtractionResult
    {
        $relative = $this->extractRelative($foldedQuery, $scripts);

        if (! $relative->isEmpty()) {
            return $relative;
        }

        $range = $this->extractExplicitRange($foldedQuery, $tokens);

        if (! $range->isEmpty()) {
            return $range;
        }

        return $this->extractSingleYear($foldedQuery, $tokens, $scripts);
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * "السنة الماضية" / "last year" → سنة محسوبة من التقويم الحالي.
     *
     * لا شيء يُستهلك هنا: الكلمات دالّة على الزمن ولا ترد في المحتوى
     * بصيغتها هذه، فاحتسابها كمصطلحات لا يضرّ ولا ينفع.
     *
     * @param  string[]  $scripts
     */
    private function extractRelative(string $foldedQuery, array $scripts): ExtractionResult
    {
        foreach ($this->lexicon->relativeTime($scripts) as $phrase => $offset) {
            if (! str_contains($foldedQuery, $phrase)) {
                continue;
            }

            $year = (int) date('Y') + (int) $offset;

            if (! $this->isPlausibleYear($year)) {
                continue;
            }

            return new ExtractionResult(
                filters: [AttributeFilter::numericEquals(self::KEY, (float) $year, 0.95)],
            );
        }

        return ExtractionResult::empty();
    }

    /**
     * نطاق سنوات صريح.
     *
     * الصيغة بالشرطة ("2018-2020") قاطعة بذاتها: رقمان رباعيان في مدى
     * السنوات يفصلهما واصل لا يحتملان تفسيراً غير الزمن.
     *
     * @param  string[]  $tokens
     */
    private function extractExplicitRange(string $foldedQuery, array $tokens): ExtractionResult
    {
        if (preg_match(self::RANGE_PATTERN, $foldedQuery, $matches) !== 1) {
            return ExtractionResult::empty();
        }

        $from = (int) $matches[1];
        $to = (int) $matches[2];

        if (! $this->isPlausibleYear($from) || ! $this->isPlausibleYear($to)) {
            return ExtractionResult::empty();
        }

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $consumed = $this->indexesOfYears($tokens, [$from, $to]);

        /*
         | النمط يُطابَق على النصّ الكامل بما فيه ما نفاه المستخدم،
         | لأن الترقيم — وهو حامل معنى النطاق — يختفي من الوحدات.
         | فنتحقّق هنا أن طرفَي النطاق ما زالا ضمن المطلوب فعلاً:
         | "لابتوب بدون 2018-2020" يجب ألّا يفرض النطاق الذي نفاه.
         */
        if ($consumed === []) {
            return ExtractionResult::empty();
        }

        return new ExtractionResult(
            filters: [AttributeFilter::numericRange(self::KEY, (float) $from, (float) $to, 0.90)],
            consumed: $consumed,
        );
    }

    /**
     * سنة مفردة. الدالّ الزمني هو ما يرفعها من ترجيح إلى إقصاء.
     *
     * @param  string[]  $tokens
     * @param  string[]  $scripts
     */
    private function extractSingleYear(string $foldedQuery, array $tokens, array $scripts): ExtractionResult
    {
        $years = [];

        foreach ($tokens as $index => $token) {
            if ($this->isYearToken($token)) {
                $years[$index] = (int) $token;
            }
        }

        if ($years === []) {
            return ExtractionResult::empty();
        }

        $cues = $this->temporalCuesIn($foldedQuery, $scripts);
        $hasCue = $cues !== [];
        $confidence = $hasCue ? 0.90 : 0.45;

        $filters = [];
        $consumed = $hasCue ? $this->indexesOfCues($tokens, $cues) : [];

        foreach ($years as $index => $year) {
            $filters[] = AttributeFilter::numericEquals(self::KEY, (float) $year, $confidence);

            /*
             | الاستهلاك مشروط بالثقة: سنة مؤكَّدة تخرج من حساب الصلة
             | كي لا تُحتسب مرّتين، وسنة مشكوك فيها تبقى مصطلحاً لأنها
             | قد تكون اسم موديل — و"ايفون 2020" يجب أن يطابق منتجاً
             | اسمه حرفياً "iPhone 2020" حتى لو لم يصدر في تلك السنة.
             */
            if ($hasCue) {
                $consumed[] = $index;
            }
        }

        return new ExtractionResult($filters, $consumed);
    }

    /**
     * الدوالّ الزمنية الحاضرة في النصّ.
     *
     * @param  string[]  $scripts
     * @return string[]
     */
    private function temporalCuesIn(string $foldedQuery, array $scripts): array
    {
        $found = [];

        foreach ($this->lexicon->temporalCues($scripts) as $cue) {
            if ($cue !== '' && str_contains($foldedQuery, $cue)) {
                $found[] = $cue;
            }
        }

        return $found;
    }

    /**
     * فهارس الوحدات التي هي دوالّ زمنية بحتة.
     *
     * ─── لماذا تُستهلَك ────────────────────────────────────────────
     *
     * "نزل" و"بال" في "الايفون يلي نزل بال 2022" حروف وظيفية تدلّ على
     * الزمن، لا أوصاف للمستند المطلوب. وبقاؤها مصطلحاتٍ يفرضها الوضع
     * الصارم على المطابقة:
     *
     *     +(الايفون* iphone*) +نزل* +بال*
     *
     * فيُشترط أن يحتوي المستند كلمة "نزل" — وهي لا ترد في أي عنوان
     * منتج. أي أن الدالّ الذي ساعدنا على فهم الاستعلام صار هو نفسه
     * ما يمنع تنفيذه.
     *
     * @param  string[]  $tokens
     * @param  string[]  $cues
     * @return int[]
     */
    private function indexesOfCues(array $tokens, array $cues): array
    {
        $cueSet = array_flip($cues);
        $indexes = [];

        foreach ($tokens as $index => $token) {
            if (isset($cueSet[$token])) {
                $indexes[] = $index;
            }
        }

        return $indexes;
    }

    private function isYearToken(string $token): bool
    {
        return preg_match('/^\d{4}$/', $token) === 1
            && $this->isPlausibleYear((int) $token);
    }

    private function isPlausibleYear(int $year): bool
    {
        return $year >= (int) config('search.understanding.min_year', 1900)
            && $year <= (int) config('search.understanding.max_year', 2100);
    }

    /**
     * @param  string[]  $tokens
     * @param  int[]  $years
     * @return int[]
     */
    private function indexesOfYears(array $tokens, array $years): array
    {
        $wanted = array_flip(array_map('strval', $years));
        $indexes = [];

        foreach ($tokens as $index => $token) {
            if (isset($wanted[$token])) {
                $indexes[] = $index;
            }
        }

        return $indexes;
    }
}
