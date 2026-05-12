<?php

namespace App\Domains\Search\Support;

/**
 * ArabicQueryNormalizer — Patched
 *
 * Root cause fix:
 *   عندما يكون النفي في بداية الجملة (beforeText = "")
 *   كانت كلمات المنتج تذهب إلى exclude بدل include.
 *
 * القاعدة الجديدة في extractNegations():
 *   afterWords = كل ما بعد النفي
 *   → الأرقام دائماً تذهب إلى exclude
 *   → كلمات المنتج (غير الأرقام):
 *       - إذا beforeText غير فارغ → exclude (المستخدم نفى المنتج فعلاً)
 *       - إذا beforeText فارغ     → include (المنتج هو موضوع البحث، الرقم فقط مستثنى)
 */
class ArabicQueryNormalizer
{
    private const NEGATION_PATTERNS = [
        'لا اريد ان'  => 3,
        'لا أريد ان'  => 3,
        'لا ابغى ان'  => 3,
        'لا أبغى ان'  => 3,
        'مش عايزة ان' => 3,
        'ما بدي'      => 2,
        'ما اريد'     => 2,
        'ما أريد'     => 2,
        'ما ابغى'     => 2,
        'ما أبغى'     => 2,
        'لا اريد'     => 2,
        'لا أريد'     => 2,
        'لا ابغى'     => 2,
        'لا أبغى'     => 2,
        'مش عايز'     => 2,
        'مش عايزة'    => 2,
        'مو بادي'     => 2,
        'مو عايز'     => 2,
        'بدون'        => 1,
        'بدوني'       => 1,
        'غير'         => 1,
        'ماعدا'       => 1,
        'سوى'         => 1,
        'عدا'         => 1,
        'إلا'         => 1,
        'الا'         => 1,
        'مبغاش'       => 1,
        'مابغاش'      => 1,
        'without'     => 1,
        'except'      => 1,
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
        'ايفون'   => 'iphone',    'آيفون'   => 'iphone',
        'أيفون'   => 'iphone',    'سامسونج' => 'samsung',
        'سامسونغ' => 'samsung',   'لابتوب'  => 'laptop',
        'جوال'    => 'phone',     'هاتف'    => 'phone',
        'موبايل'  => 'mobile',    'تابلت'   => 'tablet',
        'شاشة'    => 'screen',    'كاميرا'  => 'camera',
        'سعر'     => 'price',     'شراء'    => 'buy',
        'رخيص'    => 'cheap',     'ارخص'    => 'cheap',
        'أرخص'    => 'cheap',     'غالي'    => 'expensive',
        'ساعة'    => 'watch',     'سماعات'  => 'headphones',
        'حاسوب'   => 'computer',  'ماك'     => 'mac',
        'بيكسل'   => 'pixel',     'نوكيا'   => 'nokia',
        'جوجل'    => 'google',    'ابل'     => 'apple',
        'أبل'     => 'apple',     'هواوي'   => 'huawei',
        'شاومي'   => 'xiaomi',    'اوبو'    => 'oppo',
        'تلفزيون' => 'tv',        'تلفاز'   => 'tv',
        'شاحن'    => 'charger',   'بطارية'  => 'battery',
        'كفر'     => 'case',      'غطاء'    => 'cover',
    ];

    // ─────────────────────────────────────────────────────────────────

    public function normalize(string $query): array
    {
        // Step 1: توحيد الأحرف
        $normalized = $this->normalizeChars($query);

        // Step 2: كشف النفي — الآن يُعيد include + exclude بشكل صحيح
        [$includeText, $excludeWords, $hadNegation] = $this->extractNegations($normalized);

        // Step 3: تقسيم include words
        $includeWords = $this->splitWords($includeText);

        // Step 4: حذف الحشو من include
        $fillers      = array_flip(self::FILLER_WORDS);
        $includeWords = array_values(array_filter(
            $includeWords,
            fn($w) => ! isset($fillers[$w])
        ));

        // Step 5: ترجمة include AR → EN
        $translatedInclude = $this->translateWords($includeWords);

        // Step 6: بناء excludeTerms (ترجمة + أرقام)
        $excludeTerms = $this->buildExcludeTerms($excludeWords, $fillers);

        return [
            'normalized'        => implode(' ', $translatedInclude),
            'excludeTerms'      => $excludeTerms,
            'isNaturalLanguage' => $hadNegation,
            'cleanWords'        => $translatedInclude,
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // THE FIX: extractNegations
    // ─────────────────────────────────────────────────────────────────

    /**
     * @return array{0: string, 1: string[], 2: bool}
     *         [includeText, rawExcludeWords, hadNegation]
     *
     * القاعدة الجديدة:
     *
     * CASE A — beforeText غير فارغ:
     *   "ايفون ما بدي 14"
     *   → includeText    = "ايفون"
     *   → excludeWords   = ["14"]
     *   → المنتج في include، الرقم في exclude ✓
     *
     * CASE B — beforeText فارغ (النفي في البداية) + afterWords تحتوي منتج + رقم:
     *   "ما بدي ايفون 14"
     *   → المستخدم يريد ايفون لكن بدون 14
     *   → includeText    = "ايفون"   ← كلمات المنتج
     *   → excludeWords   = ["14"]    ← الأرقام فقط
     *
     * CASE C — beforeText فارغ + afterWords منتج فقط بدون رقم:
     *   "بدون سامسونج"
     *   → المستخدم لا يريد سامسونج أبداً
     *   → includeText    = ""
     *   → excludeWords   = ["سامسونج"]
     */
    private function extractNegations(string $text): array
    {
        // فرز تنازلي بالطول لمنع partial match
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

            // ── التمييز بين الحالات ───────────────────────────────────
            if ($beforeText !== '') {
                // CASE A: "ايفون ما بدي 14"
                // ما قبل النفي = include، ما بعده = exclude كله
                $excludeWords = array_slice($afterWords, 0, 4);
                return [$beforeText, $excludeWords, true];
            }

            // CASE B or C: النفي في البداية
            // افصل afterWords إلى: منتجات → include، أرقام → exclude
            $productWords = [];
            $numberWords  = [];

            foreach ($afterWords as $word) {
                if (is_numeric($word)) {
                    $numberWords[] = $word;
                } else {
                    $productWords[] = $word;
                }
            }

            if (! empty($numberWords) && ! empty($productWords)) {
                // CASE B: "ما بدي ايفون 14" → include=ايفون, exclude=14
                $includeText  = implode(' ', $productWords);
                $excludeWords = $numberWords;
                return [$includeText, $excludeWords, true];
            }

            // CASE C: "بدون سامسونج" → include='', exclude=سامسونج
            $excludeWords = array_slice($afterWords, 0, 4);
            return ['', $excludeWords, true];
        }

        // لا يوجد نفي
        return [$text, [], false];
    }

    // ─────────────────────────────────────────────────────────────────
    // Helpers (لم تتغير)
    // ─────────────────────────────────────────────────────────────────

    private function normalizeChars(string $text): string
    {
        $text = str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $text);
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $text);
        $text = str_replace('ـ', '', $text);
        return mb_strtolower(trim($text), 'UTF-8');
    }

    private function splitWords(string $text): array
    {
        if (empty(trim($text))) return [];
        $words = preg_split('/[\s,،.]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_filter(
            $words,
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