<?php

namespace App\Domains\CMS\Read\Actions\DataCollection;

use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Domains\Core\Actions\Action;
use Illuminate\Support\Facades\Cache;

class ShowDataCollectionDetailsAction extends Action
{
    protected function circuitServiceName(): string
    {
        return 'dataCollection.showDetails';
    }

    public function __construct(
        protected DataCollectionRepositoryInterface $repository,
        protected ProjectRepositoryInterface $projectRepository
    ) {}

    public function execute(string $projectKey, string $collectionSlug, bool $includeInactive = false)
    {
        return $this->run(function () use ($projectKey, $collectionSlug, $includeInactive) {

            $projectId = $this->projectRepository->findByKey($projectKey)->id;

            return Cache::remember(
                CacheKeys::collection($projectId, $collectionSlug, $includeInactive),
                CacheKeys::TTL_MEDIUM,
                function () use ($projectId, $collectionSlug, $includeInactive) {
                    $collection = $this->repository->find($projectId, $collectionSlug, $includeInactive);

                    // A missing collection is a null the controller turns into a
                    // 404; reaching for ->id here made it a 500 instead.
                    if (! $collection) {
                        return null;
                    }

                    // setRelation, not $collection['items']: items is a hasMany,
                    // not a column, so assigning it as an attribute shadowed the
                    // relation and relied on a bogus array cast to serialise.
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
