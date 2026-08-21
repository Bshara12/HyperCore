<?php

declare(strict_types=1);

namespace App\Domains\Search\Support;

/**
 * ArabicQueryNormalizer
 *
 * يُحلّل الـ queries العربية ويفصل include/exclude.
 *
 * Issue #12: يستخدم NegationExtractionTrait لـ CASE A/B/C logic
 * بدل إعادة تنفيذه يدوياً.
 *
 * ─── إصلاح البحث العربي ──────────────────────────────────────────────
 * الخطأ القديم: كان يستبدل الكلمة العربية بمقابلها الإنجليزي
 * ("ايفون" → "iphone")، ثم الـ repository يُقيّد البحث بـ
 * si.language = 'ar' → لا صف عربي يحتوي "iphone" اللاتينية → صفر نتائج.
 * لذلك كان "اي" يُرجع نتائج بينما "ايفون" يُرجع صفراً.
 *
 * الآن: الترجمة **إضافية** لا استبدالية. الكلمة العربية تبقى كما هي
 * (مُطبَّعة) ويُضاف مقابلها الإنجليزي كبديل OR في KeywordProcessor
 * عبر TransliterationMap → "+(ايفون* iphone*)".
 */
final class ArabicQueryNormalizer
{
    use NegationExtractionTrait;

    // ─── Negation Patterns مرتبة تنازلياً بالطول ─────────────────────
    private const NEGATION_PATTERNS = [
        'لا اريد ان'   => 3,
        'لا أريد ان'   => 3,
        'لا ابغى ان'   => 3,
        'لا أبغى ان'   => 3,
        'مش عايزة ان'  => 3,
        'ما بدي'       => 2,
        'ما اريد'      => 2,
        'ما أريد'      => 2,
        'ما ابغى'      => 2,
        'ما أبغى'      => 2,
        'لا اريد'      => 2,
        'لا أريد'      => 2,
        'لا ابغى'      => 2,
        'لا أبغى'      => 2,
        'مش عايز'      => 2,
        'مش عايزة'     => 2,
        'مو بادي'      => 2,
        'مو عايز'      => 2,
        'بدون'         => 1,
        'بدوني'        => 1,
        'غير'          => 1,
        'ماعدا'        => 1,
        'سوى'          => 1,
        'عدا'          => 1,
        'إلا'          => 1,
        'الا'          => 1,
        'مبغاش'        => 1,
        'مابغاش'       => 1,
        'without'      => 1,
        'except'       => 1,
    ];

    private const FILLER_WORDS = [
        'بدي', 'ودي', 'ابي', 'أبي', 'نفسي', 'محتاج', 'محتاجة',
        'حابب', 'حابة', 'عايز', 'عايزة', 'ابغى', 'أبغى',
        'اريد', 'أريد', 'ابغاه', 'ابيه', 'بغيت', 'عندي',
        'يا', 'هلا', 'ممكن', 'لو', 'فيه', 'وين',
        'want', 'need', 'looking', 'find', 'show',
        'please', 'give', 'tell', 'help', 'get',
    ];

    /**
     * ملاحظة: خريطة AR→EN انتقلت إلى TransliterationMap
     * (مصدر واحد للحقيقة، ثنائية الاتجاه، وتُستخدم أيضاً وقت الفهرسة).
     */

    // ─────────────────────────────────────────────────────────────────

    /**
     * @return array{
     *   normalized: string,
     *   excludeTerms: string[],
     *   isNaturalLanguage: bool,
     *   cleanWords: string[]
     * }
     */
    public function normalize(string $query): array
    {
        $normalized = $this->normalizeChars($query);

        [$includeText, $excludeWords, $hadNegation] = $this->extractNegations($normalized);

        $includeWords = $this->splitWords($includeText);

        $fillers      = self::normalizedFillers();
        $includeWords = array_values(array_filter(
            $includeWords,
            fn($w) => ! isset($fillers[$w])
        ));

        $includeTerms = $this->keepWords($includeWords);
        $excludeTerms = $this->buildExcludeTerms($excludeWords, $fillers);

        return [
            'normalized'        => implode(' ', $includeTerms),
            'excludeTerms'      => $excludeTerms,
            'isNaturalLanguage' => $hadNegation,
            'cleanWords'        => $includeTerms,
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // extractNegations — يستخدم applyNegationCases() من الـ Trait
    // ─────────────────────────────────────────────────────────────────

    /**
     * @return array{0: string, 1: string[], 2: bool}
     */
    private function extractNegations(string $text): array
    {
        // الأنماط تُطبَّع لأن $text صار مُطبَّعاً: "لا أريد" لن تُطابق أبداً
        // نصاً صار فيه "لا اريد".
        $patterns = self::normalizedPatterns();
        uksort($patterns, fn($a, $b) => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));

        foreach (array_keys($patterns) as $pattern) {
            $pos = mb_strpos($text, $pattern, 0, 'UTF-8');
            if ($pos === false) {
                continue;
            }

            $beforeText  = trim(mb_substr($text, 0, $pos, 'UTF-8'));
            $afterOffset = $pos + mb_strlen($pattern, 'UTF-8');
            $afterText   = trim(mb_substr($text, $afterOffset, null, 'UTF-8'));
            $afterWords  = $this->splitWords($afterText);

            // ✅ CASE A/B/C من الـ Trait — لا تكرار
            return $this->applyNegationCases($beforeText, $afterWords);
        }

        return [$text, [], false];
    }

    // ─────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────

    /** @var array<string, int>|null */
    private static ?array $patternsCache = null;

    /** @var array<string, bool>|null */
    private static ?array $fillersCache = null;

    /**
     * @return array<string, int>
     */
    private static function normalizedPatterns(): array
    {
        if (self::$patternsCache !== null) {
            return self::$patternsCache;
        }

        $patterns = [];
        foreach (self::NEGATION_PATTERNS as $pattern => $words) {
            $patterns[ArabicTextNormalizer::normalize($pattern)] = $words;
        }

        return self::$patternsCache = $patterns;
    }

    /**
     * @return array<string, bool>
     */
    private static function normalizedFillers(): array
    {
        if (self::$fillersCache !== null) {
            return self::$fillersCache;
        }

        $fillers = [];
        foreach (self::FILLER_WORDS as $word) {
            $fillers[ArabicTextNormalizer::normalizeToken($word)] = true;
        }

        return self::$fillersCache = $fillers;
    }

    /**
     * التطبيع مُفوَّض لـ ArabicTextNormalizer — نفس الدالة المُستخدَمة
     * وقت الفهرسة. أي اختلاف بينهما = بحث عربي معطّل.
     */
    private function normalizeChars(string $text): string
    {
        return ArabicTextNormalizer::normalize($text);
    }

    /**
     * Arabic word splitter — يستخدم Arabic + standard punctuation
     */
    private function splitWords(string $text): array
    {
        if (empty(trim($text))) return [];
        return array_values(array_filter(
            preg_split('/[\s,،.]+/u', $text, -1, PREG_SPLIT_NO_EMPTY),
            fn($w) => mb_strlen(trim($w), 'UTF-8') >= 1
        ));
    }

    /**
     * تُبقي الكلمات العربية كما هي (مُطبَّعة) بلا أي استبدال.
     *
     * المقابل الإنجليزي يُضاف كبديل OR داخل نفس المجموعة في
     * KeywordProcessor::buildTermGroups() — لا هنا، لأن الاستبدال هنا
     * كان يُلغي إمكانية مطابقة النص العربي أصلاً.
     */
    private function keepWords(array $words): array
    {
        $result = [];
        foreach ($words as $word) {
            $word = trim($word);
            if (mb_strlen($word, 'UTF-8') < 2) continue;
            $result[] = $word;
        }
        return array_values(array_unique($result));
    }

    private function buildExcludeTerms(array $rawWords, array $fillers): array
    {
        $result = [];
        foreach ($rawWords as $word) {
            $word = trim($word);
            if (mb_strlen($word, 'UTF-8') < 1) continue;
            if (isset($fillers[$word])) continue;

            if (is_numeric($word)) {
                $result[] = $word;
                continue;
            }

            if (mb_strlen($word, 'UTF-8') >= 2) {
                $result[] = $word;

                // "بدون كفر" يجب أن يستثني "case" أيضاً في النص المختلط
                foreach (TransliterationMap::variantsFor($word) as $variant) {
                    if (mb_strlen($variant, 'UTF-8') >= 2) {
                        $result[] = $variant;
                    }
                }
            }
        }
        return array_values(array_unique($result));
    }
}