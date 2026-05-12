<?php

namespace App\Domains\Search\Support;

/**
 * EnglishQueryNormalizer
 *
 * يكشف النفي في الـ queries الإنجليزية ويفصل:
 *   - normalized:    الكلمات المراد البحث عنها (include)
 *   - excludeTerms: الكلمات المستثناة
 *
 * مرايا لـ ArabicQueryNormalizer لكن للإنجليزي.
 *
 * أمثلة:
 *   "iphone not 14"        → include=[iphone]  exclude=[14]
 *   "iphone without 15"    → include=[iphone]  exclude=[15]
 *   "laptop excluding dell" → include=[laptop]  exclude=[dell]
 *   "cheap iphone"          → include=[cheap, iphone]  exclude=[]
 */
class EnglishQueryNormalizer
{
    /**
     * كلمات النفي الإنجليزية + عدد الكلمات التي تستهلكها بعدها
     * مرتبة تنازلياً بالطول لمنع partial match
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
     * كلمات حشو تُحذف من include (لا تُضاف لـ exclude أيضاً)
     * ملاحظة: "not/without" لا تُضاف هنا لأنها تُعالَج كـ negation patterns أولاً
     */
    private const FILLER_WORDS = [
        'want', 'need', 'looking', 'find', 'show',
        'please', 'give', 'tell', 'help', 'get',
        'searching', 'search', 'buy', 'for', 'me',
        'a', 'an', 'the', 'some', 'any',
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
        $lower = mb_strtolower(trim($query), 'UTF-8');

        // ─── كشف النفي وفصل include/exclude ──────────────────────────
        [$includeText, $excludeWords, $hadNegation] = $this->extractNegations($lower);

        // ─── تقسيم include words ──────────────────────────────────────
        $includeWords = $this->splitWords($includeText);

        // ─── حذف الحشو ────────────────────────────────────────────────
        $fillers      = array_flip(self::FILLER_WORDS);
        $includeWords = array_values(array_filter(
            $includeWords,
            fn($w) => ! isset($fillers[$w]) && mb_strlen($w, 'UTF-8') >= 2
        ));

        // ─── تنظيف excludeWords ───────────────────────────────────────
        $excludeTerms = array_values(array_unique(array_filter(
            $excludeWords,
            fn($w) => mb_strlen(trim($w), 'UTF-8') >= 1
        )));

        return [
            'normalized'        => implode(' ', $includeWords),
            'excludeTerms'      => $excludeTerms,
            'isNaturalLanguage' => $hadNegation,
            'cleanWords'        => $includeWords,
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // Core: كشف النفي
    // ─────────────────────────────────────────────────────────────────

    /**
     * @return array{0: string, 1: string[], 2: bool}
     *         [includeText, excludeWords, hadNegation]
     *
     * نفس منطق ArabicQueryNormalizer::extractNegations() بالضبط:
     *
     * CASE A — beforeText غير فارغ:
     *   "iphone not 14"  → include="iphone", exclude=["14"]
     *   "laptop without dell" → include="laptop", exclude=["dell"]
     *
     * CASE B — beforeText فارغ + afterWords: منتج + رقم:
     *   "not iphone 14"  → include="iphone", exclude=["14"]
     *
     * CASE C — beforeText فارغ + afterWords: منتج فقط:
     *   "without samsung" → include="", exclude=["samsung"]
     */
    private function extractNegations(string $text): array
    {
        // فرز تنازلي بالطول لمنع partial match
        // مثال: "not including" يُطابَق قبل "not"
        $patterns = self::NEGATION_PATTERNS;
        uksort($patterns, fn($a, $b) => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));

        foreach (array_keys($patterns) as $pattern) {
            $pos = mb_strpos($text, $pattern, 0, 'UTF-8');
            if ($pos === false) {
                continue;
            }

            // ── تأكد أن الـ pattern كلمة مستقلة (word boundary) ──────
            // منع "notable" يُطابق "not"
            $charBefore = $pos > 0
                ? mb_substr($text, $pos - 1, 1, 'UTF-8')
                : ' ';
            $charAfter  = mb_substr($text, $pos + mb_strlen($pattern, 'UTF-8'), 1, 'UTF-8');

            if ($charBefore !== ' ' && $pos !== 0) {
                continue; // الـ pattern داخل كلمة → تجاهل
            }
            if ($charAfter !== '' && $charAfter !== ' ') {
                continue; // الـ pattern داخل كلمة → تجاهل
            }

            $beforeText  = trim(mb_substr($text, 0, $pos, 'UTF-8'));
            $afterOffset = $pos + mb_strlen($pattern, 'UTF-8');
            $afterText   = trim(mb_substr($text, $afterOffset, null, 'UTF-8'));
            $afterWords  = $this->splitWords($afterText);

            if ($beforeText !== '') {
                // CASE A: كلمات قبل النفي → include، بعده → exclude
                $excludeWords = array_slice($afterWords, 0, 4);
                return [$beforeText, $excludeWords, true];
            }

            // CASE B or C: النفي في البداية
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
                // CASE B: "not iphone 14" → include=iphone, exclude=14
                return [implode(' ', $productWords), $numberWords, true];
            }

            // CASE C: "without samsung" → include='', exclude=samsung
            return ['', array_slice($afterWords, 0, 4), true];
        }

        // لا يوجد نفي
        return [$text, [], false];
    }

    // ─────────────────────────────────────────────────────────────────

    private function splitWords(string $text): array
    {
        if (empty(trim($text))) return [];
        $words = preg_split('/[\s\-_,\.]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_filter(
            $words,
            fn($w) => mb_strlen(trim($w), 'UTF-8') >= 1
        ));
    }

    /**
     * هل الـ query إنجليزي ويحتوي كلمات نفي؟
     * يُستخدم في SearchEntriesAction لتحديد ما إذا يجب تطبيق هذا الـ normalizer
     */
    public function hasNegation(string $query): bool
    {
        $lower    = mb_strtolower(trim($query), 'UTF-8');
        $patterns = array_keys(self::NEGATION_PATTERNS);

        // فرز تنازلي
        usort($patterns, fn($a, $b) => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));

        foreach ($patterns as $pattern) {
            $pos = mb_strpos($lower, $pattern, 0, 'UTF-8');
            if ($pos === false) continue;

            $charBefore = $pos > 0 ? mb_substr($lower, $pos - 1, 1, 'UTF-8') : ' ';
            $charAfter  = mb_substr($lower, $pos + mb_strlen($pattern, 'UTF-8'), 1, 'UTF-8');

            if (($charBefore === ' ' || $pos === 0) && ($charAfter === '' || $charAfter === ' ')) {
                return true;
            }
        }

        return false;
    }
}