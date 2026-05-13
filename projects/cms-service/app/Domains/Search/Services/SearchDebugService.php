<?php

namespace App\Domains\Search\Services;

use App\Domains\Search\Actions\SearchEntriesAction;
use App\Domains\Search\DTOs\SearchQueryDTO;
use App\Domains\Search\DTOs\UserPreferenceDTO;
use App\Domains\Search\Repositories\Interfaces\SearchRepositoryInterface;
use App\Domains\Search\Support\ArabicQueryNormalizer;
use App\Domains\Search\Support\KeywordProcessor;
use App\Domains\Search\Support\UserPreferenceAnalyzer;

class SearchDebugService
{
    // ─── Arabic stopwords التي يجب حذفها من AI tokens ─────────────────
    private const ARABIC_STOPWORDS = [
        'ما', 'بدي', 'بديش', 'بدون', 'غير', 'شيء', 'اريد', 'أريد',
        'ابحث', 'عن', 'في', 'من', 'إلى', 'على', 'هذا', 'هذه',
        'لا', 'مو', 'مش', 'لي', 'لك', 'انا', 'أنا', 'نفسي',
        'محتاج', 'عايز', 'حابب', 'ابغى', 'أبغى', 'بغيت',
        'يا', 'هلا', 'ممكن', 'لو', 'عندي', 'وين', 'ودي',
        'الي', 'اللي', 'اشتري', 'اشتر',
    ];

    // ─────────────────────────────────────────────────────────────────

    public function __construct(
        private ArabicQueryNormalizer     $arabicNormalizer,
        private KeywordProcessor          $processor,
        private UserPreferenceAnalyzer    $preferenceAnalyzer,
        private SearchRepositoryInterface $repository,
        private KeyboardLayoutFixer       $keyboardFixer,
        private AIQueryInterpreter        $aiInterpreter,
        private SearchEntriesAction       $searchAction,
    ) {}

    // ─────────────────────────────────────────────────────────────────
    // Entry Point
    // ─────────────────────────────────────────────────────────────────

    public function runDebugPipeline(
        string $keyword,
        string $language,
        int    $projectId
    ): array {
        $startTime     = microtime(true);
        $decisionTrace = [];

        // ══════════════════════════════════════════════════════════════
        // STEP 1 — Query Classification
        // ══════════════════════════════════════════════════════════════
        $isArabic    = $this->isArabicQuery($keyword);
        $isMixed     = $this->isMixedQuery($keyword);
        $isGibberish = $this->detectGibberish($keyword);
        $hasNegation = $this->containsNegation($keyword);

        // ══════════════════════════════════════════════════════════════
        // STEP 2 — Normalization
        // ══════════════════════════════════════════════════════════════
        $normalizeInfo = [
            'normalized'        => $keyword,
            'excludeTerms'      => [],
            'isNaturalLanguage' => false,
            'cleanWords'        => [],
        ];

        if ($isArabic || $language === 'ar') {
            $normalizeInfo = $this->arabicNormalizer->normalize($keyword);
        }

        $effectiveKeyword = ! empty($normalizeInfo['normalized'])
            ? $normalizeInfo['normalized']
            : $keyword;

        $excludeTerms      = $normalizeInfo['excludeTerms']      ?? [];
        $isNaturalLanguage = $normalizeInfo['isNaturalLanguage'] ?? false;

        $normalizationData = [
            'detected_arabic'     => $isArabic,
            'is_mixed'            => $isMixed,
            'original'            => $keyword,
            'normalized'          => $effectiveKeyword,
            'exclude_terms'       => $excludeTerms,
            'is_natural_language' => $isNaturalLanguage,
            'has_negation'        => $hasNegation,
        ];

        // ══════════════════════════════════════════════════════════════
        // STEP 3 — Recovery Guard Decision
        //
        // هذا هو القلب الهندسي:
        // نحدد مرة واحدة هل يجب تخطي كل recovery systems
        // ══════════════════════════════════════════════════════════════
        $skipRecovery       = $this->shouldSkipRecovery(
            $hasNegation,
            $excludeTerms,
            $isArabic,
            $isNaturalLanguage
        );
        $skipRecoveryReason = $this->resolveSkipReason(
            $hasNegation,
            $excludeTerms,
            $isArabic,
            $isNaturalLanguage
        );

        if ($skipRecovery) {
            $decisionTrace[] = "🚫 Recovery skipped: {$skipRecoveryReason}";
        }

        $recoveryMeta = [
            'skipped' => $skipRecovery,
            'reason'  => $skipRecovery ? $skipRecoveryReason : null,
        ];

        // ══════════════════════════════════════════════════════════════
        // STEP 4 — Keyword Processing
        // ══════════════════════════════════════════════════════════════
        $processed = $this->processor->processWithExpansion(
            $effectiveKeyword, $projectId, $language
        );

        $processingData = [
            'clean_words'      => $processed->cleanWords,
            'boolean_query'    => $processed->booleanQuery,
            'relaxed_queries'  => $processed->relaxedQueries,
            'intent'           => $processed->intent,
            'had_db_expansion' => $processed->hadDbExpansion,
        ];

        $preAiData = [
            'is_gibberish' => $isGibberish,
            'has_negation' => $hasNegation,
            'vowel_ratio'  => $this->getVowelRatio($effectiveKeyword),
            'skip_recovery'=> $skipRecovery,
        ];

        // ══════════════════════════════════════════════════════════════
        // STEP 5 — Initial Search
        // ══════════════════════════════════════════════════════════════
        $preference = UserPreferenceDTO::noHistory();

        $dto = new SearchQueryDTO(
            keyword:   $effectiveKeyword,
            projectId: $projectId,
            language:  $language,
            page:      1,
            perPage:   5,
        );

        $initialResult = $this->repository->searchWithExclusions(
            $dto, $processed, $preference, $excludeTerms
        );

        $decisionTrace[] = $initialResult['total'] > 0
            ? "✅ Initial search: {$initialResult['total']} results"
            : "❌ Initial search: 0 results";

        $initialSearchData = [
            'total'         => $initialResult['total'],
            'exclude_terms' => $excludeTerms,
            'top_results'   => $this->mapResultRows(
                array_slice($initialResult['items'], 0, 5),
                $processed->cleanWords
            ),
        ];

        // ══════════════════════════════════════════════════════════════
        // STEP 6 — Keyboard Fix Simulation
        // ══════════════════════════════════════════════════════════════
        $keyboardData = $this->simulateKeyboardFix(
            keyword:       $keyword,
            projectId:     $projectId,
            language:      $language,
            preference:    $preference,
            initialTotal:  $initialResult['total'],
            isGibberish:   $isGibberish,
            isArabic:      $isArabic,
            isMixed:       $isMixed,
            skipRecovery:  $skipRecovery,
            skipReason:    $skipRecoveryReason,
            decisionTrace: $decisionTrace,
        );

        // ══════════════════════════════════════════════════════════════
        // STEP 7 — AI Simulation
        // ══════════════════════════════════════════════════════════════
        $threshold     = (int) config('search.ai_trigger_threshold', 0);
        $needsFallback = $initialResult['total'] <= $threshold
            || $isGibberish
            || empty($processed->cleanWords);

        $aiData = $this->simulateAI(
            keyword:       $keyword,
            language:      $language,
            projectId:     $projectId,
            preference:    $preference,
            needsFallback: $needsFallback,
            initialTotal:  $initialResult['total'],
            excludeTerms:  $excludeTerms,
            isGibberish:   $isGibberish,
            skipRecovery:  $skipRecovery,
            skipReason:    $skipRecoveryReason,
            decisionTrace: $decisionTrace,
        );

        // ══════════════════════════════════════════════════════════════
        // STEP 8 — Final Result (من SearchEntriesAction الحقيقي)
        // ══════════════════════════════════════════════════════════════
        $realDto = new SearchQueryDTO(
            keyword:   $keyword,
            projectId: $projectId,
            language:  $language,
            page:      1,
            perPage:   10,
        );

        $realResult = $this->searchAction->execute($realDto);

        $finalSource = match (true) {
            $realResult->keyboardFixed && ! $realResult->aiEnhanced => 'keyboard_or_typo',
            $realResult->aiEnhanced                                  => 'ai',
            default                                                  => 'initial',
        };

        if ($realResult->keyboardFixed) {
            $decisionTrace[] = "✅ Real pipeline: typo/keyboard fixed → '{$realResult->keyboardQuery}'";
        }
        if ($realResult->aiEnhanced) {
            $decisionTrace[] = "✅ Real pipeline: AI enhanced → '{$realResult->aiQuery}'";
        }

        $matchWords = $processed->cleanWords;
        if ($realResult->aiEnhanced && ! empty($realResult->aiQuery)) {
            $matchWords = array_filter(
                explode(' ', $realResult->aiQuery),
                fn($w) => mb_strlen($w) >= 2
            );
        }

        $finalItems = array_map(fn($item) => [
            'entry_id'      => $item->entryId,
            'title'         => $item->title,
            'score'         => $item->score,
            'data_type'     => $item->dataTypeId,
            'matched_terms' => $this->findMatchedTerms($item->title ?? '', $matchWords),
        ], $realResult->items);

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
            'recovery'        => $recoveryMeta,
            'initial_search'  => $initialSearchData,
            'keyboard_fix'    => $keyboardData,
            'ai'              => $aiData,
            'final'           => [
                'total'          => $realResult->total,
                'source'         => $finalSource,
                'keyboard_fixed' => $realResult->keyboardFixed,
                'keyboard_query' => $realResult->keyboardQuery,
                'ai_enhanced'    => $realResult->aiEnhanced,
                'ai_query'       => $realResult->aiQuery,
                'results'        => $finalItems,
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // Recovery Guard — القلب الهندسي
    // ─────────────────────────────────────────────────────────────────

    /**
     * يحدد إذا كان يجب تخطي كل recovery systems.
     *
     * المنطق:
     *   النفي = المستخدم يعرف ما يريد، لا يحتاج تصحيح
     *   excludeTerms = الـ normalizer استخرج قصد واضح
     *   Arabic natural language = قصد طبيعي، ليس خطأ إملائي
     */
    private function shouldSkipRecovery(
        bool  $hasNegation,
        array $excludeTerms,
        bool  $isArabic,
        bool  $isNaturalLanguage
    ): bool {
        // نفي صريح → تخطي
        if ($hasNegation) {
            return true;
        }

        // الـ normalizer استخرج exclude terms → قصد واضح
        if (! empty($excludeTerms)) {
            return true;
        }

        // عربي طبيعي (مع نفي أو استبعاد) → تخطي
        if ($isArabic && $isNaturalLanguage) {
            return true;
        }

        return false;
    }

    /**
     * يُعيد سبب التخطي كـ string واضح للـ debug output
     */
    private function resolveSkipReason(
        bool  $hasNegation,
        array $excludeTerms,
        bool  $isArabic,
        bool  $isNaturalLanguage
    ): string {
        if ($hasNegation) {
            return 'negation_query';
        }
        if (! empty($excludeTerms)) {
            return 'has_exclude_terms';
        }
        if ($isArabic && $isNaturalLanguage) {
            return 'arabic_natural_language';
        }
        return 'none';
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
        bool              $isArabic,
        bool              $isMixed,
        bool              $skipRecovery,
        string            $skipReason,
        array             &$decisionTrace
    ): array {

        // ── Guard #1: recovery مُتخطَّى ──────────────────────────────
        if ($skipRecovery) {
            $decisionTrace[] = "⏭️ Keyboard: skipped ({$skipReason})";
            return $this->buildSkippedKeyboardResult($skipReason);
        }

        // ── Guard #2: Arabic أو Mixed ─────────────────────────────────
        if ($isArabic || $isMixed) {
            $reason = $isArabic ? 'arabic_query' : 'mixed_query';
            $decisionTrace[] = "⏭️ Keyboard: skipped ({$reason})";
            return $this->buildSkippedKeyboardResult($reason);
        }

        // ── Guard #3: gibberish EN→AR لا معنى له ─────────────────────
        $kbFix = $this->keyboardFixer->fix($keyword);
        if ($isGibberish && ($kbFix['direction'] === 'en_to_ar' || $kbFix['direction'] === null)) {
            $decisionTrace[] = "⏭️ Keyboard: skipped (gibberish_en_to_ar)";
            return $this->buildSkippedKeyboardResult('gibberish_en_to_ar');
        }

        // ── تحقق من الحاجة للـ fallback ──────────────────────────────
        $threshold     = (int) config('search.ai_trigger_threshold', 0);
        $needsFallback = $initialTotal <= $threshold || $isGibberish;

        if (! $needsFallback) {
            $decisionTrace[] = "⏭️ Keyboard: skipped (has_results)";
            return [
                'triggered'         => false,
                'decision'          => 'skipped_has_results',
                'fixed_query'       => $kbFix['fixed'],
                'confidence'        => $kbFix['confidence'],
                'direction'         => $kbFix['direction'],
                'total_after'       => $initialTotal,
                'top_results_after' => [],
            ];
        }

        // ── تنفيذ keyboard fix ─────────────────────────────────────────
        $minConf  = $isGibberish ? 0.25 : 0.4;
        $decision = 'no_fix';

        if ($kbFix['fixed'] === null) {
            $decision = 'no_conversion_found';
        } elseif ($kbFix['confidence'] < $minConf) {
            $decision = "low_confidence({$kbFix['confidence']} < {$minConf})";
        }

        if ($decision !== 'no_fix') {
            $decisionTrace[] = "⏭️ Keyboard: {$decision}";
            return [
                'triggered'         => false,
                'decision'          => $decision,
                'fixed_query'       => $kbFix['fixed'],
                'confidence'        => $kbFix['confidence'],
                'direction'         => $kbFix['direction'],
                'total_after'       => 0,
                'top_results_after' => [],
            ];
        }

        $fixedProcessed = $this->processor->processWithExpansion(
            $kbFix['fixed'], $projectId, $language
        );
        $fixedDto = new SearchQueryDTO(
            keyword: $kbFix['fixed'], projectId: $projectId,
            language: $language, page: 1, perPage: 5,
        );
        $kbResult   = $this->repository->searchWithExclusions(
            $fixedDto, $fixedProcessed, $preference, []
        );
        $totalAfter = $kbResult['total'];
        $topAfter   = $this->mapResultRows(
            array_slice($kbResult['items'], 0, 5),
            $fixedProcessed->cleanWords
        );

        $decisionTrace[] = "✅ Keyboard: '{$kbFix['fixed']}' (conf:{$kbFix['confidence']}) → {$totalAfter} results";

        return [
            'triggered'         => true,
            'decision'          => 'accepted',
            'fixed_query'       => $kbFix['fixed'],
            'confidence'        => $kbFix['confidence'],
            'direction'         => $kbFix['direction'],
            'total_after'       => $totalAfter,
            'top_results_after' => $topAfter,
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // AI Simulation
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
        bool              $skipRecovery,
        string            $skipReason,
        array             &$decisionTrace
    ): array {

        // ── Guard #1: recovery مُتخطَّى ──────────────────────────────
        if ($skipRecovery) {
            $decisionTrace[] = "⏭️ AI: skipped ({$skipReason})";
            return $this->buildEmptyAIResult($skipReason, $initialTotal);
        }

        // ── Guard #2: AI غير مفعّل ────────────────────────────────────
        $aiEnabled = (bool) config('search.ai_enabled', false);
        if (! $aiEnabled) {
            $decisionTrace[] = "⛔ AI: disabled";
            return $this->buildEmptyAIResult('disabled', $initialTotal);
        }

        // ── Guard #3: لا حاجة للـ fallback ───────────────────────────
        if (! $needsFallback) {
            $decisionTrace[] = "⏭️ AI: skipped (has_results)";
            return $this->buildEmptyAIResult('skipped_has_results', $initialTotal);
        }

        // ── تفسير AI ──────────────────────────────────────────────────
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

        // ── تنقية include tokens من Arabic stopwords ──────────────────
        $cleanedInclude = $this->filterArabicStopwords($interpretation['include']);

        $mergedExclude = array_values(array_unique(
            array_merge($excludeTerms, $interpretation['exclude'])
        ));
        $includeTokens = $cleanedInclude;

        if (empty($includeTokens) && ! empty(trim($interpretation['corrected'] ?? ''))) {
            $includeTokens = $this->filterArabicStopwords(
                array_filter(
                    explode(' ', mb_strtolower(trim($interpretation['corrected']))),
                    fn($w) => mb_strlen($w) >= 2
                )
            );
        }

        if (empty($includeTokens) && empty($mergedExclude)) {
            $decisionTrace[] = "⏭️ AI: no usable tokens";
            return $this->buildEmptyAIResult('no_tokens', $initialTotal);
        }

        if (empty($includeTokens) && ! empty($mergedExclude)) {
            $decisionTrace[] = "🔀 AI: exclude-only mode";
            $emptyProcessed  = $this->processor->processWithExpansion('', $projectId, $language);
            $emptyDto        = new SearchQueryDTO(
                keyword: '', projectId: $projectId, language: $language, page: 1, perPage: 5
            );
            $altResult = $this->repository->searchWithExclusions(
                $emptyDto, $emptyProcessed, $preference, $mergedExclude
            );
            $decisionTrace[] = "✅ AI exclude-only: {$altResult['total']} results";

            return [
                'triggered'    => true,
                'source'       => $interpretation['source'],
                'include'      => [],
                'exclude'      => $mergedExclude,
                'intent'       => $interpretation['intent'],
                'confidence'   => $interpretation['confidence'],
                'expanded'     => $interpretation['expanded'] ?? [],
                'final_query'  => '',
                'final_exclude'=> $mergedExclude,
                'total_after'  => $altResult['total'],
                'top_results'  => $this->mapResultRows(array_slice($altResult['items'], 0, 5), []),
            ];
        }

        $allTokens  = array_unique(array_merge($includeTokens, array_slice($interpretation['expanded'] ?? [], 0, 2)));
        $aiKeyword  = implode(' ', array_slice($allTokens, 0, 8));
        $aiProcessed = $this->processor->processWithExpansion($aiKeyword, $projectId, $language);
        $aiDto       = new SearchQueryDTO(
            keyword: $aiKeyword, projectId: $projectId, language: $language, page: 1, perPage: 5,
        );
        $aiResult = $this->repository->searchWithExclusions(
            $aiDto, $aiProcessed, $preference, $mergedExclude
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
            'final_exclude'=> $mergedExclude,
            'total_after'  => $aiResult['total'],
            'top_results'  => $this->mapResultRows(
                array_slice($aiResult['items'], 0, 5), $includeTokens
            ),
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // mapResultRows — يدعم objects و arrays
    // ─────────────────────────────────────────────────────────────────

    private function mapResultRows(array $rows, array $matchWords): array
    {
        return array_map(function ($row) use ($matchWords) {
            $isObj = is_object($row);

            $entryId  = $isObj
                ? (int)   ($row->entry_id       ?? 0)
                : (int)   ($row['entry_id']      ?? $row['entryId'] ?? 0);

            $title    = $isObj
                ? (string)($row->title           ?? '')
                : (string)($row['title']         ?? '');

            $score    = $isObj
                ? (float) ($row->final_score     ?? $row->weighted_score ?? $row->fulltext_score ?? 0)
                : (float) ($row['score']         ?? $row['final_score']  ?? $row['weighted_score'] ?? 0);

            $dataType = $isObj
                ? (string)($row->data_type_slug  ?? '')
                : (string)($row['data_type']     ?? $row['data_type_slug'] ?? '');

            return [
                'entry_id'      => $entryId,
                'title'         => $title,
                'score'         => round($score, 4),
                'data_type'     => $dataType,
                'matched_terms' => $this->findMatchedTerms($title, $matchWords),
            ];
        }, $rows);
    }

    // ─────────────────────────────────────────────────────────────────
    // Builders
    // ─────────────────────────────────────────────────────────────────

    private function buildSkippedKeyboardResult(string $reason): array
    {
        return [
            'triggered'         => false,
            'decision'          => "skipped_{$reason}",
            'fixed_query'       => null,
            'confidence'        => 0.0,
            'direction'         => null,
            'total_after'       => 0,
            'top_results_after' => [],
        ];
    }

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
            'final_exclude'=> [],
            'total_after'  => $initialTotal,
            'top_results'  => [],
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // Arabic Stopwords Filter
    // ─────────────────────────────────────────────────────────────────

    /**
     * يُزيل Arabic stopwords من tokens قبل إرسالها للـ AI
     * يمنع: include=["ما","بدي","ايفون"]
     */
    private function filterArabicStopwords(array $tokens): array
    {
        $stopwords = array_flip(self::ARABIC_STOPWORDS);

        return array_values(array_filter(
            $tokens,
            fn($token) => ! isset($stopwords[mb_strtolower(trim($token), 'UTF-8')])
                && mb_strlen(trim($token), 'UTF-8') >= 2
        ));
    }

    // ─────────────────────────────────────────────────────────────────
    // Query Classification Helpers
    // ─────────────────────────────────────────────────────────────────

    private function isArabicQuery(string $text): bool
    {
        $arabic = preg_match_all('/[\x{0600}-\x{06FF}]/u', $text);
        $total  = mb_strlen(preg_replace('/\s+/', '', $text), 'UTF-8');
        return $total > 0 && ($arabic / $total) > 0.3;
    }

    private function isMixedQuery(string $text): bool
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $total = $arabic = $english = 0;

        foreach ($chars as $char) {
            if ($char === ' ') continue;
            $total++;
            $code = mb_ord($char, 'UTF-8');
            if ($code >= 0x0600 && $code <= 0x06FF) $arabic++;
            elseif (ctype_alpha($char) && ord($char) < 128) $english++;
        }

        if ($total === 0) return false;
        return ($arabic / $total) > 0.15 && ($english / $total) > 0.15;
    }

    private function containsNegation(string $text): bool
    {
        $text    = mb_strtolower($text, 'UTF-8');
        $signals = [
            'ما بدي', 'ما اريد', 'ما أريد', 'لا اريد', 'لا أريد',
            'مش عايز', 'مو بادي', 'بدون', 'غير', 'ماعدا', 'سوى',
            'without', 'not', 'except', 'excluding', 'exclude', 'minus',
        ];

        // فرز تنازلي بالطول لمنع partial match
        usort($signals, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        foreach ($signals as $signal) {
            $pos = mb_strpos($text, $signal, 0, 'UTF-8');
            if ($pos === false) continue;

            // word boundary check للإنجليزي
            if (! $this->isArabicQuery($signal)) {
                $before = $pos > 0 ? mb_substr($text, $pos - 1, 1, 'UTF-8') : ' ';
                $after  = mb_substr($text, $pos + mb_strlen($signal, 'UTF-8'), 1, 'UTF-8');
                if (($before !== ' ' && $pos !== 0) || ($after !== '' && $after !== ' ')) {
                    continue;
                }
            }

            return true;
        }

        return false;
    }

    private function detectGibberish(string $text): bool
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        if (empty($text) || mb_strlen($text) < 3) return false;
        if ($this->isArabicQuery($text)) return false;

        $letters    = preg_replace('/[^a-z]/i', '', $text);
        $len        = strlen($letters);
        if ($len < 3) return false;

        $vowels     = preg_replace('/[^aeiou]/i', '', $letters);
        $vowelRatio = strlen($vowels) / $len;

        if ($vowelRatio < 0.10) return true;
        if (preg_match('/[^aeiou]{5,}/i', $letters)) return true;
        if ($this->hasRepeatingPattern($letters)) return true;

        return false;
    }

    private function hasRepeatingPattern(string $text): bool
    {
        $len = strlen($text);
        if ($len < 6) return false;

        for ($bl = 2; $bl <= (int)($len / 3); $bl++) {
            $block = substr($text, 0, $bl);
            $count = substr_count($text, $block);
            if ($count >= 3 && ($count * $bl) / $len >= 0.7) return true;
        }

        return false;
    }

    private function getVowelRatio(string $text): float
    {
        $letters = preg_replace('/[^a-z]/i', '', mb_strtolower($text));
        $len     = strlen($letters);
        if ($len === 0) return 0.0;
        $vowels  = preg_replace('/[^aeiou]/i', '', $letters);
        return round(strlen($vowels) / $len, 4);
    }

    // ─────────────────────────────────────────────────────────────────

    private function findMatchedTerms(string $title, array $words): array
    {
        $matched = [];
        $lower   = mb_strtolower($title, 'UTF-8');
        foreach ($words as $word) {
            if (! empty($word) && str_contains($lower, mb_strtolower($word, 'UTF-8'))) {
                $matched[] = $word;
            }
        }
        return array_values(array_unique($matched));
    }
}