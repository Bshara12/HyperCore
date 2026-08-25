<?php

namespace App\Domains\CMS\Repositories\Interface;

use App\Domains\CMS\DTOs\DataCollection\DeactivateCollectionDTO;
use App\Domains\CMS\DTOs\DataCollection\UpdateDataCollectionDTO;
use App\Models\DataCollection;

interface DataCollectionRepositoryInterface
{
    public function getBySlug(string $slug): ?DataCollection;

    public function create($dto): DataCollection;

    public function createDataCollectionItem(array $data): void;

    public function update(UpdateDataCollectionDTO $dto): DataCollection;

    public function delete(int $collectionId): void;

    public function deleteItems(int $collectionId): void;

    public function list(int $projectId, bool $includeInactive = false);

    public function find(int $projectId, string $slug, bool $includeInactive = false): ?DataCollection;

    public function findById(int $collectionId, bool $includeInactive = false): ?DataCollection;

    public function getCollectionItems(int $collectionId);

    /**
     * Replace every item of a collection atomically.
     *
     * @param  int[]  $entryIds
     */
    public function replaceItems(int $collectionId, array $entryIds): void;

    public function insertItems(int $collectionId, array $items): void;

    public function removeItems(int $collectionId, array $items): void;

    public function reOrderItems(int $collectionId, array $items);

    public function getEntries(int $collectionId);

    public function deactivate(DeactivateCollectionDTO $dto): void;

    /**
     * @return int[] entry ids inside collection
     */
    public function pluckCollectionEntryIds(int $collectionId): array;
}
