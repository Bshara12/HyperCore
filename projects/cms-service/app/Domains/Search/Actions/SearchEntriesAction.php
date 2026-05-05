<?php

namespace App\Domains\Search\Actions;

use App\Domains\Search\DTOs\LogSearchDTO;
use App\Domains\Search\DTOs\SearchQueryDTO;
use App\Domains\Search\DTOs\SearchResultDTO;
use App\Domains\Search\DTOs\SearchResultItemDTO;
use App\Domains\Search\DTOs\UserPreferenceDTO;
use App\Domains\Search\Repositories\Interfaces\SearchRepositoryInterface;
use App\Domains\Search\Services\AIQueryEnhancer;
use App\Domains\Search\Services\AIQueryInterpreter;
use App\Domains\Search\Services\KeyboardLayoutFixer;
use App\Domains\Search\Support\ArabicQueryNormalizer;
use App\Domains\Search\Support\KeywordProcessor;
use App\Domains\Search\Support\ProcessedKeyword;
use App\Domains\Search\Support\UserPreferenceAnalyzer;
use App\Jobs\IncrementViewCountJob;
use Illuminate\Support\Facades\Log;

class SearchEntriesAction
{
  public function __construct(
    private SearchRepositoryInterface $repository,
    private KeywordProcessor          $processor,
    private UserPreferenceAnalyzer    $preferenceAnalyzer,
    private LogSearchAction           $logSearchAction,
    private AIQueryEnhancer           $aiEnhancer,
    private KeyboardLayoutFixer       $keyboardFixer,
    private ArabicQueryNormalizer     $arabicNormalizer,
    private AIQueryInterpreter $aiInterpreter,
  ) {}

  // ─────────────────────────────────────────────────────────────────

  // public function execute(SearchQueryDTO $dto): SearchResultDTO
  // {
  //     Log::debug('SearchEntriesAction: start', [
  //         'keyword'    => $dto->keyword,
  //         'project_id' => $dto->projectId,
  //         'language'   => $dto->language,
  //     ]);

  //     // ─── 1. تطبيع العربي أولاً ───────────────────────────────────
  //     [$effectiveKeyword, $normalizeInfo] = $this->normalizeQuery(
  //         $dto->keyword, $dto->language
  //     );

  //     Log::debug('SearchEntriesAction: after normalization', [
  //         'original'          => $dto->keyword,
  //         'normalized'        => $effectiveKeyword,
  //         'is_natural_lang'   => $normalizeInfo['isNaturalLanguage'] ?? false,
  //         'exclude_terms'     => $normalizeInfo['excludeTerms'] ?? [],
  //     ]);

  //     // ─── 2. معالجة الـ keyword ────────────────────────────────────
  //     $processed = $this->processor->processWithExpansion(
  //         $effectiveKeyword,
  //         $dto->projectId,
  //         $dto->language
  //     );

  //     // ─── 3. تمرير معلومات الاستبعاد للـ processed ────────────────
  //     $excludeTerms = $normalizeInfo['excludeTerms'] ?? [];

  //     $preference = $this->preferenceAnalyzer->analyze(
  //         projectId: $dto->projectId,
  //         userId:    $dto->userId,
  //         sessionId: $dto->sessionId,
  //     );

  //     // ─── 4. بناء الـ DTO الفعّال (بالكلمة المُطبَّعة) ────────────
  //     $effectiveDto = $this->buildEffectiveDto($dto, $effectiveKeyword);

  //     // ─── 5. البحث الأولي ─────────────────────────────────────────
  //     $result = $this->repository->searchWithExclusions(
  //         $effectiveDto,
  //         $processed,
  //         $preference,
  //         $excludeTerms
  //     );

  //     Log::debug('SearchEntriesAction: initial search', [
  //         'keyword' => $effectiveKeyword,
  //         'total'   => $result['total'],
  //     ]);

  //     $keyboardFixed = false;
  //     $keyboardQuery = null;
  //     $aiEnhanced    = false;
  //     $aiQuery       = null;

  //     // ─── 6. Keyboard Fix (إذا لا نتائج) ──────────────────────────
  //     $threshold = (int) config('search.ai_trigger_threshold', 0);

  //     if ($result['total'] <= $threshold) {
  //         $kbResult = $this->tryKeyboardFix($dto, $effectiveDto, $preference, $result);

  //         if ($kbResult !== null) {
  //             $result        = $kbResult['result'];
  //             $processed     = $kbResult['processed'];
  //             $keyboardFixed = true;
  //             $keyboardQuery = $kbResult['fixedQuery'];

  //             Log::info('SearchEntriesAction: keyboard fix succeeded', [
  //                 'fixed_query' => $keyboardQuery,
  //                 'total'       => $result['total'],
  //             ]);
  //         }
  //     }

  //     // ─── 7. AI Fallback (إذا لا نتائج بعد keyboard fix) ──────────
  //     $aiEnabled = (bool) config('search.ai_enabled', false);

  //     if ($result['total'] <= $threshold && $aiEnabled) {
  //         Log::info('SearchEntriesAction: triggering AI fallback', [
  //             'keyword'        => $dto->keyword,
  //             'results_before' => $result['total'],
  //             'ai_enabled'     => $aiEnabled,
  //         ]);

  //         $aiResult = $this->tryAIFallback(
  //             $dto, $effectiveDto, $preference, $result, $excludeTerms
  //         );

  //         if ($aiResult !== null) {
  //             $result     = $aiResult['result'];
  //             $processed  = $aiResult['processed'];
  //             $aiEnhanced = true;
  //             $aiQuery    = $aiResult['aiQuery'];

  //             Log::info('SearchEntriesAction: AI fallback succeeded', [
  //                 'ai_query' => $aiQuery,
  //                 'total'    => $result['total'],
  //             ]);
  //         }
  //     } elseif ($result['total'] <= $threshold && !$aiEnabled) {
  //         Log::debug('SearchEntriesAction: AI disabled, skipping', [
  //             'AI_SEARCH_ENABLED' => config('search.ai_enabled'),
  //         ]);
  //     }

  //     // ─── 8. بناء الـ response ─────────────────────────────────────
  //     $total = $result['total'];
  //     $rows  = $result['items'];

  //     $items = array_map(
  //         fn($row) => $this->mapToDTO($row, $processed),
  //         $rows
  //     );

  //     $this->logSearch($dto, $processed, $preference, $total);
  //     $this->dispatchViewTracking($rows, $dto->language);

  //     return new SearchResultDTO(
  //         keyword:       $dto->keyword,
  //         total:         $total,
  //         page:          $dto->page,
  //         perPage:       $dto->perPage,
  //         lastPage:      $total > 0 ? (int) ceil($total / $dto->perPage) : 1,
  //         items:         $items,
  //         aiEnhanced:    $aiEnhanced,
  //         aiQuery:       $aiQuery,
  //         keyboardFixed: $keyboardFixed,
  //         keyboardQuery: $keyboardQuery,
  //     );
  // }


  public function execute(SearchQueryDTO $dto): SearchResultDTO
  {
    Log::debug('SearchEntriesAction: start', [
      'keyword'    => $dto->keyword,
      'project_id' => $dto->projectId,
      'language'   => $dto->language,
    ]);

    // ─── 1. تطبيع العربي ─────────────────────────────────────────
    [$effectiveKeyword, $normalizeInfo] = $this->normalizeQuery(
      $dto->keyword,
      $dto->language
    );

    // ─── 2. كشف Gibberish قبل المعالجة ───────────────────────────
    $isGibberish = $this->isGibberish($effectiveKeyword);

    Log::debug('SearchEntriesAction: after normalization', [
      'original'        => $dto->keyword,
      'normalized'      => $effectiveKeyword,
      'is_gibberish'    => $isGibberish,
      'exclude_terms'   => $normalizeInfo['excludeTerms'] ?? [],
    ]);

    // ─── 3. معالجة الكلمات ───────────────────────────────────────
    $processed    = $this->processor->processWithExpansion(
      $effectiveKeyword,
      $dto->projectId,
      $dto->language
    );
    $excludeTerms = $normalizeInfo['excludeTerms'] ?? [];
    $preference   = $this->preferenceAnalyzer->analyze(
      $dto->projectId,
      $dto->userId,
      $dto->sessionId
    );
    $effectiveDto = $this->buildEffectiveDto($dto, $effectiveKeyword);

    // ─── 4. البحث الأولي ─────────────────────────────────────────
    $result = $this->repository->searchWithExclusions(
      $effectiveDto,
      $processed,
      $preference,
      $excludeTerms
    );

    Log::debug('SearchEntriesAction: initial search', [
      'keyword'      => $effectiveKeyword,
      'total'        => $result['total'],
      'clean_words'  => $processed->cleanWords,
    ]);

    $keyboardFixed = false;
    $keyboardQuery = null;
    $aiEnhanced    = false;
    $aiQuery       = null;

    // $threshold = (int) config('search.ai_trigger_threshold', 0);
    // $aiEnabled = (bool) config('search.ai_enabled', false);

    // // ─── 5. هل نحتاج fallback؟ ───────────────────────────────────
    // $needsFallback = $result['total'] <= $threshold
    //   || $isGibberish
    //   || empty($processed->cleanWords);

    // if ($needsFallback) {

    //   // ─── 5A. Keyboard Fix (بشروط مُخففة) ─────────────────────
    //   $kbResult = $this->tryKeyboardFix(
    //     $dto,
    //     $preference,
    //     $result,
    //     $isGibberish
    //   );

    //   if ($kbResult !== null) {
    //     $result        = $kbResult['result'];
    //     $processed     = $kbResult['processed'];
    //     $keyboardFixed = true;
    //     $keyboardQuery = $kbResult['fixedQuery'];

    //     Log::info('SearchEntriesAction: keyboard fix succeeded', [
    //       'fixed_query' => $keyboardQuery,
    //       'total'       => $result['total'],
    //     ]);
    //   }

    //   // ─── 5B. AI Fallback ──────────────────────────────────────
    //   $stillEmpty = $result['total'] <= $threshold;

    //   if (($stillEmpty || $isGibberish) && $aiEnabled) {
    //     Log::info('SearchEntriesAction: triggering AI fallback', [
    //       'keyword'      => $dto->keyword,
    //       'total_before' => $result['total'],
    //       'is_gibberish' => $isGibberish,
    //     ]);

    //     $aiResult = $this->tryAIFallback(
    //       $dto,
    //       $preference,
    //       $result,
    //       $excludeTerms
    //     );

    //     if ($aiResult !== null) {
    //       $result     = $aiResult['result'];
    //       $processed  = $aiResult['processed'];
    //       $aiEnhanced = true;
    //       $aiQuery    = $aiResult['aiQuery'];

    //       Log::info('SearchEntriesAction: AI fallback succeeded', [
    //         'ai_query' => $aiQuery,
    //         'total'    => $result['total'],
    //       ]);
    //     }
    //   } elseif ($stillEmpty && !$aiEnabled) {
    //     Log::debug('SearchEntriesAction: AI skipped', [
    //       'reason'           => 'AI_SEARCH_ENABLED=false',
    //       'ai_trigger_check' => $stillEmpty,
    //     ]);
    // }


    $threshold = (int) config('search.ai_trigger_threshold', 0);
    $aiEnabled = (bool) config('search.ai_enabled', false);

    // ─── كشف الـ negation مبكراً ──────────────────────────────────
    $hasNegation   = !empty($excludeTerms)
      || $this->containsNegationSignal($dto->keyword);
    $isExcludeOnly = $hasNegation && empty($processed->cleanWords);

    // ─── شروط الـ fallback المُحسَّنة ────────────────────────────
    /*
 * قبل:  needsFallback = total <= 0 || isGibberish
 * بعد:  إضافة الـ negation والـ typo probability
 *
 * الحالات الجديدة:
 *   - hasNegation: "لا اريد سامسونغ" → AI يُعيد تفسير النية
 *   - isExcludeOnly: include فارغ لكن exclude موجود
 *   - typo: "iphoen", "غيير الايفون"
 */
    $hasTypo       = $this->hasTypoProbability($effectiveKeyword);
    $needsFallback = $result['total'] <= $threshold
      || $isGibberish
      || empty($processed->cleanWords)
      || $hasNegation
      || $hasTypo;

    Log::debug('SearchEntriesAction: fallback decision', [
      'needs_fallback' => $needsFallback,
      'has_negation'   => $hasNegation,
      'has_typo'       => $hasTypo,
      'is_gibberish'   => $isGibberish,
      'is_excl_only'   => $isExcludeOnly,
      'total'          => $result['total'],
    ]);

    if ($needsFallback) {

      // ─── 5A. Keyboard Fix ─────────────────────────────────────
      // لا نُشغِّل keyboard fix إذا كان negation (لا فائدة)
      if (!$hasNegation) {
        $kbResult = $this->tryKeyboardFix(
          $dto,
          $preference,
          $result,
          $isGibberish
        );

        if ($kbResult !== null) {
          $result        = $kbResult['result'];
          $processed     = $kbResult['processed'];
          $keyboardFixed = true;
          $keyboardQuery = $kbResult['fixedQuery'];

          Log::info('SearchEntriesAction: keyboard fix succeeded', [
            'fixed_query' => $keyboardQuery,
            'total'       => $result['total'],
          ]);
        }
      }

      // ─── 5B. AI Fallback ──────────────────────────────────────
      /*
     * AI يعمل إذا:
     *   - لا نتائج بعد keyboard fix
     *   - OR negation detected (حتى لو في نتائج)
     *   - OR gibberish
     *   - OR typo probable
     */
      $shouldTryAI = $aiEnabled && (
        $result['total'] <= $threshold
        || $isGibberish
        || $hasNegation
        || ($hasTypo && $result['total'] <= $threshold)
      );

      if ($shouldTryAI) {
        Log::info('SearchEntriesAction: triggering AI', [
          'keyword'        => $dto->keyword,
          'reason'         => $hasNegation ? 'negation' : ($isGibberish ? 'gibberish' : 'no_results'),
          'total_before'   => $result['total'],
        ]);

        $aiResult = $this->tryAIFallback(
          $dto,
          $preference,
          $result,
          $excludeTerms,
          $isExcludeOnly
        );

        if ($aiResult !== null) {
          $result     = $aiResult['result'];
          $processed  = $aiResult['processed'];
          $aiEnhanced = true;
          $aiQuery    = $aiResult['aiQuery'];

          Log::info('SearchEntriesAction: AI succeeded', [
            'ai_query' => $aiQuery,
            'total'    => $result['total'],
          ]);
        }
      } elseif (!$aiEnabled) {
        Log::debug('SearchEntriesAction: AI skipped (disabled)');
      }
    }

    // ─── 6. بناء الـ response ─────────────────────────────────────
    $total = $result['total'];
    $rows  = $result['items'];

    $items = array_map(fn($row) => $this->mapToDTO($row, $processed), $rows);

    $this->logSearch($dto, $processed, $preference, $total);
    $this->dispatchViewTracking($rows, $dto->language);

    return new SearchResultDTO(
      keyword: $dto->keyword,
      total: $total,
      page: $dto->page,
      perPage: $dto->perPage,
      lastPage: $total > 0 ? (int) ceil($total / $dto->perPage) : 1,
      items: $items,
      aiEnhanced: $aiEnhanced,
      aiQuery: $aiQuery,
      keyboardFixed: $keyboardFixed,
      keyboardQuery: $keyboardQuery,
    );
  }





  /**
   * كشف النص العشوائي/الغير مفهوم
   *
   * المؤشرات:
   *   1. نسبة حروف العلة منخفضة جداً (< 15%)
   *   2. لا يوجد كلمة طولها >= 3 حروف مفهومة
   *   3. تسلسل حروف غير طبيعي
   */
  private function isGibberish(string $text): bool
  {
    $text = mb_strtolower(trim($text), 'UTF-8');

    if (empty($text) || mb_strlen($text) < 4) {
      return false;
    }

    // إذا النص عربي → ليس gibberish للـ keyboard fixer
    if ($this->isArabicQuery($text)) {
      return false;
    }

    // إذا النص إنجليزي بحت
    $letters = preg_replace('/[^a-z]/i', '', $text);
    $len = strlen($letters);

    if ($len < 3) {
      return false;
    }

    // ─── Check 1: نسبة حروف العلة ───────────────────────────────
    $vowels      = preg_replace('/[^aeiou]/i', '', $letters);
    $vowelRatio  = strlen($vowels) / $len;

    // الكلمات الإنجليزية الطبيعية: 25-60% vowels
    // Gibberish عادة: < 10% أو > 70%
    if ($vowelRatio < 0.10) {
      Log::debug('Gibberish detected: low vowel ratio', [
        'text' => $text,
        'ratio' => $vowelRatio
      ]);
      return true;
    }

    // ─── Check 2: تسلسل حروف غير طبيعي ─────────────────────────
    // 4+ حروف ساكنة متتالية = gibberish
    if (preg_match('/[^aeiou\s]{5,}/i', $letters)) {
      Log::debug('Gibberish detected: consonant cluster', ['text' => $text]);
      return true;
    }

    // ─── Check 3: كلمات معقولة ───────────────────────────────────
    // إذا كل الكلمات أقل من 3 حروف → مشبوه
    $words = preg_split('/\s+/', trim($text));
    $longWords = array_filter($words, fn($w) => strlen(preg_replace('/[^a-z]/i', '', $w)) >= 3);

    if (count($words) > 2 && empty($longWords)) {
      return true;
    }

    return false;
  }



    // ─────────────────────────────────────────────────────────────────
    // Query Normalization
    // ─────────────────────────────────────────────────────────────────

  /**
   * تطبيع الـ query حسب اللغة
   *
   * @return array{0: string, 1: array}  [effectiveKeyword, normalizeInfo]
   */
  // private function normalizeQuery(string $keyword, string $language): array
  // {
  //   $isArabic = $this->isArabicQuery($keyword);

  //   if ($isArabic || $language === 'ar') {
  //     $info = $this->arabicNormalizer->normalize($keyword);

  //     $effectiveKeyword = !empty($info['normalized'])
  //       ? $info['normalized']
  //       : $keyword;

  //     return [$effectiveKeyword, $info];
  //   }

  //   return [$keyword, [
  //     'isNaturalLanguage' => false,
  //     'excludeTerms'      => [],
  //     'normalized'        => $keyword,
  //   ]];
  // }
  private function normalizeQuery(string $keyword, string $language): array
  {
    $isArabic = $this->isArabicQuery($keyword);

    if ($isArabic || $language === 'ar') {
      // ─── Pre-clean قبل الـ normalizer ──────────────────────────
      $preCleaned = $this->normalizeForAI($keyword);

      $info = $this->arabicNormalizer->normalize($preCleaned);

      $effectiveKeyword = !empty($info['normalized'])
        ? $info['normalized']
        : $preCleaned;

      return [$effectiveKeyword, $info];
    }

    // إنجليزي: pre-clean فقط
    $preCleaned = $this->normalizeForAI($keyword);

    return [$preCleaned, [
      'isNaturalLanguage' => false,
      'excludeTerms'      => [],
      'normalized'        => $preCleaned,
    ]];
  }

  private function isArabicQuery(string $text): bool
  {
    $arabicChars = preg_match_all('/[\x{0600}-\x{06FF}]/u', $text);
    $totalChars  = mb_strlen(preg_replace('/\s+/', '', $text), 'UTF-8');

    return $totalChars > 0 && ($arabicChars / $totalChars) > 0.3;
  }

  // ─────────────────────────────────────────────────────────────────
  // Keyboard Fix
  // ─────────────────────────────────────────────────────────────────

  // private function tryKeyboardFix(
  //   SearchQueryDTO    $dto,
  //   SearchQueryDTO    $effectiveDto,
  //   UserPreferenceDTO $preference,
  //   array             $originalResult
  // ): ?array {

  //   try {
  //     $fixResult = $this->keyboardFixer->fix($dto->keyword);
  //   } catch (\Throwable $e) {
  //     Log::warning('SearchEntriesAction: keyboardFixer failed', [
  //       'error' => $e->getMessage(),
  //     ]);
  //     return null;
  //   }

  //   if ($fixResult['fixed'] === null || $fixResult['confidence'] < 0.4) {
  //     return null;
  //   }

  //   $fixedQuery     = $fixResult['fixed'];
  //   $fixedProcessed = $this->processor->processWithExpansion(
  //     $fixedQuery,
  //     $dto->projectId,
  //     $dto->language
  //   );

  //   $fixedDto    = $this->buildEffectiveDto($dto, $fixedQuery);
  //   $fixedResult = $this->repository->searchWithExclusions(
  //     $fixedDto,
  //     $fixedProcessed,
  //     $preference,
  //     []
  //   );

  //   if ($fixedResult['total'] > $originalResult['total']) {
  //     return [
  //       'result'     => $fixedResult,
  //       'processed'  => $fixedProcessed,
  //       'fixedQuery' => $fixedQuery,
  //     ];
  //   }

  //   return null;
  // }




  private function tryKeyboardFix(
    SearchQueryDTO    $dto,
    UserPreferenceDTO $preference,
    array             $originalResult,
    bool              $isGibberish = false
  ): ?array {

    try {
      $fixResult = $this->keyboardFixer->fix($dto->keyword);
    } catch (\Throwable $e) {
      Log::warning('SearchEntriesAction: keyboardFixer failed', [
        'error' => $e->getMessage(),
      ]);
      return null;
    }

    // ─── Gibberish: نخفف الـ threshold ───────────────────────────
    $minConfidence = $isGibberish ? 0.25 : 0.4;

    if ($fixResult['fixed'] === null) {
      Log::debug('SearchEntriesAction: keyboard fix skipped (no fix)', [
        'keyword' => $dto->keyword,
      ]);
      return null;
    }

    if ($fixResult['confidence'] < $minConfidence) {
      Log::debug('SearchEntriesAction: keyboard fix rejected (low confidence)', [
        'keyword'    => $dto->keyword,
        'confidence' => $fixResult['confidence'],
        'threshold'  => $minConfidence,
      ]);
      return null;
    }

    $fixedQuery     = $fixResult['fixed'];
    $fixedProcessed = $this->processor->processWithExpansion(
      $fixedQuery,
      $dto->projectId,
      $dto->language
    );

    $fixedDto    = $this->buildEffectiveDto($dto, $fixedQuery);
    $fixedResult = $this->repository->searchWithExclusions(
      $fixedDto,
      $fixedProcessed,
      $preference,
      []
    );

    Log::debug('SearchEntriesAction: keyboard fix result', [
      'original_query'  => $dto->keyword,
      'fixed_query'     => $fixedQuery,
      'confidence'      => $fixResult['confidence'],
      'direction'       => $fixResult['direction'],
      'total_before'    => $originalResult['total'],
      'total_after'     => $fixedResult['total'],
    ]);

    if ($fixedResult['total'] > $originalResult['total']) {
      return [
        'result'     => $fixedResult,
        'processed'  => $fixedProcessed,
        'fixedQuery' => $fixedQuery,
      ];
    }

    return null;
  }

  // ─────────────────────────────────────────────────────────────────
  // AI Fallback - محسَّن
  // ─────────────────────────────────────────────────────────────────

  // private function tryAIFallback(
  //   SearchQueryDTO    $dto,
  //   UserPreferenceDTO $preference,
  //   array             $originalResult,
  //   array             $excludeTerms
  // ): ?array {

  //   try {
  //     $enhancement = $this->aiEnhancer->enhance($dto->keyword, $dto->language);
  //   } catch (\Throwable $e) {
  //     Log::error('SearchEntriesAction: AIEnhancer threw exception', [
  //       'error' => $e->getMessage(),
  //       'query' => $dto->keyword,
  //     ]);
  //     return null;
  //   }

  //   Log::debug('SearchEntriesAction: AI enhancement result', [
  //     'corrected'  => $enhancement['correctedQuery'],
  //     'expanded'   => $enhancement['expandedKeywords'],
  //     'confidence' => $enhancement['confidence'],
  //     'source'     => $enhancement['source'] ?? 'unknown',
  //   ]);

  //   $hasCorrection = mb_strtolower($enhancement['correctedQuery'], 'UTF-8')
  //     !== mb_strtolower($dto->keyword, 'UTF-8');
  //   $hasExpansion  = !empty($enhancement['expandedKeywords']);

  //   /*
  //    * الفرق عن الكود القديم:
  //    * حتى لو AI لم يُغير الـ query، إذا confidence > 0 → نجرب الـ expandedKeywords
  //    * وإذا كان الـ query فارغاً من tokens → نجرب الـ correctedQuery على أي حال
  //    */
  //   if (!$hasCorrection && !$hasExpansion) {
  //     Log::info('SearchEntriesAction: AI provided no improvement', [
  //       'keyword' => $dto->keyword,
  //     ]);
  //     return null;
  //   }

  //   $aiKeyword = $this->buildAIQuery(
  //     $enhancement['correctedQuery'],
  //     $enhancement['expandedKeywords']
  //   );

  //   if (empty(trim($aiKeyword))) {
  //     Log::warning('SearchEntriesAction: AI produced empty query');
  //     return null;
  //   }

  //   $aiProcessed = $this->processor->processWithExpansion(
  //     $aiKeyword,
  //     $dto->projectId,
  //     $dto->language
  //   );

  //   $aiDto    = $this->buildEffectiveDto($dto, $aiKeyword);
  //   $aiResult = $this->repository->searchWithExclusions(
  //     $aiDto,
  //     $aiProcessed,
  //     $preference,
  //     $excludeTerms
  //   );

  //   Log::info('SearchEntriesAction: AI fallback search done', [
  //     'ai_keyword'   => $aiKeyword,
  //     'total_before' => $originalResult['total'],
  //     'total_after'  => $aiResult['total'],
  //   ]);

  //   if ($aiResult['total'] > $originalResult['total']) {
  //     return [
  //       'result'    => $aiResult,
  //       'processed' => $aiProcessed,
  //       'aiQuery'   => $aiKeyword,
  //     ];
  //   }

  //   return null;
  // }




  // في SearchEntriesAction - استبدل tryAIFallback بهذه النسخة

  // private function tryAIFallback(
  //   SearchQueryDTO    $dto,
  //   UserPreferenceDTO $preference,
  //   array             $originalResult,
  //   array             $excludeTerms
  // ): ?array {

  //   try {
  //     // ← استخدام AIQueryInterpreter بدل AIQueryEnhancer
  //     $interpretation = $this->aiInterpreter->interpret(
  //       $dto->keyword,
  //       $dto->language
  //     );
  //   } catch (\Throwable $e) {
  //     Log::error('SearchEntriesAction: AIInterpreter failed', [
  //       'error' => $e->getMessage(),
  //       'query' => $dto->keyword,
  //     ]);
  //     return null;
  //   }

  //   Log::debug('SearchEntriesAction: AI interpretation', [
  //     'include'    => $interpretation['include'],
  //     'exclude'    => $interpretation['exclude'],
  //     'intent'     => $interpretation['intent'],
  //     'confidence' => $interpretation['confidence'],
  //     'source'     => $interpretation['source'],
  //   ]);

  //   // إذا confidence منخفض جداً → لا فائدة
  //   if ($interpretation['confidence'] < 0.2 && empty($interpretation['include'])) {
  //     Log::info('SearchEntriesAction: AI confidence too low', [
  //       'confidence' => $interpretation['confidence'],
  //     ]);
  //     return null;
  //   }

  //   // ─── بناء الـ query من include tokens ────────────────────────────
  //   $includeTokens = $interpretation['include'];
  //   $aiExclude     = array_merge($excludeTerms, $interpretation['exclude']);

  //   if (empty($includeTokens)) {
  //     // fallback للـ corrected query إذا include فارغ
  //     if (!empty(trim($interpretation['corrected']))) {
  //       $includeTokens = array_filter(
  //         explode(' ', mb_strtolower(trim($interpretation['corrected']))),
  //         fn($w) => mb_strlen($w) >= 2
  //       );
  //     }

  //     if (empty($includeTokens)) {
  //       Log::info('SearchEntriesAction: AI produced no usable tokens');
  //       return null;
  //     }
  //   }

  //   // ─── إضافة expanded للـ tokens ───────────────────────────────────
  //   $allTokens = array_unique(array_merge(
  //     $includeTokens,
  //     array_slice($interpretation['expanded'], 0, 2) // أول 2 expanded فقط
  //   ));

  //   $aiKeyword = implode(' ', array_slice($allTokens, 0, 8));

  //   Log::info('SearchEntriesAction: AI search tokens', [
  //     'ai_keyword' => $aiKeyword,
  //     'ai_exclude' => $aiExclude,
  //   ]);

  //   $aiProcessed = $this->processor->processWithExpansion(
  //     $aiKeyword,
  //     $dto->projectId,
  //     $dto->language
  //   );

  //   $aiDto = $this->buildEffectiveDto($dto, $aiKeyword);

  //   $aiResult = $this->repository->searchWithExclusions(
  //     $aiDto,
  //     $aiProcessed,
  //     $preference,
  //     $aiExclude
  //   );

  //   Log::info('SearchEntriesAction: AI search result', [
  //     'ai_keyword'   => $aiKeyword,
  //     'total_before' => $originalResult['total'],
  //     'total_after'  => $aiResult['total'],
  //   ]);

  //   if ($aiResult['total'] > $originalResult['total']) {
  //     return [
  //       'result'    => $aiResult,
  //       'processed' => $aiProcessed,
  //       'aiQuery'   => $aiKeyword,
  //     ];
  //   }

  //   return null;
  // }



  /**
   * Pre-cleaning قبل AI وقبل الـ normalizer
   *
   * يُعالج:
   *   "غيير"    → "غير"     (حروف مكررة عربي)
   *   "iphoooone" → "iphone"  (حروف مكررة إنجليزي)
   *   "سامسونغ"  → "سامسونج" (تطبيع أحرف متشابهة)
   */
  private function normalizeForAI(string $query): string
  {
    // ─── 1. إزالة حروف إنجليزية مكررة (> 2 متتالية) ─────────────
    // "iphoooone" → "iphone" عبر تقليص لـ 2 max
    $query = preg_replace('/([a-zA-Z])\1{2,}/', '$1$1', $query);

    // ─── 2. إزالة حروف عربية مكررة (> 1 متتالي) ─────────────────
    // "غيير" → "غير"، "سامسووونج" → "سامسونج"
    $query = preg_replace('/(\p{Arabic})\1+/u', '$1', $query);

    // ─── 3. تطبيع أحرف عربية شائعة الخطأ ─────────────────────────
    // "سامسونغ" → "سامسونج" (غ vs ج في نهاية الكلمة الأجنبية)
    $query = str_replace(['ة ', 'ة$'], ['ه ', 'ه'], $query);

    // ─── 4. إزالة مسافات زائدة ────────────────────────────────────
    return trim(preg_replace('/\s+/', ' ', $query));
  }

  // ─────────────────────────────────────────────────────────────────
  // AI Fallback
  // ─────────────────────────────────────────────────────────────────

  // private function tryAIFallback(
  //   SearchQueryDTO    $dto,
  //   SearchQueryDTO    $effectiveDto,
  //   UserPreferenceDTO $preference,
  //   array             $originalResult,
  //   array             $excludeTerms
  // ): ?array {

  //   try {
  //     $enhancement = $this->aiEnhancer->enhance($dto->keyword, $dto->language);
  //   } catch (\Throwable $e) {
  //     Log::error('SearchEntriesAction: AIEnhancer threw exception', [
  //       'error' => $e->getMessage(),
  //       'query' => $dto->keyword,
  //     ]);
  //     return null;
  //   }

  //   Log::debug('SearchEntriesAction: AI enhancement result', [
  //     'corrected'   => $enhancement['correctedQuery'],
  //     'expanded'    => $enhancement['expandedKeywords'],
  //     'confidence'  => $enhancement['confidence'],
  //     'source'      => $enhancement['source'] ?? 'unknown',
  //   ]);

  //   $hasCorrection = mb_strtolower($enhancement['correctedQuery'])
  //     !== mb_strtolower($dto->keyword);
  //   $hasExpansion  = !empty($enhancement['expandedKeywords']);

  //   if (!$hasCorrection && !$hasExpansion) {
  //     Log::info('SearchEntriesAction: AI provided no improvement');
  //     return null;
  //   }

  //   $aiKeyword   = $this->buildAIQuery(
  //     $enhancement['correctedQuery'],
  //     $enhancement['expandedKeywords']
  //   );

  //   $aiProcessed = $this->processor->processWithExpansion(
  //     $aiKeyword,
  //     $dto->projectId,
  //     $dto->language
  //   );

  //   $aiDto    = $this->buildEffectiveDto($dto, $aiKeyword);
  //   $aiResult = $this->repository->searchWithExclusions(
  //     $aiDto,
  //     $aiProcessed,
  //     $preference,
  //     $excludeTerms
  //   );

  //   if ($aiResult['total'] > $originalResult['total']) {
  //     return [
  //       'result'    => $aiResult,
  //       'processed' => $aiProcessed,
  //       'aiQuery'   => $aiKeyword,
  //     ];
  //   }

  //   return null;
  // }

  // ─────────────────────────────────────────────────────────────────
  // Helpers
  // ─────────────────────────────────────────────────────────────────

  private function buildEffectiveDto(SearchQueryDTO $dto, string $keyword): SearchQueryDTO
  {
    return new SearchQueryDTO(
      keyword: $keyword,
      projectId: $dto->projectId,
      language: $dto->language,
      page: $dto->page,
      perPage: $dto->perPage,
      dataTypeSlug: $dto->dataTypeSlug,
      userId: $dto->userId,
      sessionId: $dto->sessionId,
    );
  }

  private function buildAIQuery(string $correctedQuery, array $expandedKeywords): string
  {
    $allWords = [$correctedQuery, ...$expandedKeywords];
    $words    = [];

    foreach ($allWords as $phrase) {
      foreach (explode(' ', mb_strtolower(trim($phrase))) as $word) {
        if (mb_strlen($word) >= 2 && !in_array($word, $words, true)) {
          $words[] = $word;
        }
      }
    }

    return implode(' ', array_slice($words, 0, 10));
  }

  private function dispatchViewTracking(array $rows, string $language): void
  {
    if (empty($rows)) return;

    $entryIds = array_values(array_unique(
      array_map(fn($row) => (int) $row->entry_id, $rows)
    ));

    IncrementViewCountJob::dispatch($entryIds, $language)
      ->onQueue('search-tracking');
  }

  private function logSearch(
    SearchQueryDTO    $dto,
    ProcessedKeyword  $processed,
    UserPreferenceDTO $preference,
    int               $total
  ): void {
    try {
      $this->logSearchAction->execute(new LogSearchDTO(
        projectId: $dto->projectId,
        keyword: $dto->keyword,
        language: $dto->language,
        resultsCount: $total,
        detectedIntent: $processed->intent['intent'],
        intentConfidence: $processed->intent['confidence'],
        userId: $dto->userId,
        sessionId: $dto->sessionId,
      ));
    } catch (\Throwable $e) {
      Log::warning('SearchEntriesAction: logSearch failed', [
        'error'   => $e->getMessage(),
        'keyword' => $dto->keyword,
      ]);
    }
  }

  private function mapToDTO(object $row, ProcessedKeyword $processed): SearchResultItemDTO
  {
    $snippet = $this->generateSnippet($row->content ?? '', $processed->cleanWords);

    return new SearchResultItemDTO(
      entryId: (int) $row->entry_id,
      dataTypeId: (int) $row->data_type_id,
      projectId: (int) $row->project_id,
      language: $row->language,
      title: $this->highlightText($row->title ?? '', $processed->cleanWords),
      snippet: $this->highlightText($snippet, $processed->cleanWords),
      status: $row->status,
      score: round((float) ($row->final_score ?? $row->weighted_score ?? 0), 4),
      publishedAt: $row->published_at,
    );
  }

  private function generateSnippet(string $content, array $words, int $before = 60, int $after = 100): string
  {
    if (empty($content)) return '';
    $plain = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if (empty($plain)) return '';
    $pos = $this->findFirstMatch($plain, $words);
    if ($pos === null) return mb_strlen($plain) <= 160 ? $plain : mb_substr($plain, 0, 160, 'UTF-8') . '...';
    $start = max(0, $pos - $before);
    $end   = min(mb_strlen($plain, 'UTF-8'), $pos + $after);
    return ($start > 0 ? '...' : '') . trim(mb_substr($plain, $start, $end - $start, 'UTF-8')) . ($end < mb_strlen($plain, 'UTF-8') ? '...' : '');
  }

  private function findFirstMatch(string $text, array $words): ?int
  {
    $earliest = null;
    foreach ($words as $word) {
      $pos = mb_stripos($text, $word, 0, 'UTF-8');
      if ($pos !== false && ($earliest === null || $pos < $earliest)) $earliest = $pos;
    }
    return $earliest;
  }

  private function highlightText(string $text, array $words): string
  {
    if (empty($text) || empty($words)) return $text;
    usort($words, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
    foreach ($words as $word) {
      if (mb_strlen($word) < 2) continue;
      $escaped = preg_quote($word, '/');
      $result  = preg_replace('/(?<!\*\*)(' . $escaped . ')(?!\*\*)/iu', '**$1**', $text);
      $text    = $result ?? $text;
    }
    return $text;
  }
  // ─────────────────────────────────────────────────────────────────
// Smart Detection Helpers
// ─────────────────────────────────────────────────────────────────

  /**
   * كشف إشارات النفي في الـ query الأصلي
   * قبل أي معالجة - للكشف المبكر
   */
  private function containsNegationSignal(string $text): bool
  {
    $text    = mb_strtolower($text, 'UTF-8');
    $signals = [
      // عربي - single word
      'لا',
      'ما',
      'غير',
      'بدون',
      'ماعدا',
      'إلا',
      'سوى',
      'مش',
      'مو',
      'مب',
      'مبغى',
      // عربي - patterns (نبحث عنها كـ substring)
      'ما بدي',
      'ما اريد',
      'لا اريد',
      'مش عايز',
      'مبغاش',
      'مابغاش',
      'بدون ما',
      // إنجليزي
      'without',
      'except',
      'not',
      'no ',
      "don't",
      "dont",
      'exclude',
      'minus',
      'avoid',
    ];

    foreach ($signals as $signal) {
      if (str_contains($text, $signal)) {
        return true;
      }
    }

    return false;
  }

// ─────────────────────────────────────────────────────────────────

  /**
   * تقدير احتمالية وجود typo في الـ query
   *
   * المنطق:
   *   - كلمة إنجليزية بدون حروف علة كافية → typo محتمل
   *   - تسلسل حروف نادر → typo
   *   - نسبة الأحرف المتكررة عالية → typo
   */
  private function hasTypoProbability(string $text): bool
  {
    $text = mb_strtolower(trim($text), 'UTF-8');

    if (empty($text) || mb_strlen($text) < 3) {
      return false;
    }

    // عربي → لا نكشف typo هنا (يُعالج في normalizer)
    if ($this->isArabicQuery($text)) {
      return false;
    }

    $words   = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    $typoCount = 0;

    foreach ($words as $word) {
      $letters = preg_replace('/[^a-z]/i', '', $word);
      $len     = strlen($letters);

      if ($len < 3) continue;

      $vowels     = preg_replace('/[^aeiou]/i', '', $letters);
      $vowelRatio = strlen($vowels) / $len;

      // نسبة vowels خارج النطاق الطبيعي (0.15 - 0.65)
      if ($vowelRatio < 0.15 || $vowelRatio > 0.70) {
        $typoCount++;
      }

      // 4+ حروف ساكنة متتالية
      if (preg_match('/[^aeiou]{4,}/i', $letters)) {
        $typoCount++;
      }
    }

    return $typoCount > 0 && ($typoCount / count($words)) > 0.4;
  }

// ─────────────────────────────────────────────────────────────────

  /**
   * tryAIFallback المُصلَّح - يستخدم AIQueryInterpreter
   *
   * التغييرات:
   *   1. يستخدم AIQueryInterpreter بدل AIQueryEnhancer
   *   2. يدعم exclude-only queries
   *   3. pre-cleaning قبل الإرسال للـ AI
   *   4. شرط الـ confidence أكثر مرونة
   */
  private function tryAIFallback(
    SearchQueryDTO    $dto,
    UserPreferenceDTO $preference,
    array             $originalResult,
    array             $excludeTerms,
    bool              $isExcludeOnly = false
  ): ?array {

    // ─── Pre-cleaning قبل إرسال للـ AI ────────────────────────────
    $cleanedQuery = $this->normalizeForAI($dto->keyword);

    try {
      $interpretation = $this->aiInterpreter->interpret(
        $cleanedQuery,
        $dto->language
      );
    } catch (\Throwable $e) {
      Log::error('SearchEntriesAction: AIInterpreter failed', [
        'error'         => $e->getMessage(),
        'query'         => $cleanedQuery,
        'original'      => $dto->keyword,
      ]);
      return null;
    }

    Log::debug('SearchEntriesAction: AI interpretation', [
      'original'   => $dto->keyword,
      'cleaned'    => $cleanedQuery,
      'include'    => $interpretation['include'],
      'exclude'    => $interpretation['exclude'],
      'intent'     => $interpretation['intent'],
      'confidence' => $interpretation['confidence'],
      'source'     => $interpretation['source'],
    ]);

    // ─── شرط الرفض المُخفَّف ──────────────────────────────────────
    /*
     * قبل: confidence < 0.2 → reject
     * بعد: رفض فقط إذا:
     *       confidence < 0.1
     *       AND include فارغ
     *       AND corrected فارغ
     *       AND exclude فارغ
     *
     * السبب: حتى confidence = 0.15 مع exclude موجود → مفيد
     */
    $hasUsableData = !empty($interpretation['include'])
      || !empty($interpretation['exclude'])
      || !empty(trim($interpretation['corrected']));

    if ($interpretation['confidence'] < 0.1 && !$hasUsableData) {
      Log::info('SearchEntriesAction: AI result unusable', [
        'confidence' => $interpretation['confidence'],
        'has_data'   => $hasUsableData,
      ]);
      return null;
    }

    // ─── دمج الـ excludeTerms ─────────────────────────────────────
    $mergedExclude = array_unique(
      array_merge($excludeTerms, $interpretation['exclude'])
    );

    // ─── بناء include tokens ──────────────────────────────────────
    $includeTokens = $interpretation['include'];

    // إذا include فارغ → جرب الـ corrected
    if (empty($includeTokens) && !empty(trim($interpretation['corrected']))) {
      $includeTokens = array_values(array_filter(
        explode(' ', mb_strtolower(trim($interpretation['corrected']))),
        fn($w) => mb_strlen($w) >= 2
      ));
    }

    // ─── حالة exclude-only ───────────────────────────────────────
    /*
     * "لا اريد سامسونغ" → include=[], exclude=["samsung"]
     * الحل: ابحث عن كل شيء ما عدا المُستبعد
     *
     * نستخدم empty string كـ keyword → searchWithExclusions
     * يُرجع كل النتائج ماعدا المُستبعدة
     */
    if (empty($includeTokens) && !empty($mergedExclude)) {
      Log::info('SearchEntriesAction: exclude-only search', [
        'exclude' => $mergedExclude,
      ]);

      $emptyProcessed = $this->processor->processWithExpansion(
        '',
        $dto->projectId,
        $dto->language
      );

      $emptyDto = $this->buildEffectiveDto($dto, '');

      $altResult = $this->repository->searchWithExclusions(
        $emptyDto,
        $emptyProcessed,
        $preference,
        $mergedExclude
      );

      if ($altResult['total'] > 0) {
        return [
          'result'    => $altResult,
          'processed' => $emptyProcessed,
          'aiQuery'   => 'exclude:' . implode(',', $mergedExclude),
        ];
      }

      return null;
    }

    if (empty($includeTokens)) {
      Log::info('SearchEntriesAction: AI produced no tokens');
      return null;
    }

    // ─── إضافة expanded ──────────────────────────────────────────
    $allTokens = array_unique(array_merge(
      $includeTokens,
      array_slice($interpretation['expanded'], 0, 2)
    ));

    $aiKeyword = implode(' ', array_slice($allTokens, 0, 8));

    $aiProcessed = $this->processor->processWithExpansion(
      $aiKeyword,
      $dto->projectId,
      $dto->language
    );

    $aiDto    = $this->buildEffectiveDto($dto, $aiKeyword);
    $aiResult = $this->repository->searchWithExclusions(
      $aiDto,
      $aiProcessed,
      $preference,
      $mergedExclude
    );

    Log::info('SearchEntriesAction: AI search done', [
      'ai_keyword'   => $aiKeyword,
      'ai_exclude'   => $mergedExclude,
      'total_before' => $originalResult['total'],
      'total_after'  => $aiResult['total'],
    ]);

    /*
     * قبول الـ AI result إذا:
     *   - أعطى نتائج أكثر من الأصلي
     *   - OR كان الأصلي يعطي نتائج خاطئة (hasNegation)
     *     فنقبل حتى لو النتائج أقل
     */
    if ($aiResult['total'] > $originalResult['total'] || !empty($mergedExclude)) {
      return [
        'result'    => $aiResult,
        'processed' => $aiProcessed,
        'aiQuery'   => $aiKeyword,
      ];
    }

    return null;
  }
}
