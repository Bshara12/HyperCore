<?php

namespace App\Domains\E_Commerce\Actions\Offers;

use App\Domains\E_Commerce\Support\CacheKeys;
use App\Domains\Core\Actions\Action;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferPriceRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use App\Services\CMS\CMSApiClient;
use Illuminate\Support\Facades\Cache;

class RemoveOfferItemsAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'offer.removeItems';
  }

  public function __construct(
    protected CMSApiClient $cms,
    protected OfferRepositoryInterface $offerRepository,
    protected OfferPriceRepositoryInterface $offerPriceRepository
  ) {}

  public function execute($dto)
  {
    return $this->run(function () use ($dto) {

      $message = $this->cms->removeCollectionItems($dto->collectionSlug, $dto->items);

      if ($message === "Items removed successfully") {

        $collection = $this->cms->getCollectionBySlug($dto->collectionSlug);
        $offer = $this->offerRepository->findByCollectionId($collection['id']);

        foreach ($dto->items as $item) {
          $this->offerPriceRepository->deleteOfferPriceForEntryAndProject($item, $offer->id);
        }

        Cache::forget(CacheKeys::offer($collection['id']));
        Cache::forget(CacheKeys::offerBySlug($dto->collectionSlug));
      }
    });
  }
}
