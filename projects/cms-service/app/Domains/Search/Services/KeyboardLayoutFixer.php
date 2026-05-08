<?php

namespace App\Domains\Search\Services;

class KeyboardLayoutFixer
{
    /*
     * خريطة التحويل: المفتاح الإنجليزي → المقابل العربي
     * مبنية على لوحة المفاتيح القياسية QWERTY ↔ العربية
     *
     * المنطق: عندما يكتب المستخدم بـ layout إنجليزي
     * لكن الـ input method عربي أو العكس
     */
    private const EN_TO_AR = [
        'q' => 'ض', 'w' => 'ص', 'e' => 'ث', 'r' => 'ق', 't' => 'ف',
        'y' => 'غ', 'u' => 'ع', 'i' => 'ه', 'o' => 'خ', 'p' => 'ح',
        'a' => 'ش', 's' => 'س', 'd' => 'ي', 'f' => 'ب', 'g' => 'ل',
        'h' => 'ا', 'j' => 'ت', 'k' => 'ن', 'l' => 'م', ';' => 'ك',
        'z' => 'ئ', 'x' => 'ء', 'c' => 'ؤ', 'v' => 'ر', 'b' => 'لا',
        'n' => 'ى', 'm' => 'ة', ',' => 'و', '.' => 'ز',
        'Q' => 'َ',  'W' => 'ً',  'E' => 'ُ',  'R' => 'ٌ',  'T' => 'لإ',
        'Y' => 'إ',  'U' => '`',  'I' => '÷',  'O' => '×',  'P' => '؛',
        'A' => 'ِ',  'S' => 'ٍ',  'D' => ']',  'F' => '[',  'G' => 'لأ',
        'H' => 'أ',  'J' => 'ـ',  'K' => '،',  'L' => '/',
        'Z' => '~',  'X' => 'ْ',  'C' => '}',  'V' => '{',  'B' => 'لآ',
        'N' => 'آ',  'M' => "'",
    ];

    /*
     * الاتجاه المعاكس: العربي → الإنجليزي
     * يُبنى تلقائياً من EN_TO_AR
     */
    private array $arToEn = [];

    /*
     * أنماط الكلمات الإنجليزية الشائعة
     * للتحقق هل الناتج يبدو إنجليزياً صحيحاً
     */
    private const EN_COMMON_PATTERNS = [
        '/^[a-z]{2,}$/i',           // كلمة إنجليزية بحتة
        '/[aeiou]/i',               // تحتوي على حرف علة
        '/^[a-z]+(\s[a-z]+)*$/i',  // جملة إنجليزية
    ];

    /*
     * أحرف علة إنجليزية للكشف
     */
    private const EN_VOWELS = ['a', 'e', 'i', 'o', 'u'];

    // ─────────────────────────────────────────────────────────────────

    public function __construct()
    {
        // بناء الخريطة المعاكسة AR → EN
        foreach (self::EN_TO_AR as $en => $ar) {
            if (mb_strlen($ar, 'UTF-8') === 1) {
                $this->arToEn[$ar] = (string) $en;
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * الدالة الرئيسية
     *
     * @return array{
     *   original:   string,
     *   fixed:      string|null,
     *   confidence: float,
     *   direction:  string|null,
     * }
     */
    // public function fix(string $query): array
    // {
    //     $query = trim($query);

    //     if (empty($query)) {
    //         return $this->buildResult($query, null, 0.0, null);
    //     }

    //     // ─── 1. كشف نوع الحروف السائدة ───────────────────────────────
    //     $analysis = $this->analyzeCharacters($query);

    //     // ─── 2. إذا مختلط → لا نتدخل ────────────────────────────────
    //     if ($analysis['mixed']) {
    //         return $this->buildResult($query, null, 0.0, null);
    //     }

    //     // ─── 3. تجربة التحويل في الاتجاه المناسب ─────────────────────
    //     if ($analysis['dominantType'] === 'arabic') {
    //         return $this->tryArToEn($query, $analysis);
    //     }

    //     if ($analysis['dominantType'] === 'english') {
    //         return $this->tryEnToAr($query, $analysis);
    //     }

    //     return $this->buildResult($query, null, 0.0, null);
    // }

    /**
 * الدالة الرئيسية - مع قرار صارم قبل التحويل
 *
 * القاعدة الجديدة:
 *   إذا query يبدو إنجليزياً معقولاً → لا تُحوِّله
 *   فقط حوِّل إذا:
 *     - عربي واضح مكتوب بـ layout إنجليزي (ar_to_en)
 *     - OR النص إنجليزي لكن vowel ratio < 0.20 (gibberish عالي الاحتمال)
 */
public function fix(string $query): array
{
    $query = trim($query);

    if (empty($query)) {
        return $this->buildResult($query, null, 0.0, null);
    }

    $analysis = $this->analyzeCharacters($query);

    if ($analysis['mixed']) {
        return $this->buildResult($query, null, 0.0, null);
    }

    // ─── عربي → يحاول تحويل لإنجليزي (AR→EN) ────────────────────
    if ($analysis['dominantType'] === 'arabic') {
        return $this->tryArToEn($query, $analysis);
    }

    // ─── إنجليزي → نُقيِّم قبل التحويل ──────────────────────────
    if ($analysis['dominantType'] === 'english') {
        /*
         * هنا كانت المشكلة:
         * الكود القديم كان يُحوِّل أي نص إنجليزي لعربي
         * حتى "iphoen" كان يُحوَّل لـ "هحاخثى"
         *
         * القاعدة الجديدة:
         *   إنجليزي + vowel ratio معقول (>= 0.20) → ليس keyboard error
         *   إنجليزي + vowel ratio منخفض جداً (< 0.20) → ربما عربي بـ EN layout
         */
        $vowelRatio = $this->calculateVowelRatio($query);

        if ($vowelRatio >= 0.20) {
            /*
             * "iphoen" → vowels: i,o,e = 3/6 = 0.50 ≥ 0.20
             * → إنجليزي معقول حتى لو فيه typo
             * → لا تُحوِّل لعربي، هذا typo إنجليزي يحتاج spell correction
             */
            return $this->buildResult($query, null, 0.0, null);
        }

        /*
         * "kfd" → vowels: 0/3 = 0.0 < 0.20
         * → محتمل أنه عربي مكتوب بـ EN layout
         * → نحاول التحويل
         */
        return $this->tryEnToAr($query, $analysis);
    }

    return $this->buildResult($query, null, 0.0, null);
}

/**
 * حساب نسبة حروف العلة في النص الإنجليزي
 */
private function calculateVowelRatio(string $text): float
{
    $letters = preg_replace('/[^a-z]/i', '', mb_strtolower($text, 'UTF-8'));
    $len     = strlen($letters);

    if ($len === 0) {
        return 0.0;
    }

    $vowels = preg_replace('/[^aeiou]/i', '', $letters);
    return strlen($vowels) / $len;
}

    // ─────────────────────────────────────────────────────────────────
    // Character Analysis
    // ─────────────────────────────────────────────────────────────────

    /**
     * تحليل تركيبة الحروف في الـ query
     *
     * @return array{
     *   arabicRatio:  float,
     *   englishRatio: float,
     *   dominantType: string,
     *   mixed:        bool,
     *   totalChars:   int,
     * }
     */
    private function analyzeCharacters(string $text): array
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $total = 0;
        $arabic = 0;
        $english = 0;

        foreach ($chars as $char) {
            if ($char === ' ' || is_numeric($char)) {
                continue;
            }

            $total++;

            if ($this->isArabicChar($char)) {
                $arabic++;
            } elseif ($this->isEnglishChar($char)) {
                $english++;
            }
        }

        if ($total === 0) {
            return [
                'arabicRatio' => 0.0,
                'englishRatio' => 0.0,
                'dominantType' => 'unknown',
                'mixed' => false,
                'totalChars' => 0,
            ];
        }

        $arabicRatio = $arabic / $total;
        $englishRatio = $english / $total;

        /*
         * "مختلط" = يحتوي كلا النوعين بشكل متقارب
         * مثال: "iphone سعر" → مختلط، لا نُحوِّل
         */
        $mixed = $arabicRatio > 0.2 && $englishRatio > 0.2;

        $dominantType = match (true) {
            $arabicRatio >= 0.7 => 'arabic',
            $englishRatio >= 0.7 => 'english',
            default => 'mixed',
        };

        return [
            'arabicRatio' => round($arabicRatio, 4),
            'englishRatio' => round($englishRatio, 4),
            'dominantType' => $dominantType,
            'mixed' => $mixed,
            'totalChars' => $total,
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // Conversion Attempts
    // ─────────────────────────────────────────────────────────────────

    /**
     * تجربة تحويل Arabic → English
     * (عندما يكتب بـ layout عربي لكن يقصد إنجليزي)
     *
     * مثال: "مشقشرثم" → "laravel"
     */
    private function tryArToEn(string $query, array $analysis): array
    {
        $converted = '';
        $chars = preg_split('//u', $query, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($chars as $char) {
            if ($char === ' ') {
                $converted .= ' ';

                continue;
            }

            $converted .= $this->arToEn[$char] ?? $char;
        }

        $converted = trim($converted);

        // ─── تحقق: هل الناتج يبدو إنجليزياً معقولاً؟ ─────────────────
        $confidence = $this->scoreEnglishOutput($converted, $analysis);

        if ($confidence < 0.4) {
            return $this->buildResult($query, null, 0.0, null);
        }

        return $this->buildResult($query, $converted, $confidence, 'ar_to_en');
    }

    /**
     * تجربة تحويل English → Arabic
     * (عندما يكتب بـ layout إنجليزي لكن يقصد عربي)
     *
     * مثال: "ydfhfk" → "غدفهفك" (أسماء عربية)
     */
    private function tryEnToAr(string $query, array $analysis): array
    {
        $converted = '';
        $lowerQuery = mb_strtolower($query, 'UTF-8');
        $chars = preg_split('//u', $lowerQuery, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($chars as $char) {
            if ($char === ' ') {
                $converted .= ' ';

                continue;
            }

            $converted .= self::EN_TO_AR[$char] ?? $char;
        }

        $converted = trim($converted);

        // ─── تحقق: هل الناتج يحتوي حروفاً عربية كافية؟ ───────────────
        $convertedAnalysis = $this->analyzeCharacters($converted);
        $confidence = $convertedAnalysis['arabicRatio'];

        if ($confidence < 0.6) {
            return $this->buildResult($query, null, 0.0, null);
        }

        return $this->buildResult($query, $converted, round($confidence, 4), 'en_to_ar');
    }

    // ─────────────────────────────────────────────────────────────────
    // Scoring
    // ─────────────────────────────────────────────────────────────────

    /**
     * تقييم جودة الناتج الإنجليزي
     *
     * عوامل الـ score:
     *   1. نسبة الحروف الإنجليزية في الناتج
     *   2. وجود حروف علة (vowels) → الكلمات الإنجليزية الحقيقية تحتوي vowels
     *   3. لا يحتوي حروف عربية متبقية
     *   4. طول معقول
     */
    private function scoreEnglishOutput(string $output, array $originalAnalysis): float
    {
        if (empty(trim($output))) {
            return 0.0;
        }

        $outputAnalysis = $this->analyzeCharacters($output);

        // ─── 1. يجب أن يكون إنجليزياً في الأساس ─────────────────────
        if ($outputAnalysis['englishRatio'] < 0.7) {
            return 0.0;
        }

        $score = 0.0;

        // ─── 2. نسبة الحروف الإنجليزية (0 → 0.4) ─────────────────────
        $score += $outputAnalysis['englishRatio'] * 0.4;

        // ─── 3. وجود حروف علة (0 → 0.3) ──────────────────────────────
        $lowerOutput = mb_strtolower($output, 'UTF-8');
        $outputChars = preg_split('//u', $lowerOutput, -1, PREG_SPLIT_NO_EMPTY);
        $vowelCount = count(array_filter(
            $outputChars,
            fn ($c) => in_array($c, self::EN_VOWELS, true)
        ));

        $totalLetters = count(array_filter($outputChars, fn ($c) => ctype_alpha($c)));

        if ($totalLetters > 0) {
            $vowelRatio = $vowelCount / $totalLetters;
            // الكلمات الإنجليزية الطبيعية: 20-50% vowels
            $vowelScore = $vowelRatio >= 0.15 && $vowelRatio <= 0.6 ? 0.3 : 0.1;
            $score += $vowelScore;
        }

        // ─── 4. لا حروف عربية متبقية (0 → 0.2) ───────────────────────
        if ($outputAnalysis['arabicRatio'] === 0.0) {
            $score += 0.2;
        }

        // ─── 5. طول معقول (0 → 0.1) ──────────────────────────────────
        $wordLengths = array_map('mb_strlen', explode(' ', trim($output)));
        $avgLength = count($wordLengths) > 0 ? array_sum($wordLengths) / count($wordLengths) : 0;

        if ($avgLength >= 2 && $avgLength <= 15) {
            $score += 0.1;
        }

        return round(min(1.0, $score), 4);
    }

    // ─────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────

    private function isArabicChar(string $char): bool
    {
        $code = mb_ord($char, 'UTF-8');

        return ($code >= 0x0600 && $code <= 0x06FF)   // Arabic block
            || ($code >= 0xFB50 && $code <= 0xFDFF)   // Arabic Presentation Forms-A
            || ($code >= 0xFE70 && $code <= 0xFEFF);  // Arabic Presentation Forms-B
    }

    private function isEnglishChar(string $char): bool
    {
        return ctype_alpha($char) && mb_strlen($char, 'UTF-8') === 1 && ord($char) < 128;
    }

    private function buildResult(
        string $original,
        ?string $fixed,
        float $confidence,
        ?string $direction
    ): array {
        return [
            'original' => $original,
            'fixed' => $fixed,
            'confidence' => $confidence,
            'direction' => $direction,
        ];
    }
}
