<?php

namespace App\Domains\Search\Services;

use App\Domains\Search\Actions\GetSuggestionsAction;
use App\Domains\Search\DTOs\SuggestionQueryDTO;
use App\Domains\Search\DTOs\SuggestionResultDTO;

class SuggestionService
{
    private const DEFAULT_LIMIT = 8;
    private const MAX_LIMIT     = 15;

    public function __construct(
        private GetSuggestionsAction $getSuggestionsAction,
    ) {}

    public function getSuggestions(
        string $prefix,
        int    $projectId,
        string $language,
        int    $limit  = self::DEFAULT_LIMIT,
        ?int   $userId = null,
    ): SuggestionResultDTO {

        $dto = new SuggestionQueryDTO(
            prefix:    $prefix,
            projectId: $projectId,
            language:  $language,
            limit:     min($limit, self::MAX_LIMIT),
            userId:    $userId,
        );

        return $this->getSuggestionsAction->execute($dto);
    }
}