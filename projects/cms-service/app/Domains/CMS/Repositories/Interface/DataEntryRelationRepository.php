<?php

namespace App\Domains\CMS\Repositories\Interface;

interface DataEntryRelationRepository
{
  public function insertForEntry(
    int $entryId,
    int $dataTypeId,
    int $projectId,
    array $relations
  ): void;

  public function deleteForEntry(int $entryId): void;

  public function deleteWhereRelatedIs(int $relatedId): void;

  public function getEntriesWhereRelatedIs(int $entryId): array;

  /**
   * Helper methods for dynamic hierarchies / collections
   */
  public function pluckEntryIdsWhereRelatedIs(int $relatedId): array;

  /**
   * @param int[] $relatedIds
   * @return int[]
   */
  public function pluckEntryIdsByRelatedIds(array $relatedIds): array;

  /**
   * @param int[] $relatedIds
   * @param int[] $withinEntryIds
   * @return int[]
   */
  public function pluckEntryIdsByRelatedIdsWithin(array $relatedIds, array $withinEntryIds): array;
}
