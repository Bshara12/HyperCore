<?php

namespace App\Domains\Search\Support;

class ArabicQueryNormalizer
{
    /*
     * ─── Single-word Exclude Signals ─────────────────────────────────
     */
    private const EXCLUDE_SIGNALS = [
        'غير', 'بدون', 'بدوني', 'ماعدا', 'إلا', 'سوى', 'عدا',
        'without', 'except', 'not', 'exclude', 'no', 'minus',
    ];

    /*
     * ─── Multi-word Negation Patterns ────────────────────────────────
     * الترتيب مهم: الأطول أولاً لمنع partial matching
     *
     * كل pattern = [trigger_words, words_to_skip_after_match]
     * مثال: "ما بدي" = skip كلمتين ثم exclude التالية
     */
    private const NEGATION_PATTERNS = [
        // ─── 3 كلمات ─────────────────────────────────────────────────
        ['لا اريد ان',    3, 'ar'],
        ['لا ابغى ان',    3, 'ar'],
        ['مش عايز ان',    3, 'ar'],
        ['don\'t want to', 3, 'en'],

        // ─── 2 كلمات ─────────────────────────────────────────────────
        ['ما بدي',        2, 'ar'],
        ['ما اريد',       2, 'ar'],
        ['ما ابغى',       2, 'ar'],
        ['لا اريد',       2, 'ar'],
        ['لا ابغى',       2, 'ar'],
        ['لا ابي',        2, 'ar'],
        ['مش عايز',       2, 'ar'],
        ['مش عايزة',      2, 'ar'],
        ['مبغاش',         1, 'ar'],  // كلمة واحدة لكن معناها negation
        ['مابغاش',        1, 'ar'],
        ['مو عايز',       2, 'ar'],
        ['مو بادي',       2, 'ar'],
        ['don\'t want',   2, 'en'],
        ['do not want',   3, 'en'],
        ['not looking',   2, 'en'],
        ['no need',       2, 'en'],
        ['without any',   2, 'en'],
    ];

    /*
     * ─── Filler Words ─────────────────────────────────────────────────
     */
    private const FILLER_WORDS = [
        'بدي', 'ودي', 'ابي', 'أبي', 'نفسي', 'محتاج',
        'حابب', 'حابة', 'عايز', 'عايزة', 'ابغى', 'أبغى',
        'حبيب', 'صديق', 'يا', 'يع', 'هلا', 'اريد', 'اريده',
        'ابغاه', 'ابيه', 'بغيت', 'عندي',
        'want', 'need', 'looking', 'find', 'show', 'please',
        'give', 'tell', 'help',
    ];

    /*
     * ─── AR → EN Translations ─────────────────────────────────────────
     */
    private const AR_TO_EN_MAP = [
        'ايفون'   => 'iphone',  'آيفون'   => 'iphone',
        'أيفون'   => 'iphone',  'سامسونج' => 'samsung',
        'لابتوب'  => 'laptop',  'جوال'    => 'phone',
        'هاتف'    => 'phone',   'موبايل'  => 'mobile',
        'تابلت'   => 'tablet',  'شاشة'    => 'screen',
        'كاميرا'  => 'camera',  'سعر'     => 'price',
        'شراء'    => 'buy',     'جديد'    => 'new',
        'قديم'    => 'old',     'رخيص'    => 'cheap',
        'غالي'    => 'expensive','احمر'   => 'red',
        'ابيض'    => 'white',   'اسود'    => 'black',
        'حاسوب'   => 'computer','ساعة'    => 'watch',
        'سماعات'  => 'headphones',
    ];

    // ─────────────────────────────────────────────────────────────────

    public function normalize(string $query): array
    {
        $original = $query;
        $query    = $this->normalizeArabicChars($query);

        // ─── Step 1: كشف multi-word negation patterns أولاً ──────────
        [$query, $patternExcludes, $hadPattern] = $this->extractNegationPatterns($query);

        // ─── Step 2: تقسيم الكلمات ────────────────────────────────────
        $words = $this->splitWords($query);

        $excludeTerms      = $patternExcludes;
        $includedWords     = [];
        $isNaturalLanguage = $hadPattern;

        $i = 0;
        while ($i < count($words)) {
            $word    = $words[$i];
            $wordLow = mb_strtolower($word, 'UTF-8');

            // ─── Filler: تُحذف ────────────────────────────────────────
            if (in_array($wordLow, self::FILLER_WORDS, true)) {
                $isNaturalLanguage = true;
                $i++;
                continue;
            }

            // ─── Single-word Exclude Signal ───────────────────────────
            if (in_array($wordLow, self::EXCLUDE_SIGNALS, true)) {
                $isNaturalLanguage = true;

                /*
                 * استبعاد الكلمات التالية (حتى كلمتين)
                 * "غير الايفون 15" → exclude: [iphone, 15]
                 * لكن نحتاج logic أذكى:
                 *   إذا الكلمة التالية معروفة كـ product → exclude product
                 *   إذا الكلمة التالية رقم → exclude number
                 */
                $nextWords = $this->consumeExcludeTerms($words, $i + 1);
                foreach ($nextWords['terms'] as $term) {
                    $translated = $this->translateWord($term);
                    $excludeTerms[] = $translated;
                }
                $i += 1 + $nextWords['consumed'];
                continue;
            }

            // ─── ترجمة الكلمة ─────────────────────────────────────────
            $translated    = $this->translateWord($word);
            $includedWords[] = $translated;
            $i++;
        }

        // ─── Step 3: فصل أرقام عن كلمات في الـ excludeTerms ──────────
        $numberExcludes = [];
        $wordExcludes   = [];

        foreach ($excludeTerms as $term) {
            if (is_numeric($term)) {
                $numberExcludes[] = $term;
            } else {
                // لا نستبعد الكلمة إذا كانت موجودة أيضاً في الـ include
                // مثال: "ايفون غير الايفون 15" → include: iphone, exclude: 15 فقط
                if (!in_array($term, $includedWords, true)) {
                    $wordExcludes[] = $term;
                }
                // الرقم دائماً يُستبعد
            }
        }

        $normalized = implode(' ', array_filter($includedWords));

        return [
            'normalized'        => $normalized,
            'excludeTerms'      => array_merge($wordExcludes, $numberExcludes),
            'numberExcludes'    => $numberExcludes,
            'wordExcludes'      => $wordExcludes,
            'isNaturalLanguage' => $isNaturalLanguage,
            'originalWords'     => count(explode(' ', trim($original))),
            'cleanWords'        => array_values(array_filter($includedWords)),
        ];
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * استخراج negation patterns من النص قبل التقسيم
     *
     * @return array{0: string, 1: string[], 2: bool}
     *         [cleanedText, excludedTerms, hadPattern]
     */
    private function extractNegationPatterns(string $text): array
    {
        $excludedTerms = [];
        $hadPattern    = false;

        foreach (self::NEGATION_PATTERNS as [$pattern, $skipCount, $lang]) {
            $patternLow = mb_strtolower($pattern, 'UTF-8');

            if (!str_contains($text, $patternLow)) {
                continue;
            }

            $hadPattern = true;

            // استخراج ما بعد الـ pattern
            $pos         = mb_strpos($text, $patternLow, 0, 'UTF-8');
            $afterOffset = $pos + mb_strlen($patternLow, 'UTF-8');
            $afterText   = mb_substr($text, $afterOffset, null, 'UTF-8');

            // الكلمات بعد الـ pattern هي المُستبعدة
            $afterWords = $this->splitWords(trim($afterText));

            /*
             * نأخذ الكلمات التالية كـ exclude
             * لكن فقط إذا كانت ذات معنى بحثي (ليست fillers)
             */
            foreach ($afterWords as $j => $afterWord) {
                if ($j >= 3) break; // أقصى 3 كلمات بعد الـ pattern

                $translated = $this->translateWord($afterWord);

                // أوقف عند filler word
                if (in_array(mb_strtolower($afterWord, 'UTF-8'), self::FILLER_WORDS, true)) {
                    break;
                }

                $excludedTerms[] = $translated;
            }

            // إزالة الـ pattern + ما بعده من النص
            $text = mb_substr($text, 0, $pos, 'UTF-8') .
                    mb_substr($text, $afterOffset, null, 'UTF-8');

            // إزالة الكلمات المُستبعدة من النص المتبقي
            foreach ($excludedTerms as $excluded) {
                $text = str_replace($excluded, ' ', $text);
                // إزالة الكلمة العربية الأصلية أيضاً
                $arOriginal = array_search($excluded, self::AR_TO_EN_MAP);
                if ($arOriginal !== false) {
                    $text = str_replace($arOriginal, ' ', $text);
                }
            }

            $text = trim(preg_replace('/\s+/', ' ', $text));
        }

        return [$text, $excludedTerms, $hadPattern];
    }

    /**
     * استهلاك كلمات الاستبعاد بعد الـ signal
     *
     * الذكاء هنا:
     *   "غير 15"      → consume: ["15"]
     *   "غير الايفون" → consume: ["ايفون"] لكن بما أنه product → نحول لـ productExclude
     *   "غير الايفون 15" → consume: ["ايفون", "15"] → لكن نُعيد iphone للـ include
     */
    private function consumeExcludeTerms(array $words, int $startIndex): array
    {
        $terms    = [];
        $consumed = 0;

        for ($j = $startIndex; $j < count($words) && $consumed < 2; $j++) {
            $word    = $words[$j];
            $wordLow = mb_strtolower($word, 'UTF-8');

            // أوقف عند كلمة جديدة ذات معنى include
            if (in_array($wordLow, self::FILLER_WORDS, true)) {
                break;
            }
            if (in_array($wordLow, self::EXCLUDE_SIGNALS, true)) {
                break;
            }

            $terms[] = $word;
            $consumed++;
        }

        return ['terms' => $terms, 'consumed' => $consumed];
    }

    // ─────────────────────────────────────────────────────────────────

    public function buildBooleanQuery(array $normalizeResult): string
    {
        $parts = [];

        foreach ($normalizeResult['cleanWords'] as $word) {
            if (empty($word)) continue;
            $escaped = $this->escapeForFulltext($word);
            $parts[] = "+{$escaped}*";
        }

        foreach ($normalizeResult['excludeTerms'] as $term) {
            if (empty($term)) continue;
            $escaped = $this->escapeForFulltext($term);
            $parts[] = "-{$escaped}";
        }

        return implode(' ', $parts) ?: '""';
    }

    // ─────────────────────────────────────────────────────────────────

    private function translateWord(string $word): string
    {
        $wordLow = mb_strtolower($word, 'UTF-8');
        return self::AR_TO_EN_MAP[$wordLow] ?? $word;
    }

    private function normalizeArabicChars(string $text): string
    {
        $text = str_replace(['أ', 'إ', 'آ'], 'ا', $text);
        $text = preg_replace('/[\x{064B}-\x{065F}]/u', '', $text);
        $text = str_replace('ـ', '', $text);
        return mb_strtolower(trim($text), 'UTF-8');
    }

    private function splitWords(string $text): array
    {
        $words = preg_split('/[\s,،.]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_filter($words, fn($w) => mb_strlen($w, 'UTF-8') >= 1));
    }

    private function escapeForFulltext(string $word): string
    {
        return preg_replace('/[+\-><\(\)~*"@]+/', '', $word);
    }
}