<?php

namespace App\Domains\Search\Support;

/**
 * KeywordTokenizer
 *
 * يُنتج رموز (tokens) مُطبَّعة عبر ArabicTextNormalizer.
 *
 * لماذا التطبيع هنا إجبارياً؟
 *   هذا الـ tokenizer يُستخدم على **طرفَي** مقارنات تعتمد التطابق التام:
 *     - UserPreferenceAnalyzer: يبني termAffinities من نص الفهرس
 *     - SearchResultRanker:     يُطابق تلك المفاتيح برموز العنوان/المحتوى
 *     - AnalyzeSynonymsAction:  يبني كلمات المرادفات من سجلات البحث
 *   أي اختلاف تطبيع بين الطرفين يُصفّر الشخصنة العربية **صامتاً**:
 *   نقرة على "آيفون" لا تُطابق صفاً عنوانه "أيفون" ولا كلمة بحث "ايفون".
 */
class KeywordTokenizer
{
    /**
     * Stop words لن تُحسب كـ tokens معنوية
     * نفس قائمة KeywordProcessor لكن مستقلة
     *
     * تُقارَن بشكلها المُطبَّع (انظر normalizedStopWords) لأن الرموز
     * الناتجة مُطبَّعة: 'أن' في القائمة لن تُطابق 'ان' الناتجة.
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

    /** @var array<string, bool>|null */
    private static ?array $normalizedStopWords = null;

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
        // 1. تطبيع موحَّد (lowercase + طيّ الهمزات + إزالة التشكيل + أرقام)
        $text = ArabicTextNormalizer::normalize($keyword);

        // 2. إزالة الرموز الخاصة
        $text = preg_replace('/[^\p{L}\p{N}\s\-]/u', ' ', $text);

        // 3. تقسيم
        $words = preg_split('/[\s\-_]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        // 4. فلترة
        $stopWords = self::normalizedStopWords();
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
     * @return array<string, bool>
     */
    private static function normalizedStopWords(): array
    {
        if (self::$normalizedStopWords !== null) {
            return self::$normalizedStopWords;
        }

        $map = [];
        foreach (self::STOP_WORDS as $word) {
            $map[ArabicTextNormalizer::normalizeToken($word)] = true;
        }

        return self::$normalizedStopWords = $map;
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
