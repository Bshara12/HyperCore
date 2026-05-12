<?php

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
use App\Domains\Search\Support\TypoCorrector;
use App\Domains\Search\Support\UserPreferenceAnalyzer;
use App\Jobs\IncrementViewCountJob;
use Illuminate\Support\Facades\Log;

class SearchEntriesAction
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
        private readonly TypoCorrector             $typoCorrector,  // ← جديد
    ) {}

    // ─────────────────────────────────────────────────────────────────

    public function execute(SearchQueryDTO $dto): SearchResultDTO
    {
        Log::debug('SearchEntriesAction: start', [
            'keyword'    => $dto->keyword,
            'project_id' => $dto->projectId,
            'language'   => $dto->language,
        ]);

        // ══════════════════════════════════════════════════════════════
        // STEP 1 — Arabic Keyboard Mismatch Detection
        // "هحاخىث" → "iphone"
        // ══════════════════════════════════════════════════════════════
        $kbMismatch = $this->keyboardFixer->detectArabicKeyboardMismatch($dto->keyword);

        if ($kbMismatch['isKeyboardMismatch']) {
            Log::info('SearchEntriesAction: Arabic keyboard mismatch detected', [
                'original'   => $dto->keyword,
                'converted'  => $kbMismatch['convertedQuery'],
                'confidence' => $kbMismatch['confidence'],
            ]);

            $convertedDto    = $this->cloneDtoWithKeyword($dto, $kbMismatch['convertedQuery']);
            $convertedResult = $this->executeWithKeyword($convertedDto, false, false, false);

            if ($convertedResult->total > 0) {
                return new SearchResultDTO(
                    keyword:       $dto->keyword,
                    total:         $convertedResult->total,
                    page:          $dto->page,
                    perPage:       $dto->perPage,
                    lastPage:      (int) ceil($convertedResult->total / $dto->perPage),
                    items:         $convertedResult->items,
                    aiEnhanced:    false,
                    aiQuery:       null,
                    keyboardFixed: true,
                    keyboardQuery: $kbMismatch['convertedQuery'],
                );
            }

            Log::debug('SearchEntriesAction: keyboard mismatch converted but no results, continuing');
        }

        // ══════════════════════════════════════════════════════════════
        // STEP 2 — Pipeline العادي
        // ══════════════════════════════════════════════════════════════
        $isArabic    = $this->isArabicQuery($dto->keyword);
        $isMixed     = $this->isMixedQuery($dto->keyword);
        $isGibberish = $this->aiEnhancer->isGibberish($dto->keyword);

        return $this->executeWithKeyword($dto, $isArabic, $isMixed, $isGibberish);
    }

    // ─────────────────────────────────────────────────────────────────
    // Pipeline الداخلي
    // ─────────────────────────────────────────────────────────────────

    private function executeWithKeyword(
        SearchQueryDTO $dto,
        bool           $isArabic,
        bool           $isMixed,
        bool           $isGibberish
    ): SearchResultDTO {

        Log::debug('SearchEntriesAction: query type', [
            'keyword'      => $dto->keyword,
            'is_arabic'    => $isArabic,
            'is_mixed'     => $isMixed,
            'is_gibberish' => $isGibberish,
        ]);

        // ── Normalization ─────────────────────────────────────────────
        [$effectiveKeyword, $normalizeInfo] = $this->normalizeQuery(
            $dto->keyword,
            $dto->language,
            $isArabic
        );
        $excludeTerms = $normalizeInfo['excludeTerms'] ?? [];

        Log::debug('SearchEntriesAction: after normalization', [
            'effective_keyword' => $effectiveKeyword,
            'exclude_terms'     => $excludeTerms,
        ]);

        // ── Keyword Processing ────────────────────────────────────────
        $processed  = $this->processor->processWithExpansion(
            $effectiveKeyword,
            $dto->projectId,
            $dto->language
        );
        $preference = $this->preferenceAnalyzer->analyze(
            $dto->projectId,
            $dto->userId,
            $dto->sessionId
        );

        // ── Initial Search ────────────────────────────────────────────
        $effectiveDto = $this->cloneDtoWithKeyword($dto, $effectiveKeyword);
        $result       = $this->repository->searchWithExclusions(
            $effectiveDto,
            $processed,
            $preference,
            $excludeTerms
        );

        Log::debug('SearchEntriesAction: initial search', [
            'keyword' => $effectiveKeyword,
            'total'   => $result['total'],
        ]);

        $keyboardFixed = false;
        $keyboardQuery = null;
        $aiEnhanced    = false;
        $aiQuery       = null;

        $threshold     = (int) config('search.ai_trigger_threshold', 0);
        $aiEnabled     = (bool) config('search.ai_enabled', false);
        $needsFallback = $result['total'] <= $threshold
            || $isGibberish
            || empty($processed->cleanWords);

        if ($needsFallback) {

            // ── 5A. Keyboard Fix (إنجليزي خالص فقط) ──────────────────
            if (! $isArabic && ! $isMixed) {
                $kbResult = $this->tryKeyboardFix($dto, $preference, $result, $isGibberish);
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
            } else {
                Log::debug('SearchEntriesAction: keyboard fix skipped', [
                    'reason'  => $isArabic ? 'arabic_query' : 'mixed_query',
                    'keyword' => $dto->keyword,
                ]);
            }

            // ── 5B. Typo Correction (إنجليزي فقط، مستقل عن AI) ───────
            // يعمل دائماً بغض النظر عن AI_SEARCH_ENABLED
            if ($result['total'] <= $threshold && ! $isArabic && ! $isMixed) {
                $typoResult = $this->tryTypoCorrection(
                    $dto,
                    $preference,
                    $result,
                    $excludeTerms
                );
                if ($typoResult !== null) {
                    $result        = $typoResult['result'];
                    $processed     = $typoResult['processed'];
                    $keyboardFixed = true;                          // نعيد استخدام نفس الـ flag
                    $keyboardQuery = $typoResult['correctedQuery']; // نعرض الكلمة المصحَّحة
                    Log::info('SearchEntriesAction: typo correction succeeded', [
                        'corrected' => $typoResult['correctedQuery'],
                        'total'     => $result['total'],
                    ]);
                }
            }

            // ── 5C. AI Fallback ───────────────────────────────────────
            $stillNeedsAI = $result['total'] <= $threshold || $isGibberish;
            if ($stillNeedsAI && $aiEnabled) {
                $aiResult = $this->tryAIFallback($dto, $preference, $result, $excludeTerms);
                if ($aiResult !== null) {
                    $result     = $aiResult['result'];
                    $processed  = $aiResult['processed'];
                    $aiEnhanced = true;
                    $aiQuery    = $aiResult['aiQuery'];
                    Log::info('SearchEntriesAction: AI fallback succeeded', [
                        'ai_query' => $aiQuery,
                        'total'    => $result['total'],
                    ]);
                }
            }
        }

        // ── Build Response ────────────────────────────────────────────
        $total = $result['total'];
        $rows  = $result['items'];
        $items = array_map(fn($row) => $this->mapToItemDTO($row, $processed), $rows);

        $this->logSearch($dto, $processed, $preference, $total);
        $this->dispatchViewTracking($rows, $dto->language);

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
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // ✅ NEW: Typo Correction Step
    // ─────────────────────────────────────────────────────────────────

    /**
     * يُصحّح الأخطاء الإملائية ويُعيد نتائج البحث بالكلمة المصحَّحة.
     *
     * مستقل عن AI — يعمل دائماً بدون config.
     * يُستدعى فقط إذا:
     *   - النص إنجليزي (ليس عربي وليس مختلط)
     *   - البحث الأولي أعطى 0 نتائج
     */
    private function tryTypoCorrection(
        SearchQueryDTO    $dto,
        UserPreferenceDTO $preference,
        array             $prevResult,
        array             $excludeTerms
    ): ?array {

        $correction = $this->typoCorrector->correct($dto->keyword);

        if (! $correction['hadCorrection'] || $correction['corrected'] === null) {
            Log::debug('SearchEntriesAction: typo correction — no correction found', [
                'keyword' => $dto->keyword,
            ]);
            return null;
        }

        if ($correction['confidence'] < 0.50) {
            Log::debug('SearchEntriesAction: typo correction — low confidence', [
                'keyword'    => $dto->keyword,
                'corrected'  => $correction['corrected'],
                'confidence' => $correction['confidence'],
            ]);
            return null;
        }

        $correctedQuery = $correction['corrected'];

        Log::debug('SearchEntriesAction: typo correction applied', [
            'original'   => $dto->keyword,
            'corrected'  => $correctedQuery,
            'confidence' => $correction['confidence'],
        ]);

        $correctedProcessed = $this->processor->processWithExpansion(
            $correctedQuery,
            $dto->projectId,
            $dto->language
        );

        $correctedDto    = $this->cloneDtoWithKeyword($dto, $correctedQuery);
        $correctedResult = $this->repository->searchWithExclusions(
            $correctedDto,
            $correctedProcessed,
            $preference,
            $excludeTerms
        );

        Log::info('SearchEntriesAction: typo correction search done', [
            'original'       => $dto->keyword,
            'corrected'      => $correctedQuery,
            'prev_total'     => $prevResult['total'],
            'new_total'      => $correctedResult['total'],
        ]);

        if ($correctedResult['total'] > $prevResult['total']) {
            return [
                'result'         => $correctedResult,
                'processed'      => $correctedProcessed,
                'correctedQuery' => $correctedQuery,
            ];
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────────
    // Normalization
    // ─────────────────────────────────────────────────────────────────

    private function normalizeQuery(string $keyword, string $language, bool $isArabic): array
    {
        if ($isArabic || $language === 'ar') {
            $info             = $this->arabicNormalizer->normalize($keyword);
            $effectiveKeyword = ! empty($info['normalized']) ? $info['normalized'] : $keyword;
            return [$effectiveKeyword, $info];
        }

        if ($this->englishNormalizer->hasNegation($keyword)) {
            $info             = $this->englishNormalizer->normalize($keyword);
            $effectiveKeyword = ! empty($info['normalized']) ? $info['normalized'] : $keyword;

            Log::debug('SearchEntriesAction: English negation detected', [
                'original'      => $keyword,
                'effective'     => $effectiveKeyword,
                'exclude_terms' => $info['excludeTerms'],
            ]);

            return [$effectiveKeyword, $info];
        }

        return [$keyword, [
            'normalized'        => $keyword,
            'excludeTerms'      => [],
            'isNaturalLanguage' => false,
            'cleanWords'        => [],
        ]];
    }

    // ─────────────────────────────────────────────────────────────────
    // Keyboard Fix
    // ─────────────────────────────────────────────────────────────────

    private function tryKeyboardFix(
        SearchQueryDTO    $dto,
        UserPreferenceDTO $preference,
        array             $prevResult,
        bool              $isGibberish
    ): ?array {

        try {
            $fixResult = $this->keyboardFixer->fix($dto->keyword);
        } catch (\Throwable $e) {
            Log::warning('SearchEntriesAction: keyboardFixer threw', ['error' => $e->getMessage()]);
            return null;
        }

        if ($fixResult['fixed'] === null) return null;

        $minConf = $isGibberish ? 0.25 : 0.40;
        if ($fixResult['confidence'] < $minConf) {
            Log::debug('SearchEntriesAction: keyboard fix rejected', [
                'confidence' => $fixResult['confidence'],
                'threshold'  => $minConf,
            ]);
            return null;
        }

        $fixedQuery     = $fixResult['fixed'];
        $fixedProcessed = $this->processor->processWithExpansion(
            $fixedQuery, $dto->projectId, $dto->language
        );
        $fixedDto    = $this->cloneDtoWithKeyword($dto, $fixedQuery);
        $fixedResult = $this->repository->searchWithExclusions(
            $fixedDto, $fixedProcessed, $preference, []
        );

        Log::debug('SearchEntriesAction: keyboard fix result', [
            'original'   => $dto->keyword,
            'fixed'      => $fixedQuery,
            'confidence' => $fixResult['confidence'],
            'prev_total' => $prevResult['total'],
            'new_total'  => $fixedResult['total'],
        ]);

        return $fixedResult['total'] > $prevResult['total']
            ? ['result' => $fixedResult, 'processed' => $fixedProcessed, 'fixedQuery' => $fixedQuery]
            : null;
    }

    // ─────────────────────────────────────────────────────────────────
    // AI Fallback
    // ─────────────────────────────────────────────────────────────────

    private function tryAIFallback(
        SearchQueryDTO    $dto,
        UserPreferenceDTO $preference,
        array             $prevResult,
        array             $excludeTerms
    ): ?array {

        try {
            $enhancement = $this->aiEnhancer->enhance($dto->keyword, $dto->language);
        } catch (\Throwable $e) {
            Log::error('SearchEntriesAction: AIEnhancer threw', [
                'error' => $e->getMessage(), 'query' => $dto->keyword,
            ]);
            return null;
        }

        Log::debug('SearchEntriesAction: AI result', [
            'original'   => $dto->keyword,
            'include'    => $enhancement['include']  ?? [],
            'exclude'    => $enhancement['exclude']  ?? [],
            'confidence' => $enhancement['confidence'],
            'source'     => $enhancement['source']   ?? 'unknown',
        ]);

        if ($enhancement['confidence'] < 0.20) {
            Log::info('SearchEntriesAction: AI confidence too low, skipping');
            return null;
        }

        $includeTerms = $enhancement['include'] ?? [];

        if (empty($includeTerms)) {
            $corrected = trim($enhancement['correctedQuery'] ?? '');
            $original  = mb_strtolower(trim($dto->keyword), 'UTF-8');
            if (empty($corrected) || mb_strtolower($corrected, 'UTF-8') === $original) {
                Log::info('SearchEntriesAction: AI produced no usable include terms');
                return null;
            }
            $includeTerms = array_values(array_filter(
                explode(' ', $corrected),
                fn($w) => mb_strlen(trim($w), 'UTF-8') >= 2
            ));
        }

        if (empty($includeTerms)) return null;

        $aiExclude       = $enhancement['exclude'] ?? [];
        $combinedExclude = array_values(array_unique(array_merge($excludeTerms, $aiExclude)));
        $aiKeyword       = implode(' ', array_unique($includeTerms));

        if (empty(trim($aiKeyword))) return null;

        $aiProcessed = $this->processor->processWithExpansion(
            $aiKeyword, $dto->projectId, $dto->language
        );
        $aiDto    = $this->cloneDtoWithKeyword($dto, $aiKeyword);
        $aiResult = $this->repository->searchWithExclusions(
            $aiDto, $aiProcessed, $preference, $combinedExclude
        );

        Log::info('SearchEntriesAction: AI search done', [
            'include'    => $includeTerms,
            'exclude'    => $combinedExclude,
            'ai_keyword' => $aiKeyword,
            'prev_total' => $prevResult['total'],
            'new_total'  => $aiResult['total'],
        ]);

        return $aiResult['total'] > $prevResult['total']
            ? ['result' => $aiResult, 'processed' => $aiProcessed, 'aiQuery' => $aiKeyword]
            : null;
    }

    // ─────────────────────────────────────────────────────────────────
    // Query Type Detectors
    // ─────────────────────────────────────────────────────────────────

    private function isArabicQuery(string $text): bool
    {
        $arabicChars = preg_match_all('/[\x{0600}-\x{06FF}]/u', $text);
        $totalChars  = mb_strlen(preg_replace('/\s+/', '', $text), 'UTF-8');
        return $totalChars > 0 && ($arabicChars / $totalChars) > 0.30;
    }

    private function isMixedQuery(string $text): bool
    {
        $chars   = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $total   = 0;
        $arabic  = 0;
        $english = 0;

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

    // ─────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────

    private function cloneDtoWithKeyword(SearchQueryDTO $dto, string $keyword): SearchQueryDTO
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
            Log::warning('SearchEntriesAction: logSearch failed', [
                'error' => $e->getMessage(), 'keyword' => $dto->keyword,
            ]);
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
            title:       $this->highlightText($row->title ?? '', $processed->cleanWords),
            snippet:     $this->highlightText($snippet,           $processed->cleanWords),
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
        $pos = $this->findFirstMatch($plain, $words);
        if ($pos === null) {
            return mb_strlen($plain, 'UTF-8') <= 160
                ? $plain
                : mb_substr($plain, 0, 160, 'UTF-8') . '...';
        }
        $start = max(0, $pos - $before);
        $end   = min(mb_strlen($plain, 'UTF-8'), $pos + $after);
        return ($start > 0 ? '...' : '')
            . trim(mb_substr($plain, $start, $end - $start, 'UTF-8'))
            . ($end < mb_strlen($plain, 'UTF-8') ? '...' : '');
    }

    private function findFirstMatch(string $text, array $words): ?int
    {
        $earliest = null;
        foreach ($words as $word) {
            $pos = mb_stripos($text, $word, 0, 'UTF-8');
            if ($pos !== false && ($earliest === null || $pos < $earliest)) {
                $earliest = $pos;
            }
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
}