<?php

declare(strict_types=1);

namespace App\Domains\Search\Actions;

use App\Domains\Search\DTOs\LogSearchDTO;
use App\Domains\Search\DTOs\SearchQueryDTO;
use App\Domains\Search\DTOs\SearchResultDTO;
use App\Domains\Search\DTOs\SearchResultItemDTO;
use App\Domains\Search\DTOs\UserPreferenceDTO;
use App\Domains\Search\Repositories\Interfaces\SearchRepositoryInterface;
use App\Domains\Search\Services\AIQueryEnhancer;
use App\Domains\Search\Services\KeyboardLayoutFixer;
use App\Domains\Search\Support\ArabicQueryNormalizer;
use App\Domains\Search\Support\EnglishQueryNormalizer;
use App\Domains\Search\Support\KeywordProcessor;
use App\Domains\Search\Support\ProcessedKeyword;
use App\Domains\Search\Support\QueryLanguageDetector;
use App\Domains\Search\Support\TypoCorrector;
use App\Domains\Search\Support\UserPreferenceAnalyzer;
use App\Jobs\IncrementViewCountJob;
use Illuminate\Support\Facades\Log;

final class SearchEntriesAction
{
    public function __construct(
        private readonly SearchRepositoryInterface $repository,
        private readonly KeywordProcessor          $processor,
        private readonly UserPreferenceAnalyzer    $preferenceAnalyzer,
        private readonly LogSearchAction           $logSearchAction,
        private readonly AIQueryEnhancer           $aiEnhancer,
        private readonly KeyboardLayoutFixer       $keyboardFixer,
        private readonly ArabicQueryNormalizer     $arabicNormalizer,
        private readonly EnglishQueryNormalizer    $englishNormalizer,
        private readonly TypoCorrector             $typoCorrector,
    ) {}

    // ─────────────────────────────────────────────────────────────────

    public function execute(SearchQueryDTO $dto): SearchResultDTO
    {
        // trace يُجمع فقط عند debug=true — صفر overhead في production
        $trace = [];

        // ── Step 1: Arabic Keyboard Mismatch ──────────────────────────
        $kbMismatch = $this->keyboardFixer->detectArabicKeyboardMismatch($dto->keyword);

        if ($kbMismatch['isKeyboardMismatch']) {
            $convertedDto    = $this->withKeyword($dto, $kbMismatch['convertedQuery']);
            $convertedResult = $this->runPipeline($convertedDto, $trace);

            if ($convertedResult->total > 0) {
                $this->trace($trace, $dto->debug, 'keyboard_mismatch', [
                    'original'  => $dto->keyword,
                    'converted' => $kbMismatch['convertedQuery'],
                    'confidence'=> $kbMismatch['confidence'],
                ]);

                return new SearchResultDTO(
                    keyword:       $dto->keyword,
                    total:         $convertedResult->total,
                    page:          $dto->page,
                    perPage:       $dto->perPage,
                    lastPage:      (int) ceil($convertedResult->total / $dto->perPage),
                    items:         $convertedResult->items,
                    keyboardFixed: true,
                    keyboardQuery: $kbMismatch['convertedQuery'],
                    debugTrace:    $dto->debug ? $trace : [],
                );
            }
        }

        return $this->runPipeline($dto, $trace);
    }

    // ─────────────────────────────────────────────────────────────────
    // Core Pipeline
    // ─────────────────────────────────────────────────────────────────

    /**
     * Pipeline الحقيقي — يُستدعى مباشرة أو بعد keyboard mismatch correction.
     * يجمع trace metadata فقط عند dto->debug = true.
     */
    private function runPipeline(SearchQueryDTO $dto, array &$trace): SearchResultDTO
    {
        $isArabic    = QueryLanguageDetector::isArabic($dto->keyword);
        $isMixed     = QueryLanguageDetector::isMixed($dto->keyword);
        $isGibberish = QueryLanguageDetector::isGibberish($dto->keyword);

        $this->trace($trace, $dto->debug, 'classification', [
            'is_arabic'    => $isArabic,
            'is_mixed'     => $isMixed,
            'is_gibberish' => $isGibberish,
        ]);

        // ── Normalization ─────────────────────────────────────────────
        [$effectiveKeyword, $normalizeInfo] = $this->normalizeQuery(
            $dto->keyword, $dto->language, $isArabic
        );
        $excludeTerms = $normalizeInfo['excludeTerms'] ?? [];

        $this->trace($trace, $dto->debug, 'normalization', [
            'original'         => $dto->keyword,
            'effective'        => $effectiveKeyword,
            'exclude_terms'    => $excludeTerms,
            'is_natural_lang'  => $normalizeInfo['isNaturalLanguage'] ?? false,
        ]);

        // ── Keyword Processing ────────────────────────────────────────
        $processed  = $this->processor->processWithExpansion(
            $effectiveKeyword, $dto->projectId, $dto->language
        );
        $preference = $this->preferenceAnalyzer->analyze(
            $dto->projectId, $dto->userId, $dto->sessionId
        );

        $this->trace($trace, $dto->debug, 'processing', [
            'clean_words'    => $processed->cleanWords,
            'boolean_query'  => $processed->booleanQuery,
            'relaxed_queries'=> $processed->relaxedQueries,
            'intent'         => $processed->intent,
        ]);

        // ── Initial Search ────────────────────────────────────────────
        $effectiveDto = $this->withKeyword($dto, $effectiveKeyword);
        $result       = $this->repository->searchWithExclusions(
            $effectiveDto, $processed, $preference, $excludeTerms
        );

        $this->trace($trace, $dto->debug, 'initial_search', [
            'total'         => $result['total'],
            'exclude_terms' => $excludeTerms,
        ]);

        $keyboardFixed = false;
        $keyboardQuery = null;
        $aiEnhanced    = false;
        $aiQuery       = null;

        $threshold     = (int) config('search.ai_trigger_threshold', 0);
        $aiEnabled     = (bool) config('search.ai_enabled', false);

        // ✅ Issue #4 Fix: $hasNegation حُذف من هنا
        // النفي عُولج في normalizeQuery() — لا داعي لتشغيل AI بسببه
        $needsFallback = $result['total'] <= $threshold
            || $isGibberish
            || empty($processed->cleanWords);

        if ($needsFallback) {

            // ── 5A: Keyboard Fix (إنجليزي خالص فقط) ──────────────────
            if (! $isArabic && ! $isMixed && ! $isGibberish) {
                $kbResult = $this->tryKeyboardFix($dto, $preference, $result);
                if ($kbResult !== null) {
                    $result        = $kbResult['result'];
                    $processed     = $kbResult['processed'];
                    $keyboardFixed = true;
                    $keyboardQuery = $kbResult['fixedQuery'];

                    $this->trace($trace, $dto->debug, 'keyboard_fix', [
                        'fixed_query' => $keyboardQuery,
                        'total_after' => $result['total'],
                    ]);
                }
            }

            // ── 5B: Typo Correction ───────────────────────────────────
            if ($result['total'] <= $threshold && ! $isArabic && ! $isMixed) {
                $typoResult = $this->tryTypoCorrection($dto, $preference, $result, $excludeTerms);
                if ($typoResult !== null) {
                    $result        = $typoResult['result'];
                    $processed     = $typoResult['processed'];
                    $keyboardFixed = true;
                    $keyboardQuery = $typoResult['correctedQuery'];

                    $this->trace($trace, $dto->debug, 'typo_correction', [
                        'corrected_query' => $typoResult['correctedQuery'],
                        'total_after'     => $result['total'],
                    ]);
                }
            }

            // ── 5C: AI Fallback ───────────────────────────────────────
            if (($result['total'] <= $threshold || $isGibberish) && $aiEnabled) {
                $aiResult = $this->tryAIFallback($dto, $preference, $result, $excludeTerms);
                if ($aiResult !== null) {
                    $result     = $aiResult['result'];
                    $processed  = $aiResult['processed'];
                    $aiEnhanced = true;
                    $aiQuery    = $aiResult['aiQuery'];

                    $this->trace($trace, $dto->debug, 'ai_fallback', [
                        'ai_query'   => $aiQuery,
                        'total_after'=> $result['total'],
                    ]);
                }
            }
        }

        $total = $result['total'];
        $rows  = $result['items'];
        $items = array_map(fn($row) => $this->mapToItemDTO($row, $processed), $rows);

        $this->logSearch($dto, $processed, $preference, $total);
        $this->dispatchViewTracking($rows, $dto->language);

        $this->trace($trace, $dto->debug, 'final', [
            'total'          => $total,
            'keyboard_fixed' => $keyboardFixed,
            'keyboard_query' => $keyboardQuery,
            'ai_enhanced'    => $aiEnhanced,
            'ai_query'       => $aiQuery,
        ]);

        return new SearchResultDTO(
            keyword:       $dto->keyword,
            total:         $total,
            page:          $dto->page,
            perPage:       $dto->perPage,
            lastPage:      $total > 0 ? (int) ceil($total / $dto->perPage) : 1,
            items:         $items,
            aiEnhanced:    $aiEnhanced,
            aiQuery:       $aiQuery,
            keyboardFixed: $keyboardFixed,
            keyboardQuery: $keyboardQuery,
            debugTrace:    $dto->debug ? $trace : [],
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // Trace Helper — صفر overhead عند debug=false
    // ─────────────────────────────────────────────────────────────────

    private function trace(array &$trace, bool $debug, string $step, array $data): void
    {
        if (! $debug) {
            return;
        }
        $trace[$step] = $data;
    }

    // ─────────────────────────────────────────────────────────────────
    // Normalization
    // ─────────────────────────────────────────────────────────────────

    private function normalizeQuery(string $keyword, string $language, bool $isArabic): array
    {
        if ($isArabic || $language === 'ar') {
            $info = $this->arabicNormalizer->normalize($keyword);
            return [
                ! empty($info['normalized']) ? $info['normalized'] : $keyword,
                $info,
            ];
        }

        if ($this->englishNormalizer->hasNegation($keyword)) {
            $info = $this->englishNormalizer->normalize($keyword);
            return [
                ! empty($info['normalized']) ? $info['normalized'] : $keyword,
                $info,
            ];
        }

        return [$keyword, [
            'normalized'        => $keyword,
            'excludeTerms'      => [],
            'isNaturalLanguage' => false,
            'cleanWords'        => [],
        ]];
    }

    // ─────────────────────────────────────────────────────────────────
    // Fallback Methods
    // ─────────────────────────────────────────────────────────────────

    private function tryKeyboardFix(
        SearchQueryDTO    $dto,
        UserPreferenceDTO $preference,
        array             $prevResult
    ): ?array {
        try {
            $fixResult = $this->keyboardFixer->fix($dto->keyword);
        } catch (\Throwable $e) {
            Log::warning('SearchEntriesAction: keyboardFixer failed', ['error' => $e->getMessage()]);
            return null;
        }

        if ($fixResult['fixed'] === null || $fixResult['confidence'] < 0.40) {
            return null;
        }

        $fixedProcessed = $this->processor->processWithExpansion(
            $fixResult['fixed'], $dto->projectId, $dto->language
        );
        $fixedResult = $this->repository->searchWithExclusions(
            $this->withKeyword($dto, $fixResult['fixed']),
            $fixedProcessed, $preference, []
        );

        return $fixedResult['total'] > $prevResult['total']
            ? ['result' => $fixedResult, 'processed' => $fixedProcessed, 'fixedQuery' => $fixResult['fixed']]
            : null;
    }

    private function tryTypoCorrection(
        SearchQueryDTO    $dto,
        UserPreferenceDTO $preference,
        array             $prevResult,
        array             $excludeTerms
    ): ?array {
        $correction = $this->typoCorrector->correct($dto->keyword);

        if (! $correction['hadCorrection']
            || $correction['corrected'] === null
            || $correction['confidence'] < 0.50
        ) {
            return null;
        }

        $correctedProcessed = $this->processor->processWithExpansion(
            $correction['corrected'], $dto->projectId, $dto->language
        );
        $correctedResult = $this->repository->searchWithExclusions(
            $this->withKeyword($dto, $correction['corrected']),
            $correctedProcessed, $preference, $excludeTerms
        );

        return $correctedResult['total'] > $prevResult['total']
            ? ['result' => $correctedResult, 'processed' => $correctedProcessed, 'correctedQuery' => $correction['corrected']]
            : null;
    }

    private function tryAIFallback(
        SearchQueryDTO    $dto,
        UserPreferenceDTO $preference,
        array             $prevResult,
        array             $excludeTerms
    ): ?array {
        try {
            $enhancement = $this->aiEnhancer->enhance($dto->keyword, $dto->language);
        } catch (\Throwable $e) {
            Log::error('SearchEntriesAction: AIEnhancer failed', ['error' => $e->getMessage()]);
            return null;
        }

        if ($enhancement['confidence'] < 0.20) {
            return null;
        }

        $includeTerms = $enhancement['include'] ?? [];

        if (empty($includeTerms)) {
            $corrected = trim($enhancement['correctedQuery'] ?? '');
            if (empty($corrected) || mb_strtolower($corrected) === mb_strtolower($dto->keyword)) {
                return null;
            }
            $includeTerms = array_values(array_filter(
                explode(' ', $corrected),
                fn($w) => mb_strlen($w) >= 2
            ));
        }

        if (empty($includeTerms)) {
            return null;
        }

        $aiKeyword       = implode(' ', array_unique($includeTerms));
        $combinedExclude = array_unique(array_merge($excludeTerms, $enhancement['exclude'] ?? []));
        $aiProcessed     = $this->processor->processWithExpansion($aiKeyword, $dto->projectId, $dto->language);
        $aiResult        = $this->repository->searchWithExclusions(
            $this->withKeyword($dto, $aiKeyword),
            $aiProcessed, $preference, $combinedExclude
        );

        return $aiResult['total'] > $prevResult['total']
            ? ['result' => $aiResult, 'processed' => $aiProcessed, 'aiQuery' => $aiKeyword]
            : null;
    }

    // ─────────────────────────────────────────────────────────────────
    // Utilities
    // ─────────────────────────────────────────────────────────────────

    private function withKeyword(SearchQueryDTO $dto, string $keyword): SearchQueryDTO
    {
        return new SearchQueryDTO(
            keyword:      $keyword,
            projectId:    $dto->projectId,
            language:     $dto->language,
            page:         $dto->page,
            perPage:      $dto->perPage,
            dataTypeSlug: $dto->dataTypeSlug,
            userId:       $dto->userId,
            sessionId:    $dto->sessionId,
            debug:        $dto->debug,
        );
    }

    private function dispatchViewTracking(array $rows, string $language): void
    {
        if (empty($rows)) return;
        $entryIds = array_values(array_unique(
            array_map(fn($row) => (int) $row->entry_id, $rows)
        ));
        IncrementViewCountJob::dispatch($entryIds, $language)->onQueue('search-tracking');
    }

    private function logSearch(
        SearchQueryDTO    $dto,
        ProcessedKeyword  $processed,
        UserPreferenceDTO $preference,
        int               $total
    ): void {
        try {
            $this->logSearchAction->execute(new LogSearchDTO(
                projectId:        $dto->projectId,
                keyword:          $dto->keyword,
                language:         $dto->language,
                resultsCount:     $total,
                detectedIntent:   $processed->intent['intent'],
                intentConfidence: $processed->intent['confidence'],
                userId:           $dto->userId,
                sessionId:        $dto->sessionId,
            ));
        } catch (\Throwable $e) {
            Log::warning('SearchEntriesAction: logSearch failed', ['error' => $e->getMessage()]);
        }
    }

    private function mapToItemDTO(object $row, ProcessedKeyword $processed): SearchResultItemDTO
    {
        $snippet = $this->generateSnippet($row->content ?? '', $processed->cleanWords);
        return new SearchResultItemDTO(
            entryId:     (int)  $row->entry_id,
            dataTypeId:  (int)  $row->data_type_id,
            projectId:   (int)  $row->project_id,
            language:           $row->language,
            title:       $this->highlightText($row->title   ?? '', $processed->cleanWords),
            snippet:     $this->highlightText($snippet,            $processed->cleanWords),
            status:             $row->status,
            score:       round((float) ($row->final_score ?? $row->weighted_score ?? 0), 4),
            publishedAt:        $row->published_at,
        );
    }

    private function generateSnippet(string $content, array $words, int $before = 60, int $after = 100): string
    {
        if (empty($content)) return '';
        $plain = trim(preg_replace('/\s+/', ' ',
            html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        ));
        if (empty($plain)) return '';
        $pos = null;
        foreach ($words as $word) {
            $p = mb_stripos($plain, $word, 0, 'UTF-8');
            if ($p !== false && ($pos === null || $p < $pos)) $pos = $p;
        }
        if ($pos === null) {
            return mb_strlen($plain, 'UTF-8') <= 160 ? $plain : mb_substr($plain, 0, 160, 'UTF-8') . '...';
        }
        $start = max(0, $pos - $before);
        $end   = min(mb_strlen($plain, 'UTF-8'), $pos + $after);
        return ($start > 0 ? '...' : '') . trim(mb_substr($plain, $start, $end - $start, 'UTF-8')) . ($end < mb_strlen($plain, 'UTF-8') ? '...' : '');
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
}