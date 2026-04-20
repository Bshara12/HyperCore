<?php

namespace App\Domains\CMS\Services\ValueConditions;

use App\Domains\CMS\Repositories\Interface\DataEntryValueRepository;

class InCollectionConditionStrategy implements ValueConditionStrategy
{
  public function __construct(
    protected DataEntryValueRepository $valueRepository
  ) {}

  public function apply(string $field, $value, int $projectId, int $dataTypeId): array
  {
    $collectionIds =  $this->valueRepository->pluckEntryIdsByFieldInCollection($projectId, $dataTypeId, $value);

    if (empty($collectionIds)) {
      return [];
    }

    $entryIds = $this->valueRepository->returnEntryIdsFromCollectionItems($collectionIds);
    return $entryIds;
  }
}
