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
        private ArabicQueryNormalizer  $arabicNormalizer,
        private KeywordProcessor       $processor,
        private UserPreferenceAnalyzer $preferenceAnalyzer,
        private SearchRepositoryInterface $repository,
        private KeyboardLayoutFixer    $keyboardFixer,
        private AIQueryEnhancer        $aiEnhancer,
    ) {}

    // ─────────────────────────────────────────────────────────────────

    /**
     * تشغيل الـ pipeline خطوة بخطوة مع تسجيل كل مرحلة
     */
    public function runDebugPipeline(
        string $keyword,
        string $language,
        int    $projectId
    ): array {
        $startTime    = microtime(true);
        $decisionTrace = [];

        // ─── Step 1: Normalization ────────────────────────────────────
        $normStart   = microtime(true);
        $isArabic    = $this->isArabicQuery($keyword);
        $normalizeInfo = ['normalized' => $keyword, 'excludeTerms' => [],
                          'isNaturalLanguage' => false, 'cleanWords' => []];

        if ($isArabic || $language === 'ar') {
            $normalizeInfo = $this->arabicNormalizer->normalize($keyword);
        }

        $effectiveKeyword = !empty($normalizeInfo['normalized'])
            ? $normalizeInfo['normalized']
            : $keyword;

        $normalizationData = [
            'input_language'     => $language,
            'detected_arabic'    => $isArabic,
            'normalized'         => $effectiveKeyword,
            'original'           => $keyword,
            'exclude_terms'      => $normalizeInfo['excludeTerms'] ?? [],
            'is_natural_language'=> $normalizeInfo['isNaturalLanguage'] ?? false,
            'word_count_before'  => str_word_count($keyword),
            'word_count_after'   => str_word_count($effectiveKeyword),
            'time_ms'            => round((microtime(true) - $normStart) * 1000, 2),
        ];

        // ─── Step 2: Pre-AI Analysis ──────────────────────────────────
        $isGibberish  = $this->detectGibberish($effectiveKeyword);
        $typoSignals  = $this->detectTypoSignals($effectiveKeyword);
        $hasNegation  = $this->containsNegation($keyword);

        $preAiData = [
            'is_gibberish'   => $isGibberish,
            'has_negation'   => $hasNegation,
            'typo_signals'   => $typoSignals,
            'vowel_ratio'    => $this->getVowelRatio($effectiveKeyword),
            'token_count'    => str_word_count($effectiveKeyword),
        ];

        // ─── Step 3: Keyword Processing ───────────────────────────────
        $procStart = microtime(true);
        $processed = $this->processor->processWithExpansion(
            $effectiveKeyword, $projectId, $language
        );

        $processingData = [
            'clean_words'       => $processed->cleanWords,
            'boolean_query'     => $processed->booleanQuery,
            'relaxed_queries'   => $processed->relaxedQueries,
            'intent'            => $processed->intent,
            'had_db_expansion'  => $processed->hadDbExpansion,
            'db_expanded_groups'=> $processed->dbExpandedGroups,
            'expanded_groups'   => array_map(
                fn($g) => count($g) > 1 ? $g : null,
                $processed->expandedGroups
            ),
            'time_ms'           => round((microtime(true) - $procStart) * 1000, 2),
        ];

        // ─── Step 4: Initial Search ───────────────────────────────────
        $searchStart = microtime(true);
        $dto = new SearchQueryDTO(
            keyword:   $effectiveKeyword,
            projectId: $projectId,
            language:  $language,
            page:      1,
            perPage:   5,
        );

        $preference  = UserPreferenceDTO::noHistory();
        $excludeTerms = $normalizeInfo['excludeTerms'] ?? [];

        $initialResult = $this->repository->searchWithExclusions(
            $dto, $processed, $preference, $excludeTerms
        );

        $initialSearchData = [
            'total'         => $initialResult['total'],
            'boolean_query' => $processed->relaxedQueries[0] ?? '',
            'exclude_terms' => $excludeTerms,
            'top_results'   => array_map(
                fn($row) => [
                    'entry_id'    => $row->entry_id,
                    'title'       => $row->title ?? '',
                    'score'       => round((float)($row->weighted_score ?? $row->fulltext_score ?? 0), 4),
                    'data_type'   => $row->data_type_slug ?? '',
                    'language'    => $row->language ?? '',
                ],
                array_slice($initialResult['items'], 0, 5)
            ),
            'time_ms'       => round((microtime(true) - $searchStart) * 1000, 2),
        ];

        // ─── Step 5: Keyboard Fix Simulation ─────────────────────────
        $kbStart      = microtime(true);
        $kbFix        = $this->keyboardFixer->fix($keyword);
        $kbTriggered  = false;
        $kbTotalAfter = 0;
        $kbDecision   = 'not_attempted';

        $needsFallback = $initialResult['total'] <= 0 || $isGibberish || empty($processed->cleanWords);

        if ($needsFallback) {
            $minConf     = $isGibberish ? 0.25 : 0.4;
            $kbTriggered = $kbFix['fixed'] !== null && $kbFix['confidence'] >= $minConf;

            if ($kbFix['fixed'] === null) {
                $kbDecision = 'no_fix_found';
            } elseif ($kbFix['confidence'] < $minConf) {
                $kbDecision = "low_confidence({$kbFix['confidence']} < {$minConf})";
            } else {
                $kbDecision = 'accepted';
                // جلب نتائج الـ keyboard fix
                $fixedProcessed = $this->processor->processWithExpansion(
                    $kbFix['fixed'], $projectId, $language
                );
                $fixedDto = new SearchQueryDTO(
                    keyword: $kbFix['fixed'], projectId: $projectId,
                    language: $language, page: 1, perPage: 5,
                );
                $kbResult     = $this->repository->searchWithExclusions(
                    $fixedDto, $fixedProcessed, $preference, []
                );
                $kbTotalAfter = $kbResult['total'];
            }
        } else {
            $kbDecision = 'skipped_has_results';
        }

        $decisionTrace[] = $kbTriggered
            ? "✅ Keyboard fix: '{$kbFix['fixed']}' (conf: {$kbFix['confidence']})"
            : "⏭️ Keyboard fix: {$kbDecision}";

        $keyboardData = [
            'triggered'      => $kbTriggered,
            'decision'       => $kbDecision,
            'fixed_query'    => $kbFix['fixed'],
            'confidence'     => $kbFix['confidence'],
            'direction'      => $kbFix['direction'] ?? null,
            'total_after'    => $kbTotalAfter,
            'time_ms'        => round((microtime(true) - $kbStart) * 1000, 2),
        ];

        // ─── Step 6: AI Simulation ────────────────────────────────────
        $aiStart   = microtime(true);
        $aiEnabled = (bool) config('search.ai_enabled', false);
        $aiData    = $this->simulateAI(
            keyword:       $keyword,
            language:      $language,
            projectId:     $projectId,
            needsFallback: $needsFallback,
            aiEnabled:     $aiEnabled,
            isGibberish:   $isGibberish,
            hasNegation:   $hasNegation,
            initialTotal:  $initialResult['total'],
            excludeTerms:  $excludeTerms,
            preference:    $preference,
            decisionTrace: $decisionTrace,
            startTime:     $aiStart,
        );

        // ─── Step 7: Final Results ────────────────────────────────────
        $finalResults = $aiData['triggered'] && !empty($aiData['final_query'])
            ? $this->getFinalResults($aiData['final_query'], $aiData['final_exclude'] ?? [], $projectId, $language, $preference)
            : $initialResult['items'];

        // ─── Decision Trace Summary ───────────────────────────────────
        $decisionTrace[] = $initialResult['total'] === 0
            ? "❌ Initial search: 0 results"
            : "✅ Initial search: {$initialResult['total']} results";

        if ($isGibberish)   $decisionTrace[] = "⚠️ Gibberish detected";
        if ($hasNegation)   $decisionTrace[] = "🚫 Negation detected: " . implode(', ', $excludeTerms);
        if (!$aiEnabled)    $decisionTrace[] = "⛔ AI disabled (AI_SEARCH_ENABLED=false)";

        return [
            'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            'decision_trace'    => $decisionTrace,
            'input'             => [
                'original'   => $keyword,
                'language'   => $language,
                'project_id' => $projectId,
            ],
            'normalization'     => $normalizationData,
            'pre_ai_analysis'   => $preAiData,
            'processing'        => $processingData,
            'initial_search'    => $initialSearchData,
            'keyboard_fix'      => $keyboardData,
            'ai'                => $aiData,
            'final'             => [
                'total'   => $aiData['triggered'] ? ($aiData['total_after'] ?? $initialResult['total']) : $initialResult['total'],
                'source'  => $aiData['triggered'] ? 'ai' : ($kbTriggered ? 'keyboard' : 'initial'),
                'results' => array_map(
                    fn($row) => [
                        'entry_id'     => $row->entry_id,
                        'title'        => $row->title ?? '',
                        'score'        => round((float)($row->weighted_score ?? $row->fulltext_score ?? 0), 4),
                        'data_type'    => $row->data_type_slug ?? '',
                        'matched_terms'=> $this->findMatchedTerms(
                            $row->title ?? '',
                            $processed->cleanWords
                        ),
                    ],
                    array_slice($finalResults, 0, 10)
                ),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────

    private function simulateAI(
        string            $keyword,
        string            $language,
        int               $projectId,
        bool              $needsFallback,
        bool              $aiEnabled,
        bool              $isGibberish,
        bool              $hasNegation,
        int               $initialTotal,
        array             $excludeTerms,
        UserPreferenceDTO $preference,
        array             &$decisionTrace,
        float             $startTime
    ): array {
        $shouldTryAI = $aiEnabled && ($needsFallback || $isGibberish || $hasNegation);

        if (!$shouldTryAI) {
            $reason = !$aiEnabled ? 'disabled' : 'not_needed';
            $decisionTrace[] = "⏭️ AI: skipped ({$reason})";

            return [
                'triggered'   => false,
                'reason'      => $reason,
                'include'     => [],
                'exclude'     => [],
                'intent'      => '',
                'confidence'  => 0.0,
                'expanded'    => [],
                'final_query' => '',
                'total_after' => $initialTotal,
                'time_ms'     => 0,
            ];
        }

        try {
            $enhancement = $this->aiEnhancer->enhance($keyword, $language);

            $hasCorrection = mb_strtolower($enhancement['correctedQuery'])
                          !== mb_strtolower($keyword);
            $hasExpansion  = !empty($enhancement['expandedKeywords']);

            if (!$hasCorrection && !$hasExpansion) {
                $decisionTrace[] = "⏭️ AI: no improvement found";
                return [
                    'triggered'   => false,
                    'reason'      => 'no_improvement',
                    'include'     => [],
                    'exclude'     => $excludeTerms,
                    'intent'      => '',
                    'confidence'  => $enhancement['confidence'] ?? 0,
                    'expanded'    => [],
                    'final_query' => '',
                    'total_after' => $initialTotal,
                    'time_ms'     => round((microtime(true) - $startTime) * 1000, 2),
                ];
            }

            $aiKeyword = trim($enhancement['correctedQuery']);

            // جلب نتائج الـ AI
            $aiProcessed = $this->processor->processWithExpansion(
                $aiKeyword, $projectId, $language
            );
            $aiDto = new SearchQueryDTO(
                keyword: $aiKeyword, projectId: $projectId,
                language: $language, page: 1, perPage: 5,
            );
            $aiResult = $this->repository->searchWithExclusions(
                $aiDto, $aiProcessed, $preference, $excludeTerms
            );

            $decisionTrace[] = "✅ AI: corrected to '{$aiKeyword}' → {$aiResult['total']} results";

            return [
                'triggered'    => true,
                'reason'       => 'fallback',
                'source'       => $enhancement['source'] ?? 'api',
                'corrected'    => $enhancement['correctedQuery'],
                'include'      => array_filter(explode(' ', $aiKeyword), fn($w) => mb_strlen($w) >= 2),
                'exclude'      => $excludeTerms,
                'intent'       => '',
                'confidence'   => $enhancement['confidence'] ?? 0,
                'expanded'     => $enhancement['expandedKeywords'] ?? [],
                'final_query'  => $aiKeyword,
                'final_exclude'=> $excludeTerms,
                'total_after'  => $aiResult['total'],
                'time_ms'      => round((microtime(true) - $startTime) * 1000, 2),
            ];

        } catch (\Throwable $e) {
            $decisionTrace[] = "❌ AI: failed ({$e->getMessage()})";
            return [
                'triggered'   => false,
                'reason'      => 'error',
                'error'       => $e->getMessage(),
                'include'     => [],
                'exclude'     => [],
                'intent'      => '',
                'confidence'  => 0.0,
                'expanded'    => [],
                'final_query' => '',
                'total_after' => $initialTotal,
                'time_ms'     => round((microtime(true) - $startTime) * 1000, 2),
            ];
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────

    private function getFinalResults(
        string            $keyword,
        array             $excludeTerms,
        int               $projectId,
        string            $language,
        UserPreferenceDTO $preference
    ): array {
        $processed = $this->processor->processWithExpansion($keyword, $projectId, $language);
        $dto = new SearchQueryDTO(
            keyword: $keyword, projectId: $projectId,
            language: $language, page: 1, perPage: 10,
        );
        $result = $this->repository->searchWithExclusions($dto, $processed, $preference, $excludeTerms);
        return $result['items'];
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

        return $signals;
    }

    private function detectGibberish(string $text): bool
    {
        $text    = mb_strtolower(trim($text), 'UTF-8');
        $letters = preg_replace('/[^a-z]/i', '', $text);
        $len     = strlen($letters);

        if ($len < 3 || $this->isArabicQuery($text)) return false;

        $vowels = preg_replace('/[^aeiou]/i', '', $letters);
        if (strlen($vowels) / $len < 0.10) return true;
        if (preg_match('/[^aeiou\s]{5,}/i', $letters)) return true;

        return false;
    }

    private function containsNegation(string $text): bool
    {
        $text    = mb_strtolower($text, 'UTF-8');
        $signals = ['لا', 'ما', 'غير', 'بدون', 'مش', 'مو', 'ما بدي',
                    'لا اريد', 'without', 'not', "don't", 'exclude', 'avoid'];
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

    private function findMatchedTerms(string $title, array $words): array
    {
        $matched = [];
        $lower   = mb_strtolower($title, 'UTF-8');
        foreach ($words as $word) {
            if (str_contains($lower, mb_strtolower($word, 'UTF-8'))) {
                $matched[] = $word;
            }
        }
        return $matched;
    }
}