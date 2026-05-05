<?php

namespace App\Domains\Search\Support;

class KeywordProcessor
{
    private const STOP_WORDS = [
        'i',
        'me',
        'my',
        'we',
        'our',
        'you',
        'your',
        'he',
        'she',
        'it',
        'its',
        'they',
        'them',
        'their',
        'what',
        'which',
        'who',
        'this',
        'that',
        'these',
        'those',
        'am',
        'is',
        'are',
        'was',
        'were',
        'be',
        'been',
        'being',
        'have',
        'has',
        'had',
        'do',
        'does',
        'did',
        'will',
        'would',
        'could',
        'should',
        'may',
        'might',
        'shall',
        'can',
        'need',
        'dare',
        'ought',
        'a',
        'an',
        'the',
        'and',
        'but',
        'or',
        'nor',
        'for',
        'yet',
        'so',
        'in',
        'on',
        'at',
        'to',
        'for',
        'of',
        'with',
        'by',
        'from',
        'up',
        'about',
        'into',
        'through',
        'during',
        'before',
        'after',
        'above',
        'below',
        'between',
        'out',
        'off',
        'over',
        'then',
        'once',
        'here',
        'there',
        'when',
        'where',
        'why',
        'how',
        'all',
        'both',
        'each',
        'more',
        'most',
        'other',
        'some',
        'such',
        'than',
        'too',
        'very',
        'just',
        'because',
        'as',
        'until',
        'while',
        'although',
        'if',
        'not',
        'no',
        'get',
        'got',
        'also',
        'back',
        'use',
        'used',
        'using',
        'want',
        'make',
        'like',
        'know',
        'think',
        'look',
        'go',
        'best',
        'good',
        'great',
        'top',
        'new',
        'free',
        'easy',
        'في',
        'من',
        'إلى',
        'على',
        'عن',
        'مع',
        'هذا',
        'هذه',
        'ذلك',
        'تلك',
        'هو',
        'هي',
        'هم',
        'هن',
        'أنا',
        'أنت',
        'نحن',
        'التي',
        'الذي',
        'الذين',
        'كان',
        'كانت',
        'يكون',
        'أن',
        'لأن',
        'لكن',
        'أو',
        'و',
        'ثم',
        'قد',
        'لقد',
        'لم',
        'لن',
        'ما',
        'لا',
        'إن',
        'إذا',
        'حتى',
        'بعد',
        'قبل',
        'عند',
        'كل',
        'بعض',
        'أي',
    ];

    private const MIN_WORD_LENGTH = 2;

    private const MAX_WORDS = 10;

    // ─────────────────────────────────────────────────────────────────

    public function __construct(
        private SynonymProvider $synonymProvider,
        private IntentDetector $intentDetector,
        private SynonymExpander $synonymExpander,   // ← إضافة
    ) {}

    // ─────────────────────────────────────────────────────────────────

    /**
     * process بدون DB expansion (للحالات العادية بدون projectId)
     */
    public function process(string $rawInput): ProcessedKeyword
    {
        return $this->processWithExpansion($rawInput, null, null);
    }

    /**
     * process مع DB synonym expansion (الطريقة المُفضَّلة)
     */
    public function processWithExpansion(
        string $rawInput,
        ?int $projectId,
        ?string $language
    ): ProcessedKeyword {

        // ─── 1. تنظيف واستخراج كلمات ────────────────────────────────
        $cleaned = $this->cleanInput($rawInput);
        $words = $this->tokenize($cleaned);
        $filtered = $this->removeStopWords($words);
        $limited = array_slice($filtered, 0, self::MAX_WORDS);

        if (empty($limited)) {
            $limited = array_slice($this->tokenize($cleaned), 0, self::MAX_WORDS);
        }

        // ─── 2. كشف النية ────────────────────────────────────────────
        $intent = $this->intentDetector->detect($limited);

        // ─── 3. DB Synonym Expansion (إذا توفر projectId) ────────────
        $dbExpandedGroups = [];
        $hadDbExpansion = false;

        if ($projectId !== null && $language !== null) {
            $expansionResult = $this->synonymExpander->expand(
                $limited,
                $projectId,
                $language
            );
            $dbExpandedGroups = $expansionResult['groups'];
            $hadDbExpansion = $expansionResult['hadExpansion'];
        }

        // ─── 4. Static Synonym Expansion (SynonymProvider) ───────────
        // نمرر الكلمات المُوسَّعة من DB إذا وُجدت، وإلا الأصلية
        $wordsForStaticExpansion = $hadDbExpansion
          ? $expansionResult['expanded']  // كلمات أصلية + DB synonyms
          : $limited;                     // كلمات أصلية فقط

        $expandedGroups = $this->synonymProvider->expandWords($wordsForStaticExpansion);

        // ─── 5. دمج كل المجموعات في relaxed queries ──────────────────
        $relaxedQueries = $this->buildRelaxedQueries($expandedGroups);
        $booleanQuery = $relaxedQueries[0] ?? '""';
        $primaryWord = $limited[0] ?? ($words[0] ?? '');

        return new ProcessedKeyword(
            original: $rawInput,
            booleanQuery: $booleanQuery,
            cleanWords: $limited,
            primaryWord: $primaryWord,
            relaxedQueries: $relaxedQueries,
            expandedGroups: $expandedGroups,
            intent: $intent,
            dbExpandedGroups: $dbExpandedGroups,
            hadDbExpansion: $hadDbExpansion,
        );
    }

    // ─── باقي الـ methods كما هي ──────────────────────────────────────

    public function buildRelaxedQueries(array $groups): array
    {
        if (empty($groups)) {
            return ['""'];
        }
        if (count($groups) === 1) {
            return [$this->buildGroupTerm($groups[0], required: false)];
        }

        $queries = [];
        $queries[] = $this->buildStrictQuery($groups);

        $semiStrict = $this->buildSemiStrictQuery($groups);
        if ($semiStrict !== $queries[0]) {
            $queries[] = $semiStrict;
        }

        $loose = $this->buildLooseQuery($groups);
        if ($loose !== end($queries)) {
            $queries[] = $loose;
        }

        $fallback = $this->buildFallbackQuery($groups);
        if ($fallback !== end($queries)) {
            $queries[] = $fallback;
        }

        return $queries;
    }

    // private function buildStrictQuery(array $groups): string
    // {
    //     return implode(' ', array_map(
    //         fn($g) => $this->buildGroupTerm($g, required: true), $groups
    //     ));
    // }
    private function buildStrictQuery(array $groups): string
    {
        return implode(' ', array_map(
            fn ($g) => $this->buildGroupTerm($g, required: true, weighted: false),
            $groups
        ));
    }

    // private function buildSemiStrictQuery(array $groups): string
    // {
    //   return implode(' ', array_map(
    //     fn($g, $i) => $this->buildGroupTerm($g, required: $i === 0),
    //     $groups,
    //     array_keys($groups)
    //   ));
    // }
    private function buildSemiStrictQuery(array $groups): string
    {
        return implode(' ', array_map(
            fn ($g, $i) => $this->buildGroupTerm(
                $g,
                required: $i === 0,
                weighted: $i !== 0   // ← الأولى بدون weighting، الباقي مع weighting
            ),
            $groups,
            array_keys($groups)
        ));
    }

    // private function buildLooseQuery(array $groups): string
    // {
    //   return implode(' ', array_map(
    //     fn($g) => $this->buildGroupTerm($g, required: false),
    //     $groups
    //   ));
    // }
    private function buildLooseQuery(array $groups): string
    {
        return implode(' ', array_map(
            fn ($g) => $this->buildGroupTerm($g, required: false, weighted: true),
            $groups
        ));
    }

    // private function buildFallbackQuery(array $groups): string
    // {
    //   return $this->buildGroupTerm($groups[0], required: false);
    // }
    private function buildFallbackQuery(array $groups): string
    {
        return $this->buildGroupTerm($groups[0], required: false, weighted: true);
    }

    // private function buildGroupTerm(array $group, bool $required): string
    // {
    //     if (count($group) === 1) {
    //         $word = $this->escapeWord($group[0]);
    //         return $required ? "+{$word}*" : "{$word}*";
    //     }

    //     $innerTerms = array_map(fn($w) => $this->buildWordOrPhrase($w), $group);
    //     $inner      = implode(' ', $innerTerms);

    //     return $required ? "+({$inner})" : "({$inner})";
    // }

    /**
     * buildGroupTerm المُحدَّثة مع Weighted Synonym Logic
     *
     * استراتيجية المطابقة والترتيب:
     *
     * STRICT mode (required=true):
     *   كلمة واحدة:  "+cost*"
     *   مع مرادفات:  "+(cost* price* fee*)"  ← OR logic للمطابقة
     *                الكل مطلوب مجموعة واحدة
     *
     * LOOSE mode (required=false):
     *   كلمة واحدة:  "cost*"
     *   مع مرادفات:  "cost* (price* fee*)"
     *                ↑ original مستقل (للـ score)
     *                ↑ synonyms في مجموعة optional منفصلة
     *
     * لماذا هذا يعمل؟
     *   في BOOLEAN MODE، كلمة تظهر مرتين → score أعلى
     *   "cost* (price*)" عند entry يحتوي "cost":
     *     → يتطابق cost* → +score
     *     → price* لا يتطابق → لا تأثير
     *   "cost* (price*)" عند entry يحتوي "price":
     *     → cost* لا يتطابق → لا تأثير
     *     → price* يتطابق → +score أقل (لأن داخل مجموعة)
     *
     * @param  string[]  $group  [original, synonym1, synonym2, ...]
     * @param  bool  $required  هل المجموعة مطلوبة؟
     * @param  bool  $weighted  هل نطبق الـ weighting؟ (افتراضي: true)
     */
    private function buildGroupTerm(
        array $group,
        bool $required,
        bool $weighted = true
    ): string {

        // ─── كلمة واحدة بدون مرادفات: لا تغيير ────────────────────────
        if (count($group) === 1) {
            $word = $this->escapeWord($group[0]);

            return $required ? "+{$word}*" : "{$word}*";
        }

        // ─── مجموعة بمرادفات ──────────────────────────────────────────
        $original = $group[0];                     // الكلمة الأصلية
        $synonyms = array_slice($group, 1);        // المرادفات

        if (! $weighted) {
            // ─── السلوك القديم: كل الكلمات متساوية في مجموعة ───────────
            $innerTerms = array_map(fn ($w) => $this->buildWordOrPhrase($w), $group);
            $inner = implode(' ', $innerTerms);

            return $required ? "+({$inner})" : "({$inner})";
        }

        // ─── السلوك الجديد: Original مستقل، Synonyms في مجموعة optional ─
        return $this->buildWeightedGroupTerm($original, $synonyms, $required);
    }

    /**
     * بناء term مُرجَّح: original مستقل + synonyms optional
     *
     * الصيغة:
     *
     *   STRICT (required=true, للـ WHERE filtering):
     *     "+(cost* price* fee*)"
     *     → نستخدم الـ OR group للتأكد أن المطابقة تشمل كل الاحتمالات
     *     → لا نريد أن يُخفق البحث إذا استخدم المستخدم "price" بدل "cost"
     *
     *   LOOSE (required=false, للـ Ranking):
     *     "cost* (price* fee*)"
     *     → original مستقل = يُحسب في الـ score بشكل منفصل
     *     → synonyms في مجموعة = يُضيفون score إضافي لكن أقل
     *
     * مثال عملي:
     *   Entry A: "iPhone Cost Comparison"
     *     cost* يتطابق (score +X)
     *     (price* fee*) لا يتطابق
     *     Total boost: X
     *
     *   Entry B: "iPhone Price Guide"
     *     cost* لا يتطابق
     *     (price* fee*) price يتطابق (score +Y, Y < X)
     *     Total boost: Y
     *
     *   Entry A يظهر قبل Entry B ✅
     *   لكن كلاهما يظهر في النتائج ✅
     */
    private function buildWeightedGroupTerm(
        string $original,
        array $synonyms,
        bool $required
    ): string {

        $originalTerm = $this->buildWordOrPhrase($original);

        // ─── STRICT: OR group للمطابقة الشاملة ──────────────────────────
        if ($required) {
            /*
               * نجمع original + synonyms في OR group واحدة
               * الهدف: ضمان المطابقة حتى لو استخدم المستخدم مرادفاً
               *
               * "+(cost* price* fee*)"
               * = يجب أن يحتوي على cost أو price أو fee
               */
            $allTerms = array_map(
                fn ($w) => $this->buildWordOrPhrase($w),
                array_merge([$original], $synonyms)
            );

            return '+('.implode(' ', $allTerms).')';
        }

        // ─── LOOSE: Original مستقل + Synonyms optional ───────────────────
        /*
         * "cost* (price* fee*)"
         *
         * في BOOLEAN MODE:
         *   cost*         = optional term (يرفع score إذا وُجد)
         *   (price* fee*) = optional group (يرفع score إذا وُجد أي منهما)
         *
         * الـ score الفعلي:
         *   entry يحتوي "cost":  score = FULLTEXT(cost) + position_bonus
         *   entry يحتوي "price": score = FULLTEXT(price) فقط (أقل عادة)
         *
         * هذا يُحقق الـ weighting بشكل طبيعي لأن:
         *   - original term يُحسب كـ independent term
         *   - synonyms يُحسبون كـ group واحدة
         */
        $synonymTerms = array_map(
            fn ($w) => $this->buildWordOrPhrase($w),
            $synonyms
        );

        $synonymGroup = '('.implode(' ', $synonymTerms).')';

        return "{$originalTerm} {$synonymGroup}";
    }

    private function buildWordOrPhrase(string $word): string
    {
        $word = trim($word);
        if (str_contains($word, ' ')) {
            return '"'.str_replace('"', '', $word).'"';
        }

        return $this->escapeWord($word).'*';
    }

    private function escapeWord(string $word): string
    {
        return preg_replace('/[+\-><\(\)~*"@#$%^&]+/', '', $word);
    }

    private function cleanInput(string $input): string
    {
        $input = preg_replace('/[+\-><\(\)~*"@#$%^&=\[\]{}|\\\\]+/', ' ', $input);
        $input = mb_strtolower($input, 'UTF-8');

        return trim(preg_replace('/\s+/', ' ', $input));
    }

    private function tokenize(string $text): array
    {
        $words = preg_split('/[\s\-_,،\.]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter(
            $words,
            fn ($w) => mb_strlen($w, 'UTF-8') >= self::MIN_WORD_LENGTH
        ));
    }

    private function removeStopWords(array $words): array
    {
        $stopWords = array_flip(self::STOP_WORDS);

        return array_values(array_filter(
            $words,
            fn ($w) => ! isset($stopWords[mb_strtolower($w, 'UTF-8')])
        ));
    }
}
