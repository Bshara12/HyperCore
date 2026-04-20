<?php

namespace App\Domains\CMS\Repositories\Interface;

use App\Models\DataEntry;

interface DataEntryRepositoryInterface
{
  public function create(array $data);
  public function find(int $id): ?DataEntry;

  public function findOrFail(int $id): DataEntry;
  public function findForProjectOrFail(
    int $entryId,
    int $projectId
  ): object;

  public function updateStatus(int $id, string $status): void;
  public function schedule(int $id, string $publishAt): void;
  public function touchUpdatedBy(int $id, ?int $userId): void;

  /**
   * Helpers for dynamic collections / relations
   */
  public function pluckIdsByProjectTypeAndValues(
    int $projectId,
    int $dataTypeId,
    array $values
  ): array;

  public function pluckIdsForProjectTypeExcluding(
    int $projectId,
    int $dataTypeId,
    array $excludedEntryIds
  ): array;

  /**
   * @return int[]
   */
  public function pluckIdsForProject(int $projectId): array;


  public function updateRatingStats(int $id, array $data): void;

  public function getRatingStats(int $id): array;
}
