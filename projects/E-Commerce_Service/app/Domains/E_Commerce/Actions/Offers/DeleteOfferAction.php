<?php

namespace App\Domains\E_Commerce\Actions\Offers;

use App\Domains\Core\Actions\Action;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use App\Domains\E_Commerce\Support\CacheKeys;
use App\Services\CMS\CMSApiClient;
use Illuminate\Support\Facades\Cache;

class DeleteOfferAction extends Action
{
    protected function circuitServiceName(): string
    {
        return 'offer.delete';
    }

    public function __construct(
        protected OfferRepositoryInterface $repository,
        protected CMSApiClient $cms
    ) {}

    public function execute(string $collectionSlug)
    {
        return $this->run(function () use ($collectionSlug) {

            $collection = $this->cms->getCollectionBySlug($collectionSlug);
            $offer = $this->repository->findByCollectionId($collection['id']);

            $this->repository->deleteOfferByCollectionId($collection['id']);

            Cache::forget(CacheKeys::offer($collection['id']));
            Cache::forget(CacheKeys::offerBySlug($collectionSlug));
            Cache::forget(CacheKeys::offers($offer->project_id));
        });
    }
}
