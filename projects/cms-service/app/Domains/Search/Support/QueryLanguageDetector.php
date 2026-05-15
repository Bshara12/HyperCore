<?php

declare(strict_types=1);

namespace App\Domains\Search\Support;

/**
 * QueryLanguageDetector
 *
 * Static utility — لا state، لا dependencies، pure string analysis.
 *
 * لماذا static وليس injectable service؟
 *   - لا DB، لا cache، لا config
 *   - يُستخدم في كل طبقات النظام (Actions, Services, Support classes)
 *   - Injectable service يُضيف DI overhead بلا قيمة حقيقية
 *   - اختبارها مباشر بدون mocking
 *
 * Single source of truth لـ:
 *   - Arabic detection
 *   - Mixed language detection
 *   - Gibberish detection
 *   - Vowel ratio analysis
 */
final class QueryLanguageDetector
{
    // ─── Arabic Unicode blocks ────────────────────────────────────────
    private const ARABIC_BLOCK_PATTERN     = '/[\x{0600}-\x{06FF}]/u';
    private const ARABIC_EXTENDED_PATTERN  = '/[\x{0600}-\x{06FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u';

    // ─── Thresholds ───────────────────────────────────────────────────
    // موحّدة هنا — تغييرها يؤثر على كل النظام
    private const ARABIC_DOMINANT_THRESHOLD = 0.30;
    private const MIXED_ARABIC_MIN          = 0.15;
    private const MIXED_ENGLISH_MIN         = 0.15;
    private const GIBBERISH_VOWEL_RATIO_MIN = 0.10;
    private const GIBBERISH_CONSONANT_RUN   = 5;
    private const GIBBERISH_MIN_LENGTH      = 4;

    private const ENGLISH_VOWELS = ['a', 'e', 'i', 'o', 'u'];

    // ─────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────

    /**
     * هل الـ query عربي بشكل مهيمن؟
     *
     * مثال:
     *   "ما بدي ايفون"   → true  (100% arabic)
     *   "iphone سعر"     → false (mixed)
     *   "iphone"         → false (0% arabic)
     */
    public static function isArabic(string $text): bool
    {
        $clean = self::stripWhitespace($text);
        $total = mb_strlen($clean, 'UTF-8');

        if ($total === 0) {
            return false;
        }

        $arabic = preg_match_all(self::ARABIC_BLOCK_PATTERN, $clean);

        return ($arabic / $total) > self::ARABIC_DOMINANT_THRESHOLD;
    }

    /**
     * هل الـ query يحتوي عربي + إنجليزي بنسب متقاربة؟
     *
     * مثال:
     *   "iphone سعر"       → true
     *   "samsung جوال"     → true
     *   "ما بدي ايفون"     → false (arabic dominant)
     *   "cheap iphone"     → false (english only)
     */
    public static function isMixed(string $text): bool
    {
        $chars   = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $total   = 0;
        $arabic  = 0;
        $english = 0;

        foreach ($chars as $char) {
            if ($char === ' ') {
                continue;
            }

            $total++;
            $code = mb_ord($char, 'UTF-8');

            if ($code >= 0x0600 && $code <= 0x06FF) {
                $arabic++;
            } elseif (ctype_alpha($char) && ord($char) < 128) {
                $english++;
            }
        }

        if ($total === 0) {
            return false;
        }

        return ($arabic / $total) > self::MIXED_ARABIC_MIN
            && ($english / $total) > self::MIXED_ENGLISH_MIN;
    }

    /**
     * هل الـ query English فقط؟ (لا عربي، لا مختلط)
     */
    public static function isEnglishOnly(string $text): bool
    {
        return ! self::isArabic($text) && ! self::isMixed($text);
    }

    /**
     * هل الـ query gibberish؟ (لا معنى له)
     *
     * المنطق المُدمج:
     *   1. Arabic → ليس gibberish (يُعالَج بـ normalizer)
     *   2. قصير جداً → ليس gibberish
     *   3. Vowel ratio منخفض جداً → gibberish
     *   4. Consonant run طويل → gibberish
     *   5. Repeating pattern → gibberish
     *
     * مثال:
     *   "iphoen"     → false (typo, not gibberish — vowel ratio=0.50)
     *   "asdasdasd"  → true  (repeating pattern)
     *   "qwqwqwqw"   → true  (low vowel ratio + repeating)
     *   "kfdghj"     → true  (no vowels)
     *   "iphone"     → false (valid word)
     */
    public static function isGibberish(string $text): bool
    {
        $text = mb_strtolower(trim($text), 'UTF-8');

        if (empty($text)) {
            return false;
        }

        // Arabic لا يُعتبر gibberish — يُعالَج بـ ArabicQueryNormalizer
        if (self::isArabic($text)) {
            return false;
        }

        $letters = preg_replace('/[^a-z]/i', '', $text);
        $len     = strlen($letters);

        if ($len < self::GIBBERISH_MIN_LENGTH) {
            return false;
        }

        // Check 1: Vowel ratio
        if (self::vowelRatio($letters) < self::GIBBERISH_VOWEL_RATIO_MIN) {
            return true;
        }

        // Check 2: Long consonant run
        if (preg_match('/[^aeiou]{' . self::GIBBERISH_CONSONANT_RUN . ',}/i', $letters)) {
            return true;
        }

        // Check 3: Repeating pattern (asdasdasd, qweqweqwe)
        if (self::hasRepeatingPattern($letters)) {
            return true;
        }

        return false;
    }

    /**
     * نسبة حروف العلة في النص الإنجليزي
     * تُستخدم بـ KeyboardLayoutFixer لتمييز typo عن keyboard mismatch
     *
     * مثال:
     *   "iphoen"  → 3/6 = 0.50 (typo, not layout mismatch)
     *   "kfdghj"  → 0/6 = 0.00 (possible layout mismatch)
     */
    public static function vowelRatioOfText(string $text): float
    {
        $letters = preg_replace('/[^a-z]/i', '', mb_strtolower($text, 'UTF-8'));
        $len     = strlen($letters);

        if ($len === 0) {
            return 0.0;
        }

        return self::vowelRatio($letters);
    }

    /**
     * تحليل شامل للـ query يُعيد كل المعلومات دفعة واحدة
     * يُستخدم عند الحاجة لأكثر من معلومة واحدة لتجنب حساب نفس الشيء مرتين
     *
     * @return array{
     *   isArabic: bool,
     *   isMixed: bool,
     *   isEnglishOnly: bool,
     *   isGibberish: bool,
     *   vowelRatio: float,
     *   arabicRatio: float,
     * }
     */
    public static function analyze(string $text): array
    {
        $isArabic = self::isArabic($text);
        $isMixed  = ! $isArabic && self::isMixed($text);

        return [
            'isArabic'     => $isArabic,
            'isMixed'      => $isMixed,
            'isEnglishOnly'=> ! $isArabic && ! $isMixed,
            'isGibberish'  => self::isGibberish($text),
            'vowelRatio'   => self::vowelRatioOfText($text),
            'arabicRatio'  => self::arabicRatio($text),
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // Private Helpers
    // ─────────────────────────────────────────────────────────────────

    private static function stripWhitespace(string $text): string
    {
        return preg_replace('/\s+/', '', $text);
    }

    private static function vowelRatio(string $lettersOnly): float
    {
        $len = strlen($lettersOnly);
        if ($len === 0) return 0.0;

        $vowels = preg_replace('/[^aeiou]/i', '', $lettersOnly);
        return strlen($vowels) / $len;
    }

    private static function arabicRatio(string $text): float
    {
        $clean  = self::stripWhitespace($text);
        $total  = mb_strlen($clean, 'UTF-8');
        if ($total === 0) return 0.0;
        $arabic = preg_match_all(self::ARABIC_BLOCK_PATTERN, $clean);
        return round($arabic / $total, 4);
    }

    /**
     * كشف الأنماط المتكررة
     * "asdasdasd" → "asd" × 3 → gibberish
     * "qweqweqwe" → "qwe" × 3 → gibberish
     */
    private static function hasRepeatingPattern(string $text): bool
    {
        $len = strlen($text);
        if ($len < 6) return false;

        for ($blockLen = 2; $blockLen <= (int)($len / 3); $blockLen++) {
            $block = substr($text, 0, $blockLen);
            $count = substr_count($text, $block);

            if ($count >= 3 && ($count * $blockLen) / $len >= 0.70) {
                return true;
            }
        }

        return false;
    }
}