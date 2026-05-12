<?php

namespace App\Domains\Search\Services;

use App\Domains\Search\DTOs\SearchQueryDTO;
use App\Domains\Search\DTOs\UserPreferenceDTO;
use App\Domains\Search\Repositories\Interfaces\SearchRepositoryInterface;
use App\Domains\Search\Support\ArabicQueryNormalizer;
use App\Domains\Search\Support\KeywordProcessor;
use App\Domains\Search\Support\UserPreferenceAnalyzer;
use Illuminate\Support\Facades\DB;

class SearchDebugService
{
  public function __construct(
    private ArabicQueryNormalizer     $arabicNormalizer,
    private KeywordProcessor          $processor,
    private UserPreferenceAnalyzer    $preferenceAnalyzer,
    private SearchRepositoryInterface $repository,
    private KeyboardLayoutFixer       $keyboardFixer,
    private AIQueryInterpreter        $aiInterpreter,   // ← AIQueryInterpreter فقط
  ) {}

  // ─────────────────────────────────────────────────────────────────

  public function runDebugPipeline(
    string $keyword,
    string $language,
    int    $projectId
  ): array {
    $startTime     = microtime(true);
    $decisionTrace = [];

    // ─── Step 1: Normalization ────────────────────────────────────
    $isArabic      = $this->isArabicQuery($keyword);
    $normalizeInfo = [
      'normalized' => $keyword,
      'excludeTerms' => [],
      'isNaturalLanguage' => false,
      'cleanWords' => []
    ];

    if ($isArabic || $language === 'ar') {
      $normalizeInfo = $this->arabicNormalizer->normalize($keyword);
    }
    $effectiveKeyword = !empty($normalizeInfo['normalized'])
      ? $normalizeInfo['normalized']
      : $keyword;

    $normalizationData = [
      'detected_arabic'     => $isArabic,
      'original'            => $keyword,
      'normalized'          => $effectiveKeyword,
      'exclude_terms'       => $normalizeInfo['excludeTerms'] ?? [],
      'is_natural_language' => $normalizeInfo['isNaturalLanguage'] ?? false,
      'word_count_before'   => str_word_count($keyword),
      'word_count_after'    => str_word_count($effectiveKeyword),
    ];

    // ─── Step 2: Pre-AI Analysis ──────────────────────────────────
    $isGibberish  = $this->detectGibberish($effectiveKeyword);
    $typoSignals  = $this->detectTypoSignals($effectiveKeyword);
    $hasNegation  = $this->containsNegation($keyword);

    $preAiData = [
      'is_gibberish'  => $isGibberish,
      'has_negation'  => $hasNegation,
      'typo_signals'  => $typoSignals,
      'vowel_ratio'   => $this->getVowelRatio($effectiveKeyword),
      'token_count'   => count(array_filter(
        explode(' ', $effectiveKeyword),
        fn($w) => mb_strlen($w) >= 2
      )),
    ];

    // ─── Step 3: Keyword Processing ───────────────────────────────
    $processed = $this->processor->processWithExpansion(
      $effectiveKeyword,
      $projectId,
      $language
    );
    $processingData = [
      'clean_words'     => $processed->cleanWords,
      'boolean_query'   => $processed->booleanQuery,
      'relaxed_queries' => $processed->relaxedQueries,
      'intent'          => $processed->intent,
      'had_db_expansion' => $processed->hadDbExpansion,
    ];

    // ─── Step 4: Initial Search ───────────────────────────────────
    $dto = new SearchQueryDTO(
      keyword: $effectiveKeyword,
      projectId: $projectId,
      language: $language,
      page: 1,
      perPage: 5,
    );

    $preference   = UserPreferenceDTO::noHistory();
    $excludeTerms = $normalizeInfo['excludeTerms'] ?? [];

    $initialResult = $this->repository->searchWithExclusions(
      $dto,
      $processed,
      $preference,
      $excludeTerms
    );

    $decisionTrace[] = $initialResult['total'] > 0
      ? "✅ Initial search: {$initialResult['total']} results"
      : "❌ Initial search: 0 results";

    if ($isGibberish)  $decisionTrace[] = "⚠️ Gibberish detected";
    if ($hasNegation)  $decisionTrace[] = "🚫 Negation: exclude=" . implode(',', $excludeTerms);

    $initialSearchData = [
      'total'         => $initialResult['total'],
      'exclude_terms' => $excludeTerms,
      'top_results'   => $this->mapResultRows(
        array_slice($initialResult['items'], 0, 5),
        $processed->cleanWords
      ),
    ];

    // ─── Step 5: Keyboard Fix ─────────────────────────────────────
    $keyboardData = $this->simulateKeyboardFix(
      keyword: $keyword,
      projectId: $projectId,
      language: $language,
      preference: $preference,
      initialTotal: $initialResult['total'],
      isGibberish: $isGibberish,
      decisionTrace: $decisionTrace,
    );

    // ─── Step 6: AI Simulation ────────────────────────────────────
    $threshold    = (int) config('search.ai_trigger_threshold', 0);
    $needsFallback = $initialResult['total'] <= $threshold
      || $isGibberish
      || empty($processed->cleanWords)
      || $hasNegation;

    $aiData = $this->simulateAI(
      keyword: $keyword,
      language: $language,
      projectId: $projectId,
      preference: $preference,
      needsFallback: $needsFallback,
      initialTotal: $initialResult['total'],
      excludeTerms: $excludeTerms,
      isGibberish: $isGibberish,
      hasNegation: $hasNegation,
      decisionTrace: $decisionTrace,
    );

    // ─── Step 7: Final Results ────────────────────────────────────
    if ($aiData['triggered'] && !empty($aiData['include'])) {
      $finalItems  = $this->getFinalResults(
        $aiData['final_query'],
        $aiData['final_exclude'],
        $projectId,
        $language,
        $preference
      );
      $finalTotal  = $aiData['total_after'];
      $finalSource = 'ai';
    } elseif ($keyboardData['triggered'] && $keyboardData['total_after'] > 0) {
      $finalItems  = $keyboardData['top_results_after'] ?? $initialResult['items'];
      $finalTotal  = $keyboardData['total_after'];
      $finalSource = 'keyboard';
    } else {
      $finalItems  = $initialResult['items'];
      $finalTotal  = $initialResult['total'];
      $finalSource = 'initial';
    }

    // الكلمات المستخدمة للـ highlight (من AI أو من processing)
    $matchWords = !empty($aiData['include'])
      ? $aiData['include']
      : $processed->cleanWords;

    return [
      'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
      'decision_trace'    => $decisionTrace,
      'input'             => [
        'original'   => $keyword,
        'language'   => $language,
        'project_id' => $projectId,
      ],
      'normalization'   => $normalizationData,
      'pre_ai_analysis' => $preAiData,
      'processing'      => $processingData,
      'initial_search'  => $initialSearchData,
      'keyboard_fix'    => $keyboardData,
      'ai'              => $aiData,
      'final'           => [
        'total'   => $finalTotal,
        'source'  => $finalSource,
        'results' => $this->mapResultRows(array_slice($finalItems, 0, 10), $matchWords),
      ],
    ];
  }

  // ─────────────────────────────────────────────────────────────────
  // Keyboard Fix Simulation
  // ─────────────────────────────────────────────────────────────────

  private function simulateKeyboardFix(
    string            $keyword,
    int               $projectId,
    string            $language,
    UserPreferenceDTO $preference,
    int               $initialTotal,
    bool              $isGibberish,
    array             &$decisionTrace
  ): array {

    $kbFix = $this->keyboardFixer->fix($keyword);
    $threshold    = (int) config('search.ai_trigger_threshold', 0);
    $needsFallback = $initialTotal <= $threshold || $isGibberish;

    if (!$needsFallback) {
      $decisionTrace[] = "⏭️ Keyboard: skipped (has results)";
      return [
        'triggered'        => false,
        'decision'         => 'skipped_has_results',
        'fixed_query'      => $kbFix['fixed'],
        'confidence'       => $kbFix['confidence'],
        'direction'        => $kbFix['direction'] ?? null,
        'total_after'      => $initialTotal,
        'top_results_after' => [],
      ];
    }

    $minConf    = $isGibberish ? 0.25 : 0.4;
    $triggered  = $kbFix['fixed'] !== null && $kbFix['confidence'] >= $minConf;
    $totalAfter = 0;
    $topAfter   = [];
    $decision   = 'no_fix';

    if ($kbFix['fixed'] === null) {
      $decision = 'no_conversion_found';
    } elseif ($kbFix['confidence'] < $minConf) {
      $decision = "low_confidence({$kbFix['confidence']} < {$minConf})";
    } else {
      $decision = 'accepted';
      $fixedProcessed = $this->processor->processWithExpansion(
        $kbFix['fixed'],
        $projectId,
        $language
      );
      $fixedDto = new SearchQueryDTO(
        keyword: $kbFix['fixed'],
        projectId: $projectId,
        language: $language,
        page: 1,
        perPage: 5,
      );
      $kbResult   = $this->repository->searchWithExclusions(
        $fixedDto,
        $fixedProcessed,
        $preference,
        []
      );
      $totalAfter = $kbResult['total'];
      $topAfter   = $this->mapResultRows(
        array_slice($kbResult['items'], 0, 5),
        $fixedProcessed->cleanWords
      );
    }

    $decisionTrace[] = $triggered
      ? "✅ Keyboard: '{$kbFix['fixed']}' (conf:{$kbFix['confidence']}) → {$totalAfter} results"
      : "⏭️ Keyboard: {$decision}";

    return [
      'triggered'        => $triggered,
      'decision'         => $decision,
      'fixed_query'      => $kbFix['fixed'],
      'confidence'       => $kbFix['confidence'],
      'direction'        => $kbFix['direction'] ?? null,
      'total_after'      => $totalAfter,
      'top_results_after' => $topAfter,
    ];
  }

  // ─────────────────────────────────────────────────────────────────
  // AI Simulation - يستخدم AIQueryInterpreter فقط
  // ─────────────────────────────────────────────────────────────────

  private function simulateAI(
    string            $keyword,
    string            $language,
    int               $projectId,
    UserPreferenceDTO $preference,
    bool              $needsFallback,
    int               $initialTotal,
    array             $excludeTerms,
    bool              $isGibberish,
    bool              $hasNegation,
    array             &$decisionTrace
  ): array {

    $aiEnabled = (bool) config('search.ai_enabled', false);

    // ─── قرار تشغيل AI ───────────────────────────────────────────
    if (!$aiEnabled) {
      $decisionTrace[] = "⛔ AI: disabled (AI_SEARCH_ENABLED=false)";
      return $this->buildEmptyAIResult('disabled', $initialTotal);
    }

    if (!$needsFallback) {
      $decisionTrace[] = "⏭️ AI: skipped (results exist, no negation)";
      return $this->buildEmptyAIResult('skipped_has_results', $initialTotal);
    }

    // ─── استدعاء AIQueryInterpreter ──────────────────────────────
    try {
      $interpretation = $this->aiInterpreter->interpret($keyword, $language);
    } catch (\Throwable $e) {
      $decisionTrace[] = "❌ AI: error ({$e->getMessage()})";
      return $this->buildEmptyAIResult('error:' . $e->getMessage(), $initialTotal);
    }

    $decisionTrace[] = sprintf(
      "🤖 AI: interpreted → include:[%s] exclude:[%s] intent:%s conf:%.2f source:%s",
      implode(',', $interpretation['include']),
      implode(',', $interpretation['exclude']),
      $interpretation['intent'],
      $interpretation['confidence'],
      $interpretation['source']
    );

    // ─── تحقق من usability ───────────────────────────────────────
    $mergedExclude = array_unique(
      array_merge($excludeTerms, $interpretation['exclude'])
    );

    $includeTokens = $interpretation['include'];

    // إذا include فارغ → جرب corrected
    if (empty($includeTokens) && !empty(trim($interpretation['corrected'] ?? ''))) {
      $includeTokens = array_values(array_filter(
        explode(' ', mb_strtolower(trim($interpretation['corrected']))),
        fn($w) => mb_strlen($w) >= 2
      ));
    }

    $hasUsableData = !empty($includeTokens) || !empty($mergedExclude);

    if (!$hasUsableData && $interpretation['confidence'] < 0.1) {
      $decisionTrace[] = "⏭️ AI: no usable tokens (gibberish?)";
      return $this->buildEmptyAIResult('no_tokens', $initialTotal);
    }

    // ─── حالة exclude-only ───────────────────────────────────────
    if (empty($includeTokens) && !empty($mergedExclude)) {
      $decisionTrace[] = "🔀 AI: exclude-only search, exclude=" . implode(',', $mergedExclude);

      $emptyProcessed = $this->processor->processWithExpansion('', $projectId, $language);
      $emptyDto       = new SearchQueryDTO(
        keyword: '',
        projectId: $projectId,
        language: $language,
        page: 1,
        perPage: 5
      );
      $altResult = $this->repository->searchWithExclusions(
        $emptyDto,
        $emptyProcessed,
        $preference,
        $mergedExclude
      );

      $decisionTrace[] = "✅ AI exclude-only: {$altResult['total']} results";

      return [
        'triggered'    => true,
        'source'       => $interpretation['source'],
        'include'      => [],
        'exclude'      => $mergedExclude,
        'intent'       => $interpretation['intent'],
        'confidence'   => $interpretation['confidence'],
        'expanded'     => $interpretation['expanded'],
        'final_query'  => '',
        'final_exclude' => $mergedExclude,
        'total_after'  => $altResult['total'],
        'top_results'  => $this->mapResultRows(
          array_slice($altResult['items'], 0, 5),
          []
        ),
      ];
    }

    // ─── بناء AI query من tokens فقط ─────────────────────────────
    $allTokens = array_unique(array_merge(
      $includeTokens,
      array_slice($interpretation['expanded'] ?? [], 0, 2)
    ));

    $aiKeyword = implode(' ', array_slice($allTokens, 0, 8));

    $aiProcessed = $this->processor->processWithExpansion($aiKeyword, $projectId, $language);
    $aiDto       = new SearchQueryDTO(
      keyword: $aiKeyword,
      projectId: $projectId,
      language: $language,
      page: 1,
      perPage: 5,
    );

    $aiResult = $this->repository->searchWithExclusions(
      $aiDto,
      $aiProcessed,
      $preference,
      $mergedExclude
    );

    $decisionTrace[] = "✅ AI search: '{$aiKeyword}' → {$aiResult['total']} results";

    return [
      'triggered'    => true,
      'source'       => $interpretation['source'],
      'include'      => $includeTokens,
      'exclude'      => $mergedExclude,
      'intent'       => $interpretation['intent'],
      'confidence'   => $interpretation['confidence'],
      'expanded'     => $interpretation['expanded'] ?? [],
      'final_query'  => $aiKeyword,
      'final_exclude' => $mergedExclude,
      'total_after'  => $aiResult['total'],
      'top_results'  => $this->mapResultRows(
        array_slice($aiResult['items'], 0, 5),
        $includeTokens
      ),
    ];
  }

  // ─────────────────────────────────────────────────────────────────
  // Gibberish Detection - مُحسَّن
  // ─────────────────────────────────────────────────────────────────

  private function detectGibberish(string $text): bool
  {
    $text = mb_strtolower(trim($text), 'UTF-8');

    if (empty($text) || mb_strlen($text) < 3) {
      return false;
    }

    // عربي → نتركه للـ normalizer
    if ($this->isArabicQuery($text)) {
      return false;
    }

    $letters = preg_replace('/[^a-z]/i', '', $text);
    $len     = strlen($letters);

    if ($len < 3) {
      return false;
    }

    // ─── Check 1: Vowel ratio ─────────────────────────────────────
    $vowels     = preg_replace('/[^aeiou]/i', '', $letters);
    $vowelRatio = strlen($vowels) / $len;

    if ($vowelRatio < 0.10) {
      return true;
    }

    // ─── Check 2: Consonant cluster ───────────────────────────────
    if (preg_match('/[^aeiou]{5,}/i', $letters)) {
      return true;
    }

    // ─── Check 3: Repeated pattern ───────────────────────────────
    // "asdasdasd" → repeating block = gibberish
    if ($this->hasRepeatingPattern($letters)) {
      return true;
    }

    // ─── Check 4: كل الكلمات قصيرة جداً ─────────────────────────
    $words     = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
    $longWords = array_filter(
      $words,
      fn($w) => strlen(preg_replace('/[^a-z]/i', '', $w)) >= 3
    );

    if (count($words) > 2 && empty($longWords)) {
      return true;
    }

    return false;
  }

  /**
   * كشف الأنماط المتكررة
   * "asdasdasd" → "asd" × 3 = gibberish
   * "abcabcabc" → "abc" × 3 = gibberish
   */
  private function hasRepeatingPattern(string $text): bool
  {
    $len = strlen($text);

    if ($len < 6) {
      return false;
    }

    // جرب أطوال blocks من 2 إلى len/3
    for ($blockLen = 2; $blockLen <= (int)($len / 3); $blockLen++) {
      $block     = substr($text, 0, $blockLen);
      $repeated  = str_repeat($block, (int)($len / $blockLen));
      $truncated = substr($repeated, 0, $len);

      // إذا الـ block يتكرر ≥ 3 مرات
      if ($truncated === $text && $len / $blockLen >= 3) {
        return true;
      }
    }

    // كشف أكثر مرونة: نسبة التكرار
    // إذا نفس الـ block يظهر 3+ مرات كـ substring
    for ($blockLen = 2; $blockLen <= min(5, (int)($len / 3)); $blockLen++) {
      $block = substr($text, 0, $blockLen);
      $count = substr_count($text, $block);

      if ($count >= 3 && ($count * $blockLen) / $len >= 0.7) {
        return true;
      }
    }

    return false;
  }

  // ─────────────────────────────────────────────────────────────────
  // Helpers
  // ─────────────────────────────────────────────────────────────────

  private function buildEmptyAIResult(string $reason, int $initialTotal): array
  {
    return [
      'triggered'    => false,
      'reason'       => $reason,
      'source'       => 'none',
      'include'      => [],
      'exclude'      => [],
      'intent'       => '',
      'confidence'   => 0.0,
      'expanded'     => [],
      'final_query'  => '',
      'final_exclude' => [],
      'total_after'  => $initialTotal,
      'top_results'  => [],
    ];
  }

  private function getFinalResults(
    string            $keyword,
    array             $excludeTerms,
    int               $projectId,
    string            $language,
    UserPreferenceDTO $preference
  ): array {
    if (empty(trim($keyword))) {
      return [];
    }
    $processed = $this->processor->processWithExpansion($keyword, $projectId, $language);
    $dto       = new SearchQueryDTO(
      keyword: $keyword,
      projectId: $projectId,
      language: $language,
      page: 1,
      perPage: 10
    );
    $result = $this->repository->searchWithExclusions($dto, $processed, $preference, $excludeTerms);
    return $result['items'];
  }

  private function mapResultRows(array $rows, array $matchWords): array
  {
    return array_map(function ($row) use ($matchWords) {
      return [
        'entry_id'     => (int) $row->entry_id,
        'title'        => $row->title ?? '',
        'score'        => round((float)($row->weighted_score ?? $row->fulltext_score ?? 0), 4),
        'data_type'    => $row->data_type_slug ?? '',
        'matched_terms' => $this->findMatchedTerms($row->title ?? '', $matchWords),
      ];
    }, $rows);
  }

  private function findMatchedTerms(string $title, array $words): array
  {
    $matched = [];
    $lower   = mb_strtolower($title, 'UTF-8');

    foreach ($words as $word) {
      if (!empty($word) && str_contains($lower, mb_strtolower($word, 'UTF-8'))) {
        $matched[] = $word;
      }
    }

    return array_values(array_unique($matched));
  }

  private function detectTypoSignals(string $text): array
  {
    $signals = [];
    $letters = preg_replace('/[^a-z]/i', '', mb_strtolower($text));

    if (strlen($letters) >= 3) {
      $vowels = preg_replace('/[^aeiou]/i', '', $letters);
      $ratio  = strlen($vowels) / strlen($letters);

      if ($ratio < 0.15) $signals[] = "low_vowel_ratio({$ratio})";
      if (preg_match('/[^aeiou]{5,}/i', $letters)) $signals[] = 'consonant_cluster';
    }

    if ($this->hasRepeatingPattern(preg_replace('/[^a-z]/i', '', $text))) {
      $signals[] = 'repeating_pattern';
    }

    return $signals;
  }

  private function containsNegation(string $text): bool
  {
    $text    = mb_strtolower($text, 'UTF-8');
    $signals = [
      'لا',
      'ما',
      'غير',
      'بدون',
      'مش',
      'مو',
      'ما بدي',
      'لا اريد',
      'without',
      'not',
      "don't",
      'exclude'
    ];

    foreach ($signals as $s) {
      if (str_contains($text, $s)) return true;
    }

    return false;
  }

  private function getVowelRatio(string $text): float
  {
    $letters = preg_replace('/[^a-z]/i', '', mb_strtolower($text));
    $len     = strlen($letters);

    if ($len === 0) return 0.0;

    $vowels = preg_replace('/[^aeiou]/i', '', $letters);
    return round(strlen($vowels) / $len, 4);
  }

  private function isArabicQuery(string $text): bool
  {
    $arabic = preg_match_all('/[\x{0600}-\x{06FF}]/u', $text);
    $total  = mb_strlen(preg_replace('/\s+/', '', $text), 'UTF-8');
    return $total > 0 && ($arabic / $total) > 0.3;
  }
}
