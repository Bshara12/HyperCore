<?php

namespace App\Domains\CMS\Repositories\Interface;

interface DataEntryValueRepository
{
  public function bulkInsert(
    int $entryId,
    int $dataTypeId,
    array $values
  ): void;

  public function replacePartial(
    int $entryId,
    int $dataTypeId,
    array $values
  ): void;
  public function getForEntry(int $entryId): array;
  public function deleteForEntry(int $entryId): void;

  public function bulkInsertFromSnapshot(int $entryId, array $values): void;

  /**
   * Filtering helpers used by dynamic collections (Strategy pattern)
   */
  public function pluckEntryIdsByFieldComparison(string $field, string $operator, $value): array;

  public function pluckEntryIdsByFieldLike(string $field, string $pattern): array;

  public function pluckEntryIdsByFieldIn(string $field, array $values): array;
  
  public function pluckEntryIdsByFieldInCollection(int $projectId, int $dataTypeId, array $values): array;

  public function returnEntryIdsFromCollectionItems(array $collectionIds): array;

  public function pluckEntryIdsByFieldBetween(string $field, array $values): array;

  /**
   * Same helpers but scoped to a candidate set (performance for Offer Engine)
   *
   * @param int[] $withinEntryIds
   */
  public function pluckEntryIdsByFieldComparisonWithin(string $field, string $operator, $value, array $withinEntryIds): array;

  /**
   * @param int[] $withinEntryIds
   */
  public function pluckEntryIdsByFieldLikeWithin(string $field, string $pattern, array $withinEntryIds): array;

  /**
   * @param int[] $withinEntryIds
   */
  public function pluckEntryIdsByFieldInWithin(string $field, array $values, array $withinEntryIds): array;

  /**
   * @param int[] $withinEntryIds
   */
  public function pluckEntryIdsByFieldBetweenWithin(string $field, array $values, array $withinEntryIds): array;

  /**
   * Read numeric values for a field (e.g. price) keyed by entry_id.
   *
   * @param int[] $entryIds
   * @return array<int, float>
   */
  public function pluckNumericFieldValuesByEntryIds(string $field, array $entryIds): array;
}
