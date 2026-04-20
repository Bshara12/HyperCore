<?php

namespace App\Domains\CMS\Services\ValueConditions;

use App\Domains\CMS\Repositories\Interface\DataEntryValueRepository;

class BetweenValueConditionStrategy implements ValueConditionStrategy
{
  public function __construct(
    protected DataEntryValueRepository $valueRepository
  ) {
  }

  public function apply(string $field, $value, int $projectId, int $dataTypeId): array
  {
    return $this->valueRepository->pluckEntryIdsByFieldBetween(
      $field,
      (array)$value
    );
  }
}

