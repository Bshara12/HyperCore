<?php

declare(strict_types=1);

namespace App\Domains\Search\Repositories\Interfaces;

use App\Domains\Search\DTOs\SearchQueryDTO;
use App\Domains\Search\Support\Query\QueryPlan;
use App\Domains\Search\Support\Ranking\CorpusStatistics;
use App\Domains\Search\Support\Retrieval\CandidateWindow;

/**
 * استرجاع المرشَّحين وما يلزم لترتيبهم.
 *
 * الواجهة تتلقّى QueryPlan لا سلسلة نصّية: ما يصل إلى قاعدة البيانات
 * حقولٌ مكتوبة الأنواع، لا نصّ يُلصَق في نحو استعلام.
 */
interface SearchIndexQueryRepositoryInterface
{
    /**
     * @return array{rows: array<int, object>, total: int, relaxation: int, window: CandidateWindow}
     */
    public function fetchCandidates(QueryPlan $plan, SearchQueryDTO $dto): array;

    /**
     * @param  int[]  $entryIds
     * @return array<int, array<string, array<int, array{value_text:?string, value_num:?float}>>>
     */
    public function attributesFor(array $entryIds, int $projectId, string $language): array;

    public function corpusStatistics(QueryPlan $plan, int $projectId, string $language): CorpusStatistics;

    public function incrementClickCount(int $entryId, string $language): void;
}
