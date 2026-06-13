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
 * Zero behavior change.
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

    private const AR_TO_EN_MAP = [
        'ايفون'    => 'iphone',    'آيفون'    => 'iphone',
        'أيفون'    => 'iphone',    'سامسونج'  => 'samsung',
        'سامسونغ'  => 'samsung',   'لابتوب'   => 'laptop',
        'جوال'     => 'phone',     'هاتف'     => 'phone',
        'موبايل'   => 'mobile',    'تابلت'    => 'tablet',
        'شاشة'     => 'screen',    'كاميرا'   => 'camera',
        'سعر'      => 'price',     'شراء'     => 'buy',
        'رخيص'     => 'cheap',     'ارخص'     => 'cheap',
        'أرخص'     => 'cheap',     'غالي'     => 'expensive',
        'ساعة'     => 'watch',     'سماعات'   => 'headphones',
        'حاسوب'    => 'computer',  'ماك'      => 'mac',
        'بيكسل'    => 'pixel',     'نوكيا'    => 'nokia',
        'جوجل'     => 'google',    'ابل'      => 'apple',
        'أبل'      => 'apple',     'هواوي'    => 'huawei',
        'شاومي'    => 'xiaomi',    'اوبو'     => 'oppo',
        'تلفزيون'  => 'tv',        'تلفاز'    => 'tv',
        'شاحن'     => 'charger',   'بطارية'   => 'battery',
        'كفر'      => 'case',      'غطاء'     => 'cover',
    ];

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

        $fillers      = array_flip(self::FILLER_WORDS);
        $includeWords = array_values(array_filter(
            $includeWords,
            fn($w) => ! isset($fillers[$w])
        ));

        $translatedInclude = $this->translateWords($includeWords);
        $excludeTerms      = $this->buildExcludeTerms($excludeWords, $fillers);

        return [
            'normalized'        => implode(' ', $translatedInclude),
            'excludeTerms'      => $excludeTerms,
            'isNaturalLanguage' => $hadNegation,
            'cleanWords'        => $translatedInclude,
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
        $patterns = self::NEGATION_PATTERNS;
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

    private function normalizeChars(string $text): string
    {
        $text = str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $text);
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $text);
        $text = str_replace('ـ', '', $text);
        return mb_strtolower(trim($text), 'UTF-8');
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

    private function translateWords(array $words): array
    {
        $result = [];
        foreach ($words as $word) {
            $word = trim($word);
            if (mb_strlen($word, 'UTF-8') < 2) continue;
            $result[] = self::AR_TO_EN_MAP[$word] ?? $word;
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

            $translated = self::AR_TO_EN_MAP[$word] ?? $word;
            if (mb_strlen($translated, 'UTF-8') >= 2) {
                $result[] = $translated;
            }
        }
        return array_values(array_unique($result));
    }
}