<?php

namespace App\Domains\Search\Services;

use App\Domains\Search\Actions\SearchEntriesAction;
use App\Domains\Search\DTOs\SearchQueryDTO;
use App\Domains\Search\DTOs\SearchResultDTO;

class SearchService
{
    public function __construct(
        private SearchEntriesAction $searchAction,
    ) {}

    public function search(SearchQueryDTO $dto): SearchResultDTO
    {
        return $this->searchAction->execute($dto);
    }
}
