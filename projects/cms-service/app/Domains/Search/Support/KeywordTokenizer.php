<?php

namespace App\Domains\Search\Support;

class KeywordTokenizer
{
    /**
     * Stop words لن تُحسب كـ tokens معنوية
     * نفس قائمة KeywordProcessor لكن مستقلة
     */
    private const STOP_WORDS = [
        'i', 'me', 'my', 'we', 'our', 'you', 'your', 'he', 'she', 'it',
        'they', 'them', 'what', 'which', 'who', 'this', 'that', 'these',
        'those', 'am', 'is', 'are', 'was', 'were', 'be', 'been', 'have',
        'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could',
        'should', 'may', 'might', 'can', 'a', 'an', 'the', 'and', 'but',
        'or', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from',
        'up', 'about', 'into', 'get', 'got', 'use', 'want', 'make',
        // Arabic
        'في', 'من', 'إلى', 'على', 'عن', 'مع', 'هذا', 'هذه', 'ذلك',
        'هو', 'هي', 'هم', 'أنا', 'أنت', 'نحن', 'التي', 'الذي', 'كان',
        'أن', 'لكن', 'أو', 'و', 'ثم', 'قد', 'لم', 'لن', 'ما', 'لا',
    ];

    private const MIN_WORD_LENGTH = 2;

    private const MAX_WORD_LENGTH = 40;

    // ─────────────────────────────────────────────────────────────────

    /**
     * تحويل keyword إلى مصفوفة tokens نظيفة فريدة
     *
     * مثال:
     *   "Best iphone 15 price"
     *   → ["iphone", "15", "price"]  (حُذفت best كـ stop word)
     *
     * @return string[]
     */
    public function tokenize(string $keyword): array
    {
        // 1. lowercase
        $text = mb_strtolower(trim($keyword), 'UTF-8');

        // 2. إزالة الرموز الخاصة
        $text = preg_replace('/[^\p{L}\p{N}\s\-]/u', ' ', $text);

        // 3. تقسيم
        $words = preg_split('/[\s\-_]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        // 4. فلترة
        $stopWords = array_flip(self::STOP_WORDS);
        $filtered = [];

        foreach ($words as $word) {
            $len = mb_strlen($word, 'UTF-8');

            if ($len < self::MIN_WORD_LENGTH) {
                continue;
            }
            if ($len > self::MAX_WORD_LENGTH) {
                continue;
            }
            if (isset($stopWords[$word])) {
                continue;
            }

            $filtered[] = $word;
        }

        // 5. unique (في نفس الـ keyword)
        return array_values(array_unique($filtered));
    }

    /**
     * tokenize مصفوفة من keywords ويُرجع flat array من tokens
     *
     * @param  string[]  $keywords
     * @return string[][] [keyword_index => [token1, token2, ...]]
     */
    public function tokenizeAll(array $keywords): array
    {
        $result = [];
        foreach ($keywords as $index => $keyword) {
            $tokens = $this->tokenize($keyword);
            if (! empty($tokens)) {
                $result[$index] = $tokens;
            }
        }

        return $result;
    }
}
