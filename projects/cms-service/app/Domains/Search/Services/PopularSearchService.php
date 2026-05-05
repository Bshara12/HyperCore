<?php

namespace App\Domains\Search\Services;

use App\Domains\Search\Actions\GetPopularSearchesAction;
use App\Domains\Search\Actions\RecomputePopularSearchesAction;
use App\Domains\Search\DTOs\PopularSearchQueryDTO;
use App\Domains\Search\DTOs\PopularSearchResultDTO;

class PopularSearchService
{
    private const VALID_WINDOWS = ['24h', '7d', '30d', 'all'];

    private const VALID_TYPES = ['trending', 'popular', 'both'];

    private const DEFAULT_LIMIT = 10;

    private const MAX_LIMIT = 20;

    public function __construct(
        private GetPopularSearchesAction $getAction,
        private RecomputePopularSearchesAction $recomputeAction,
    ) {}

    public function getPopular(
        int $projectId,
        string $language = 'en',
        string $window = '7d',
        string $type = 'both',
        int $limit = self::DEFAULT_LIMIT,
    ): PopularSearchResultDTO {

        // ─── Sanitize inputs ─────────────────────────────────────────
        $window = in_array($window, self::VALID_WINDOWS, true) ? $window : '7d';
        $type = in_array($type, self::VALID_TYPES, true) ? $type : 'both';
        $limit = min(max($limit, 1), self::MAX_LIMIT);

        $dto = new PopularSearchQueryDTO(
            projectId: $projectId,
            language: $language,
            window: $window,
            type: $type,
            limit: $limit,
        );

        return $this->getAction->execute($dto);
    }

    public function recompute(?int $projectId = null): array
    {
        return $this->recomputeAction->execute($projectId);
    }
}
