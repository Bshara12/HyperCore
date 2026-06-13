<?php

declare(strict_types=1);

namespace App\Domains\Search\Support;

/**
 * EnglishQueryNormalizer
 *
 * يكشف النفي في الـ queries الإنجليزية ويفصل include/exclude.
 *
 * يستخدم NegationExtractionTrait للمنطق المشترك مع ArabicQueryNormalizer.
 *
 * أمثلة:
 *   "iphone not 14"         → include=[iphone]  exclude=[14]
 *   "iphone without 15"     → include=[iphone]  exclude=[15]
 *   "laptop excluding dell" → include=[laptop]  exclude=[dell]
 *   "cheap iphone"          → include=[cheap, iphone] exclude=[]
 *
 * Fixes:
 * - Word boundary bug ("notable" ≠ "not")
 * - Deduplication عبر Trait
 * - Zero behavior regression
 */
final class EnglishQueryNormalizer
{
    use NegationExtractionTrait;

    // ─────────────────────────────────────────────────────────────────────
    // Negation Patterns
    // ─────────────────────────────────────────────────────────────────────

    /**
     * كلمات النفي الإنجليزية
     * مرتبة تنازلياً بالطول لمنع partial match.
     */
    private const NEGATION_PATTERNS = [
        'not including' => 2,
        'other than'    => 2,
        'aside from'    => 2,
        'apart from'    => 2,
        'excluding'     => 1,
        'without'       => 1,
        'except'        => 1,
        'exclude'       => 1,
        'minus'         => 1,
        'not'           => 1,
        'no'            => 1,
    ];

    /**
     * كلمات حشو تُحذف من include/exclude.
     */
    private const FILLER_WORDS = [
        'want', 'need', 'looking', 'find', 'show',
        'please', 'give', 'tell', 'help', 'get',
        'searching', 'search', 'buy', 'for', 'me',
        'a', 'an', 'the', 'some', 'any',
    ];

    // ─────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────

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
        $lower = mb_strtolower(trim($query), 'UTF-8');

        // كشف النفي
        [$includeText, $excludeWords, $hadNegation] =
            $this->extractNegations($lower);

        // تقسيم الكلمات
        $includeWords = $this->splitWords($includeText);

        // إزالة filler words
        $fillers = array_flip(self::FILLER_WORDS);

        $filteredInclude = array_values(array_filter(
            $includeWords,
            fn ($w) => ! isset($fillers[$w])
        ));

        // إزالة الكلمات القصيرة
        $filteredInclude = array_values(array_filter(
            $filteredInclude,
            fn ($w) => mb_strlen($w, 'UTF-8') >= 2
        ));

        // تنظيف excludeTerms
        $excludeTerms = [];

        foreach ($excludeWords as $word) {
            $word = trim($word);

            if (mb_strlen($word, 'UTF-8') < 1) {
                continue;
            }

            if (isset($fillers[$word])) {
                continue;
            }

            $excludeTerms[] = $word;
        }

        $excludeTerms = array_values(array_unique($excludeTerms));

        return [
            'normalized'        => implode(' ', $filteredInclude),
            'excludeTerms'      => $excludeTerms,
            'isNaturalLanguage' => $hadNegation,
            'cleanWords'        => $filteredInclude,
        ];
    }

    /**
     * هل الـ query الإنجليزي يحتوي negation؟
     *
     * يُستخدم في SearchEntriesAction لتحديد
     * ما إذا يجب تطبيق English normalizer.
     */
    public function hasNegation(string $query): bool
    {
        $lower = mb_strtolower(trim($query), 'UTF-8');

        $patterns = array_keys(self::NEGATION_PATTERNS);

        usort($patterns, fn ($a, $b) =>
            mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8')
        );

        foreach ($patterns as $pattern) {
            $pos = mb_strpos($lower, $pattern, 0, 'UTF-8');

            if ($pos === false) {
                continue;
            }

            // يمنع notable من مطابقة not
            if ($this->isWordBoundary(
                $lower,
                $pos,
                mb_strlen($pattern, 'UTF-8')
            )) {
                return true;
            }
        }

        return false;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Negation Extraction
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return array{0: string, 1: string[], 2: bool}
     */
    private function extractNegations(string $text): array
    {
        $patterns = array_keys(self::NEGATION_PATTERNS);

        usort($patterns, fn ($a, $b) =>
            mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8')
        );

        foreach ($patterns as $pattern) {
            $pos = mb_strpos($text, $pattern, 0, 'UTF-8');

            if ($pos === false) {
                continue;
            }

            // Fix: notable ≠ not
            if (! $this->isWordBoundary(
                $text,
                $pos,
                mb_strlen($pattern, 'UTF-8')
            )) {
                continue;
            }

            $beforeText = trim(
                mb_substr($text, 0, $pos, 'UTF-8')
            );

            $afterOffset = $pos + mb_strlen($pattern, 'UTF-8');

            $afterText = trim(
                mb_substr($text, $afterOffset, null, 'UTF-8')
            );

            $afterWords = $this->splitWords($afterText);

            return $this->applyNegationCases(
                $beforeText,
                $afterWords
            );
        }

        return [$text, [], false];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    private function splitWords(string $text): array
    {
        if (empty(trim($text))) {
            return [];
        }

        $words = preg_split(
            '/[\s\-_\,\.]+/u',
            $text,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        return array_values(array_filter(
            $words,
            fn ($w) => mb_strlen(trim($w), 'UTF-8') >= 1
        ));
    }
}
