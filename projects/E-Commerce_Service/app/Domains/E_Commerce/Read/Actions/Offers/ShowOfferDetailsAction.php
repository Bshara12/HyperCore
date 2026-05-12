<?php

namespace App\Domains\E_Commerce\Read\Actions\Offers;

use App\Domains\Core\Actions\Action;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use App\Domains\E_Commerce\Support\CacheKeys;
use App\Services\CMS\CMSApiClient;
use Illuminate\Support\Facades\Cache;

class ShowOfferDetailsAction extends Action
{
    protected function circuitServiceName(): string
    {
        return 'offer.showDetails';
    }

    public function __construct(
        protected CMSApiClient $cms,
        protected OfferRepositoryInterface $repository
    ) {}

    public function execute($collectionSlug)
    {
        return $this->run(function () use ($collectionSlug) {

            return Cache::tags(['offers'])->remember(
                CacheKeys::offerBySlug($collectionSlug),
                CacheKeys::TTL_MEDIUM,
                function () use ($collectionSlug) {
                    $collection = $this->cms->getCollectionBySlug($collectionSlug);

                    return [
                        'collection' => $collection,
                        'offer' => $this->repository->getOfferDetails($collection['id']),
                    ];
                }
            );
        });
    }
}
