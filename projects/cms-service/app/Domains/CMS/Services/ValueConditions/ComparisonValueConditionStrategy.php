<?php

namespace App\Domains\CMS\Services\ValueConditions;

use App\Domains\CMS\Repositories\Interface\DataEntryValueRepository;

class ComparisonValueConditionStrategy implements ValueConditionStrategy
{
  protected string $operator;

  public function __construct(
    string $operator,
    protected DataEntryValueRepository $valueRepository
  ) {
    $this->operator = $operator;
  }

  public function apply(string $field, $value, int $projectId, int $dataTypeId): array
  {
    return $this->valueRepository->pluckEntryIdsByFieldComparison(
      $field,
      $this->operator,
      $value
    );
  }
}

