<?php

namespace App\Domains\CMS\Actions\DataCollection;

use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\CMS\Services\DynamicCollectionQueryBuilder;

class GenerateDynamicItemsAction
{
  public function __construct(
    protected DataCollectionRepositoryInterface $repository,
    protected DynamicCollectionQueryBuilder $builder
  ) {}

  public function execute($collection)
  {
    // Get items based on collection's dynamic source
    $entries = $this->builder->build($collection);

    // Create collection items
    foreach ($entries as $index => $entry) {
      $this->repository->createDataCollectionItem([
        'collection_id' => $collection->id,
        'item_id' => $entry->id,
        'sort_order' => $index + 1,
      ]);
    }
    return $entries;
  }
}
