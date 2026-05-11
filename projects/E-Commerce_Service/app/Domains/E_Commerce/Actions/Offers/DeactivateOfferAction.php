<?php

namespace App\Domains\E_Commerce\Actions\Offers;

use App\Domains\Core\Actions\Action;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use App\Domains\E_Commerce\Support\CacheKeys;
use App\Services\CMS\CMSApiClient;
use Illuminate\Support\Facades\Cache;

class DeactivateOfferAction extends Action
{
    protected function circuitServiceName(): string
    {
        return 'offer.deactivate';
    }

    public function __construct(
        protected OfferRepositoryInterface $repository,
        protected CMSApiClient $cms
    ) {}

    public function execute($dto)
    {
        return $this->run(function () use ($dto) {

            $collection = $this->cms->getCollectionBySlug($dto->collectionSlug);
            $this->repository->deactivateOffer($collection['id']);

            Cache::forget(CacheKeys::offer($collection['id']));
            Cache::forget(CacheKeys::offerBySlug($dto->collectionSlug));

            return $collection;
        });
    }
}
