<?php

declare(strict_types=1);

namespace App\Domains\Search\Services;

use App\Domains\Search\DTOs\SearchQueryDTO;
use App\Domains\Search\Repositories\Interfaces\SearchIndexQueryRepositoryInterface;
use App\Domains\Search\Support\Query\QueryAnalyzer;
use App\Domains\Search\Support\Query\QueryPlan;
use App\Domains\Search\Support\Ranking\Bm25fScorer;
use App\Domains\Search\Support\Ranking\PersonalizationScorer;
use App\Domains\Search\Support\Ranking\SignalScorer;
use App\Domains\Search\Support\Retrieval\BooleanQueryBuilder;
use App\Domains\Search\Support\Text\Segmenter;
use App\Domains\Search\Support\Text\TextFolder;
use App\Domains\Search\Support\Text\UnicodeScript;
use App\Domains\Search\Support\UserPreferenceAnalyzer;

/**
 * SearchExplainService — تفكيك بحث واحد إلى مكوّناته.
 *
 * ─── لماذا يستحقّ هذا أن يكون واجهة ─────────────────────────────────
 *
 * محرك البحث نظام يقرّر ترتيباً، وأي نظام يقرّر يجب أن يكون قادراً على
 * تبرير قراره. بلا ذلك يصير ضبط الأوزان تخميناً: يشكو صاحب المحتوى أن
 * صفحته لا تظهر، فلا يوجد ما يُقال سوى "الخوارزمية".
 *
 * هذه الخدمة تُظهر، لاستعلام بعينه: كيف طُبِّع نصّه، وكيف قُسِّم، وأي
 * scripts كُشفت، وأي شروط استُخرجت وبأي ثقة، وأي تعبير وصل إلى MySQL،
 * ثم لكل نتيجة: كم من درجتها صلةٌ نصّية وكم منها إشارات وكم تخصيص.
 *
 * وهي تعيد تنفيذ المسار نفسه لا نسخةً منه — فما يُشرَح هو ما يقع فعلاً.
 */
final class SearchExplainService
{
    public function __construct(
        private readonly QueryAnalyzer $analyzer,
        private readonly BooleanQueryBuilder $booleanBuilder,
        private readonly SearchIndexQueryRepositoryInterface $repository,
        private readonly Bm25fScorer $bm25,
        private readonly SignalScorer $signals,
        private readonly PersonalizationScorer $personalization,
        private readonly UserPreferenceAnalyzer $preferences,
        private readonly QueryRescueService $rescue,
        private readonly QueryPlanRefiner $refiner,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function explain(
        string $keyword,
        string $language,
        int $projectId,
        ?int $userId = null,
        int $limit = 10
    ): array {
        $started = microtime(true);

        $plan = $this->analyzer->analyze($keyword, null, $projectId, $language);

        $dto = new SearchQueryDTO(
            keyword: $keyword,
            projectId: $projectId,
            language: $language,
            page: 1,
            perPage: $limit,
        );

        $candidates = $this->repository->fetchCandidates($plan, $dto);

        /*
         | يعيد الشرح تنفيذ سلّم الإنقاذ نفسه لا وصفه.
         |
         | لو اكتفى بوصف ما "كان سيحدث" لانفصل الشرح عن السلوك عند أول
         | تعديل في الترتيب أو العتبات — وشرحٌ يخالف ما يقع أضرّ من
         | غياب الشرح، لأنه يوجّه الضبط في الاتجاه الخطأ بثقة.
         */
        $rescue = ['attempted' => false, 'accepted' => null, 'tried' => []];

        if ($candidates['total'] === 0) {
            $rescue['attempted'] = true;

            foreach ($this->rescue->candidates($plan, $projectId, $language) as $attempt) {
                $outcome = $this->repository->fetchCandidates($attempt['plan'], $dto);

                $rescue['tried'][] = [
                    'strategy' => $attempt['strategy'],
                    'terms' => $attempt['plan']->terms,
                    'total' => $outcome['total'],
                ];

                if ($outcome['total'] > 0) {
                    $rescue['accepted'] = $attempt['strategy'];
                    $plan = $attempt['plan'];
                    $candidates = $outcome;

                    break;
                }
            }
        }

        $usedRefiner = false;

        if ($candidates['total'] === 0) {
            $refined = $this->refiner->refine($plan, $projectId, $language);

            if ($refined !== null) {
                $retry = $this->repository->fetchCandidates($refined, $dto);

                if ($retry['total'] > 0) {
                    $plan = $refined;
                    $candidates = $retry;
                    $usedRefiner = true;
                }
            }
        }

        return [
            'execution_time_ms' => round((microtime(true) - $started) * 1000, 2),
            'input' => ['keyword' => $keyword, 'language' => $language, 'project_id' => $projectId],
            'text_pipeline' => $this->explainText($keyword),
            'plan' => $plan->toArray(),
            'retrieval' => $this->explainRetrieval($plan, $candidates),
            'rescue' => $rescue,
            'refiner' => ['used' => $usedRefiner, 'source' => $plan->source],
            'results' => $this->explainResults($plan, $dto, $candidates['rows'], $userId, $limit),
        ];
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function explainText(string $keyword): array
    {
        $folded = TextFolder::fold($keyword);

        return [
            'raw' => $keyword,
            'folded' => $folded,
            'script_profile' => UnicodeScript::profile($keyword),
            'dominant_script' => UnicodeScript::dominant($keyword),
            'is_mixed' => UnicodeScript::isMixed($keyword),
            'needs_ngram' => UnicodeScript::needsNgram($keyword),
            'tokens' => Segmenter::tokenize($folded),
            'ngram_text' => Segmenter::ngramText($folded),
        ];
    }

    /**
     * @param  array{rows: array<int, object>, total: int, relaxation: int, window: mixed}  $candidates
     * @return array<string, mixed>
     */
    private function explainRetrieval(QueryPlan $plan, array $candidates): array
    {
        $queries = $this->booleanBuilder->build($plan);

        return [
            'boolean_queries' => $queries,
            'relaxation_step_used' => $candidates['relaxation'],
            'query_actually_used' => $queries[$candidates['relaxation']] ?? null,
            'match_target' => $plan->needsNgram ? 'ft_ngram (ngram parser)' : 'ft_fold (default parser)',
            'hard_filters' => array_map(
                static fn ($filter): array => $filter->toArray(),
                $plan->hardFilters()
            ),
            'soft_filters' => array_map(
                static fn ($filter): array => $filter->toArray(),
                $plan->softFilters()
            ),
            'candidates_fetched' => count($candidates['rows']),
            'total_matches' => $candidates['total'],
            'window' => [
                'size' => $candidates['window']->size,
                'sql_offset' => $candidates['window']->sqlOffset,
                'slice_offset' => $candidates['window']->sliceOffset,
                'reranked' => $candidates['window']->rerank,
            ],
        ];
    }

    /**
     * تفكيك درجة كل نتيجة إلى مكوّناتها.
     *
     * @param  array<int, object>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function explainResults(
        QueryPlan $plan,
        SearchQueryDTO $dto,
        array $rows,
        ?int $userId,
        int $limit
    ): array {
        if ($rows === []) {
            return [];
        }

        $entryIds = array_map(static fn (object $row): int => (int) $row->entry_id, $rows);

        $stats = $this->repository->corpusStatistics($plan, $dto->projectId, $dto->language);
        $attributes = $this->repository->attributesFor($entryIds, $dto->projectId, $dto->language);
        $preference = $this->preferences->analyze($dto->projectId, $userId, null);

        $explained = [];

        foreach ($rows as $row) {
            $entryId = (int) $row->entry_id;
            $rowAttributes = $attributes[$entryId] ?? [];

            $bm25 = $this->bm25->score($plan, $row, $stats);
            $phrase = $this->bm25->phraseBonus($plan, $row);
            $signal = $this->signals->score($plan, $row, $rowAttributes);
            $base = $bm25 + $phrase + $signal;
            $final = $this->personalization->apply($base, $row, $preference);

            $explained[] = [
                'sort_key' => round($final, 6),
                'entry_id' => $entryId,
                'title' => $row->title,
                'data_type_slug' => $row->data_type_slug,
                'score' => [
                    'bm25f' => round($bm25, 4),
                    'phrase_bonus' => round($phrase, 4),
                    'signals' => round($signal, 4),
                    'base' => round($base, 4),
                    'personalization_multiplier' => $base > 0 ? round($final / $base, 4) : 1.0,
                    'final' => round($final, 4),
                ],
                'lengths' => [
                    'title_terms' => (int) $row->title_terms,
                    'content_terms' => (int) $row->content_terms,
                    'meta_terms' => (int) $row->meta_terms,
                ],
                'attributes' => $rowAttributes,
            ];
        }

        /*
         | الترتيب على مفتاح مستقلّ لا على حقل متداخل.
         |
         | الشرح يحمل بنية حرّة (سمات المستند تختلف من مشروع لآخر)، فلا
         | يستطيع المحلّل الساكن أن يتحقّق من عمق مثل $a['score']['final'].
         | فصل مفتاح الترتيب يجعل العقد صريحاً ومفحوصاً.
         */
        $keys = array_map(
            static fn (array $entry): float => $entry['sort_key'],
            $explained
        );

        array_multisort($keys, SORT_DESC, $explained);

        return array_map(
            static function (array $entry): array {
                unset($entry['sort_key']);

                return $entry;
            },
            array_slice($explained, 0, $limit)
        );
    }

    /**
     * وزن كل مصطلح في هذا المتن — يفسّر لماذا تفوز كلمة على أخرى.
     *
     * @return array<string, mixed>
     */
    public function explainTermWeights(string $keyword, int $projectId, string $language): array
    {
        $plan = $this->analyzer->analyze($keyword, null, $projectId, $language);
        $stats = $this->repository->corpusStatistics($plan, $projectId, $language);

        $weights = [];

        foreach ($plan->allTerms() as $term) {
            $weights[] = [
                'term' => $term,
                'document_frequency' => $stats->documentFrequency($term),
                'idf' => round($stats->inverseDocumentFrequency($term), 4),
                'is_expansion' => in_array($term, $plan->expansions, true),
            ];
        }

        usort($weights, static fn (array $a, array $b): int => $b['idf'] <=> $a['idf']);

        return [
            'corpus' => [
                'document_count' => $stats->documentCount,
                'avg_title_terms' => $stats->avgTitleTerms,
                'avg_content_terms' => $stats->avgContentTerms,
                'avg_meta_terms' => $stats->avgMetaTerms,
            ],
            'terms' => $weights,
        ];
    }
}
