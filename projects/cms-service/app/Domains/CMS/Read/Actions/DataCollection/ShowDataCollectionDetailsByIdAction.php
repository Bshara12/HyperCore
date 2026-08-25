<?php

namespace App\Domains\CMS\Read\Actions\DataCollection;

use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Domains\Core\Actions\Action;
use Illuminate\Support\Facades\Cache;

class ShowDataCollectionDetailsByIdAction extends Action
{
    protected function circuitServiceName(): string
    {
        return 'dataCollection.showDetailsById';
    }

    public function __construct(
        protected DataCollectionRepositoryInterface $repository,
    ) {}

    public function execute(int $collectionId, bool $includeInactive = false)
    {
        return $this->run(function () use ($collectionId, $includeInactive) {

            return Cache::remember(
                CacheKeys::collectionById($collectionId, $includeInactive),
                CacheKeys::TTL_MEDIUM,
                function () use ($collectionId, $includeInactive) {
                    $collection = $this->repository->findById($collectionId, $includeInactive);

                    if (! $collection) {
                        return null;
                    }

                    $collection->setRelation(
                        'items',
                        $this->repository->getCollectionItems($collection->id)
                    );

                    return $collection;
                }
            );
        });
    }
}
