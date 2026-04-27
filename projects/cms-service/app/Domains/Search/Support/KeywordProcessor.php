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
    'اللتي',
    'كان',
    'كانت',
    'يكون',
    'تكون',
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
  private const MAX_WORDS       = 10;

  // ─────────────────────────────────────────────────────────────────

  public function __construct(
    private SynonymProvider $synonymProvider,
    private IntentDetector  $intentDetector,  // ← إضافة

  ) {}

  // ─────────────────────────────────────────────────────────────────

  public function process(string $rawInput): ProcessedKeyword
  {
    // ─── 1. تنظيف واستخراج كلمات ────────────────────────────────
    $cleaned  = $this->cleanInput($rawInput);
    $words    = $this->tokenize($cleaned);
    $filtered = $this->removeStopWords($words);
    $limited  = array_slice($filtered, 0, self::MAX_WORDS);

    if (empty($limited)) {
      $limited = array_slice($this->tokenize($cleaned), 0, self::MAX_WORDS);
    }

    // ─── 2. كشف النية ────────────────────────────────────────────
    $intent = $this->intentDetector->detect($limited);

    // ─── 3. توسيع المرادفات ──────────────────────────────────────
    $expandedGroups = $this->synonymProvider->expandWords($limited);

    // ─── 4. بناء relaxed queries ─────────────────────────────────
    $relaxedQueries = $this->buildRelaxedQueries($expandedGroups);
    $booleanQuery   = $relaxedQueries[0] ?? '""';
    $primaryWord    = $limited[0] ?? ($words[0] ?? '');

    return new ProcessedKeyword(
      original: $rawInput,
      booleanQuery: $booleanQuery,
      cleanWords: $limited,
      primaryWord: $primaryWord,
      relaxedQueries: $relaxedQueries,
      expandedGroups: $expandedGroups,
      intent: $intent,           // ← إضافة
    );
  }
    // ─────────────────────────────────────────────────────────────────
    // Relaxed Queries Builder - محدَّث مع دعم المرادفات
    // ─────────────────────────────────────────────────────────────────

  /**
   * @param  string[][] $groups  مجموعات الكلمات بعد توسيع المرادفات
   * @return string[]
   */
  public function buildRelaxedQueries(array $groups): array
  {
    if (empty($groups)) {
      return ['""'];
    }

    if (count($groups) === 1) {
      $term = $this->buildGroupTerm($groups[0], required: false);
      return [$term];
    }

    $queries = [];

    // Step 0: STRICT - كل المجموعات required
    $queries[] = $this->buildStrictQuery($groups);

    // Step 1: SEMI-STRICT - الأولى required، الباقي optional
    $semiStrict = $this->buildSemiStrictQuery($groups);
    if ($semiStrict !== $queries[0]) {
      $queries[] = $semiStrict;
    }

    // Step 2: LOOSE - كل المجموعات optional
    $loose = $this->buildLooseQuery($groups);
    if ($loose !== end($queries)) {
      $queries[] = $loose;
    }

    // Step 3: FALLBACK - المجموعة الأولى فقط
    $fallback = $this->buildFallbackQuery($groups);
    if ($fallback !== end($queries)) {
      $queries[] = $fallback;
    }

    return $queries;
  }

    // ─────────────────────────────────────────────────────────────────
    // Query Builders - محدَّثة لتدعم المجموعات
    // ─────────────────────────────────────────────────────────────────

  /**
   * STRICT: +(word* syn1* syn2*) +(word2* syn1*)
   */
  private function buildStrictQuery(array $groups): string
  {
    $terms = array_map(
      fn($group) => $this->buildGroupTerm($group, required: true),
      $groups
    );

    return implode(' ', $terms);
  }

  /**
   * SEMI-STRICT: +(word* syn1* syn2*) word2* word3*
   */
  private function buildSemiStrictQuery(array $groups): string
  {
    $terms = [];

    foreach ($groups as $index => $group) {
      $terms[] = $this->buildGroupTerm(
        $group,
        required: $index === 0   // الأولى فقط required
      );
    }

    return implode(' ', $terms);
  }

  /**
   * LOOSE: (word* syn1* syn2*) (word2* syn1*)
   */
  private function buildLooseQuery(array $groups): string
  {
    $terms = array_map(
      fn($group) => $this->buildGroupTerm($group, required: false),
      $groups
    );

    return implode(' ', $terms);
  }

  /**
   * FALLBACK: (word* syn1* syn2*)  ← المجموعة الأولى فقط
   */
  private function buildFallbackQuery(array $groups): string
  {
    return $this->buildGroupTerm($groups[0], required: false);
  }

    // ─────────────────────────────────────────────────────────────────
    // Group Term Builder - قلب النظام
    // ─────────────────────────────────────────────────────────────────

  /**
   * تحويل مجموعة كلمات إلى MySQL FULLTEXT term
   *
   * كلمة واحدة بدون مرادفات:
   *   required=true  → "+word*"
   *   required=false → "word*"
   *
   * مجموعة بمرادفات:
   *   required=true  → "+(word* syn1* syn2*)"
   *   required=false → "(word* syn1* syn2*)"
   *
   * المنطق داخل المجموعة:
   *   الكلمات داخل () بدون + = OR logic في MySQL FULLTEXT
   *   يكفي وجود أي منها للتطابق
   *
   * @param  string[] $group     [كلمة أصلية, مرادف1, مرادف2]
   * @param  bool     $required  هل هذه المجموعة مطلوبة؟
   */
  private function buildGroupTerm(array $group, bool $required): string
  {
    // ─── مجموعة من كلمة واحدة (لا مرادفات) ─────────────────────
    if (count($group) === 1) {
      $word = $this->escapeWord($group[0]);
      return $required
        ? "+{$word}*"
        : "{$word}*";
    }

    // ─── مجموعة بمرادفات ─────────────────────────────────────────
    /*
         * كل كلمة داخل () تصبح optional (بدون +) حتى يعمل OR logic
         * MySQL FULLTEXT: (a b c) = ابحث عن أي من a أو b أو c
         *
         * ملاحظة: بعض المرادفات قد تكون عبارات متعددة الكلمات
         * مثل "apple phone" - نُعامله كـ phrase
         */
    $innerTerms = array_map(
      fn($word) => $this->buildWordOrPhrase($word),
      $group
    );

    $inner = implode(' ', $innerTerms);

    return $required
      ? "+({$inner})"
      : "({$inner})";
  }

  /**
   * كلمة واحدة → word*
   * عبارة متعددة الكلمات → "phrase" (MySQL FULLTEXT phrase)
   *
   * مثال:
   *   "iphone"      → "iphone*"
   *   "apple phone" → '"apple phone"'
   */
  private function buildWordOrPhrase(string $word): string
  {
    $word = trim($word);

    // إذا كانت عبارة متعددة الكلمات
    if (str_contains($word, ' ')) {
      $escaped = str_replace('"', '', $word); // إزالة quotes داخلية
      return '"' . $escaped . '"';
    }

    return $this->escapeWord($word) . '*';
  }

  /**
   * تنظيف الكلمة من أي رموز خاصة
   */
  private function escapeWord(string $word): string
  {
    return preg_replace('/[+\-><\(\)~*"@#$%^&]+/', '', $word);
  }

  // ─────────────────────────────────────────────────────────────────
  // Helpers
  // ─────────────────────────────────────────────────────────────────

  private function cleanInput(string $input): string
  {
    $input = preg_replace('/[+\-><\(\)~*"@#$%^&=\[\]{}|\\\\]+/', ' ', $input);
    $input = mb_strtolower($input, 'UTF-8');
    return trim(preg_replace('/\s+/', ' ', $input));
  }

  private function tokenize(string $text): array
  {
    $words = preg_split('/[\s\-_,،\.]+/u', $text);

    return array_values(
      array_filter(
        $words,
        fn($w) => mb_strlen($w, 'UTF-8') >= self::MIN_WORD_LENGTH
      )
    );
  }

  private function removeStopWords(array $words): array
  {
    $stopWords = array_flip(self::STOP_WORDS);

    return array_values(
      array_filter(
        $words,
        fn($w) => !isset($stopWords[mb_strtolower($w, 'UTF-8')])
      )
    );
  }
}
