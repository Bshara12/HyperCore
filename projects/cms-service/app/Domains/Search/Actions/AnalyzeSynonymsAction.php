<?php

namespace App\Domains\Search\Actions;

use App\Domains\Search\DTOs\SynonymAnalysisResultDTO;
use App\Domains\Search\DTOs\SynonymSuggestionDTO;
use App\Domains\Search\Repositories\Interfaces\SynonymSuggestionRepositoryInterface;
use App\Domains\Search\Support\CoOccurrenceAnalyzer;
use App\Domains\Search\Support\KeywordTokenizer;
use App\Domains\Search\Support\SynonymScorer;
use Illuminate\Support\Facades\Log;

class AnalyzeSynonymsAction
{
    /*
       * الحدود لضبط الأداء
       */
    private const MIN_JACCARD_SCORE = 0.15;  // تجاهل أزواج أقل من 15% تشابه

    private const MIN_COOCCURRENCE_COUNT = 2;      // يجب أن يظهرا معاً مرتين على الأقل

    private const MIN_CONFIDENCE_SCORE = 0.20;  // تجاهل اقتراحات ضعيفة جداً

    private const MAX_WORD_PAIRS = 50000;  // حد أقصى للأزواج التي نُقيّمها

    public function __construct(
        private SynonymSuggestionRepositoryInterface $repository,
        private KeywordTokenizer $tokenizer,
        private CoOccurrenceAnalyzer $analyzer,
        private SynonymScorer $scorer,
    ) {}

    // ─────────────────────────────────────────────────────────────────

    public function execute(
        int $projectId,
        string $language = 'en',
        int $days = 90,
        int $minCount = 2,
    ): SynonymAnalysisResultDTO {

        $start = microtime(true);

        Log::info('SynonymAnalysis: starting', [
            'project_id' => $projectId,
            'language' => $language,
            'days' => $days,
        ]);

        // ─── 1. جلب الـ keywords من الـ logs ─────────────────────────
        $keywords = $this->repository->fetchKeywordsForAnalysis(
            $projectId,
            $language,
            $days,
            $minCount
        );

        if (empty($keywords)) {
            return $this->emptyResult($projectId, $start);
        }

        $keywordCount = count($keywords);

        Log::info('SynonymAnalysis: keywords fetched', ['count' => $keywordCount]);

        // ─── 2. Tokenize كل keyword ───────────────────────────────────
        $tokenizedKeywords = $this->tokenizer->tokenizeAll($keywords);

        // تجاهل keywords ذات token واحد فقط (لا co-occurrence ممكن)
        $multiTokenKeywords = array_filter(
            $tokenizedKeywords,
            fn ($tokens) => count($tokens) >= 2
        );

        $uniqueWords = $this->extractUniqueWords($tokenizedKeywords);

        Log::info('SynonymAnalysis: tokenization done', [
            'unique_words' => count($uniqueWords),
            'multi_token_keywords' => count($multiTokenKeywords),
        ]);

        // ─── 3. حساب Co-occurrence ────────────────────────────────────
        $analysisData = $this->analyzer->analyze($tokenizedKeywords);
        $coOccurrence = $analysisData['coOccurrence'];
        $wordFrequency = $analysisData['wordFrequency'];
        if (empty($coOccurrence)) {
            return $this->emptyResult($projectId, $start, $keywordCount, count($uniqueWords));
        }

        // ─── 4. إيجاد أعلى co-occurrence للـ normalization ────────────
        $maxCoOccurrence = max($coOccurrence);

        // ─── 5. تقييم كل زوج وحساب الـ scores ───────────────────────
        $suggestions = [];
        $pairsChecked = 0;

        /*
             * بدل إنشاء كل permutations (n² عملية)
             * نستخدم الـ coOccurrence array المبنية مسبقاً
             * وهذا يعني أننا نُقيّم فقط الأزواج التي ظهرت معاً فعلاً
             */
        foreach ($coOccurrence as $pairKey => $coCount) {

            if ($pairsChecked >= self::MAX_WORD_PAIRS) {

                break;
            }

            // if ($coCount < self::MIN_COOCCURRENCE_COUNT) {

            //   continue;
            // }

            $pairsChecked++;
            // استخراج الكلمتين من الـ key
            [$wordA, $wordB] = explode('|', $pairKey, 2);

            $freqA = $wordFrequency[$wordA] ?? 0;
            $freqB = $wordFrequency[$wordB] ?? 0;

            // ─── Jaccard Similarity ───────────────────────────────────
            $jaccardScore = $this->analyzer->jaccardSimilarity(
                $wordA,
                $wordB,
                $coOccurrence,
                $wordFrequency
            );

            if ($jaccardScore < self::MIN_JACCARD_SCORE) {
                continue;
            }

            // ─── Confidence Score النهائي ────────────────────────────
            $confidenceScore = $this->scorer->calculate(
                jaccardScore: $jaccardScore,
                coOccurrenceCount: $coCount,
                wordACount: $freqA,
                wordBCount: $freqB,
                maxCoOccurrence: $maxCoOccurrence,
            );

            if ($confidenceScore < self::MIN_CONFIDENCE_SCORE) {
                continue;
            }

            $suggestions[] = SynonymSuggestionDTO::normalized(
                wordA: $wordA,
                wordB: $wordB,
                jaccardScore: $jaccardScore,
                cooccurrenceCount: $coCount,
                confidenceScore: $confidenceScore,
                wordACount: $freqA,
                wordBCount: $freqB,
                language: $language,
            );
        }

        // ─── 6. ترتيب حسب الـ confidence ─────────────────────────────
        usort($suggestions, fn ($a, $b) => $b->confidenceScore <=> $a->confidenceScore);

        // ─── 7. حفظ في DB ─────────────────────────────────────────────
        $savedCount = 0;
        if (! empty($suggestions)) {
            $savedCount = $this->repository->saveSuggestions($projectId, $suggestions);
        }

        Log::info('SynonymAnalysis: completed', [
            'keywords_analyzed' => $keywordCount,
            'pairs_evaluated' => $pairsChecked,
            'suggestions_generated' => $savedCount,
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
        ]);

        return new SynonymAnalysisResultDTO(
            projectId: $projectId,
            keywordsAnalyzed: $keywordCount,
            uniqueWordsFound: count($uniqueWords),
            pairsEvaluated: $pairsChecked,
            suggestionsGenerated: $savedCount,
            suggestions: array_slice($suggestions, 0, 20), // أول 20 للعرض
            durationMs: (microtime(true) - $start) * 1000,
        );
    }

    // ─────────────────────────────────────────────────────────────────

    private function extractUniqueWords(array $tokenizedKeywords): array
    {
        $words = [];
        foreach ($tokenizedKeywords as $tokens) {
            foreach ($tokens as $token) {
                $words[$token] = true;
            }
        }

        return array_keys($words);
    }

    private function emptyResult(
        int $projectId,
        float $start,
        int $keywordsAnalyzed = 0,
        int $uniqueWords = 0
    ): SynonymAnalysisResultDTO {
        return new SynonymAnalysisResultDTO(
            projectId: $projectId,
            keywordsAnalyzed: $keywordsAnalyzed,
            uniqueWordsFound: $uniqueWords,
            pairsEvaluated: 0,
            suggestionsGenerated: 0,
            suggestions: [],
            durationMs: (microtime(true) - $start) * 1000,
        );
    }
}
