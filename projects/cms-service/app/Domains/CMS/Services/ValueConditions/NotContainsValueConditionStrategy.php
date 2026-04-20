<?php

namespace App\Domains\CMS\Services\ValueConditions;

use App\Domains\CMS\Repositories\Interface\DataEntryRepositoryInterface;
use App\Domains\CMS\Repositories\Interface\DataEntryValueRepository;

class NotContainsValueConditionStrategy implements ValueConditionStrategy
{
  public function __construct(
    protected DataEntryValueRepository $valueRepository,
    protected DataEntryRepositoryInterface $entryRepository
  ) {
  }

  public function apply(string $field, $value, int $projectId, int $dataTypeId): array
  {
    $badIds = $this->valueRepository->pluckEntryIdsByFieldLike(
      $field,
      "%{$value}%"
    );

    return $this->entryRepository->pluckIdsForProjectTypeExcluding(
      $projectId,
      $dataTypeId,
      $badIds
    );
  }
}

