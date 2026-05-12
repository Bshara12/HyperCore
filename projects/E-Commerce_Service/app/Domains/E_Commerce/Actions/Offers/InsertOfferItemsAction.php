<?php

namespace App\Domains\E_Commerce\Actions\Offers;

use App\Domains\Core\Actions\Action;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use App\Domains\E_Commerce\Support\CacheKeys;
use App\Events\SystemLogEvent;
use App\Services\CMS\CMSApiClient;
use Illuminate\Support\Facades\Cache;

class InsertOfferItemsAction extends Action
{
    protected function circuitServiceName(): string
    {
        return 'offer.insertItems';
    }

    public function __construct(
        protected CMSApiClient $cms,
        protected OfferRepositoryInterface $repository
    ) {}

    public function execute($dto)
    {
        return $this->run(function () use ($dto) {

            $message = $this->cms->addCollectionItems($dto->collectionSlug, $dto->items);

            if ($message === 'Items added successfully') {

                $collection = $this->cms->getCollectionBySlug($dto->collectionSlug);
                $offer = $this->repository->findByCollectionId($collection['id']);

                Cache::forget(CacheKeys::offer($collection['id']));
                Cache::forget(CacheKeys::offerBySlug($dto->collectionSlug));

                event(new SystemLogEvent(
                    module: 'ecommerce',
                    eventType: 'isert_offer_item',
                    userId: null,
                    entityType: 'offer',
                    entityId: $collection['id'] ?? null
                ));

                return [
                    'message' => 'Items added successfully',
                    'collection' => $collection,
                    'offer' => $offer,
                ];
            }
        });
    }
}
