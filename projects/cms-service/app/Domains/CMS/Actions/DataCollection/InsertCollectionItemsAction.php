<?php

namespace App\Domains\CMS\Actions\DataCollection;

use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\CMS\Support\CollectionCache;
use App\Domains\Core\Actions\Action;
use App\Events\SystemLogEvent;

class InsertCollectionItemsAction extends Action
{
    protected function circuitServiceName(): string
    {
        return 'dataCollection.insertItems';
    }

    public function __construct(
        protected DataCollectionRepositoryInterface $repository
    ) {}

    public function execute($dto)
    {
        return $this->run(function () use ($dto) {

            $collection = $this->repository->getBySlug($dto->collectionSlug);
            $this->repository->insertItems($collection->id, $dto->items);

            CollectionCache::forgetContents($collection->project_id, $collection->slug, $collection->id);

            event(new SystemLogEvent(
                module: 'cms',
                eventType: 'add_collection_item',
                userId: null,
                entityType: 'collection',
                entityId: $dto->collectionSlug
            ));
        });
    }
}
