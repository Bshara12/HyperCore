<?php

namespace App\Domains\E_Commerce\Actions\Offers;

use App\Domains\Core\Actions\Action;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferPriceRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use App\Domains\E_Commerce\Support\CacheKeys;
use App\Events\SystemLogEvent;
use Illuminate\Support\Facades\Cache;

class DeleteOfferPricesAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'offer.deletePrices';
  }

  public function __construct(
    protected OfferPriceRepositoryInterface $repository,
    protected OfferRepositoryInterface $offerRepository
  ) {}

  public function execute(int $offerId)
  {
    return $this->run(function () use ($offerId) {
      // جلب بيانات العرض لإبطال الكاش الخاص به قبل/بعد الحذف
      $offer = $this->offerRepository->find($offerId);

      $this->repository->deleteOfferPricesForOffer($offerId);

      // 🟢 إبطال كاش العرض باستخدام Cache::tags(['offers'])
      if ($offer) {
        $cache = Cache::tags(['offers']);

        if (isset($offer->collection_id)) {
          $cache->forget(CacheKeys::offer($offer->collection_id));
        }

        if (isset($offer->project_id)) {
          $cache->forget(CacheKeys::offers($offer->project_id));
        }
      }

      event(new SystemLogEvent(
        module: 'ecommerce',
        eventType: 'delete_offer_price',
        userId: null,
        entityType: 'offer',
        entityId: $offerId
      ));
    });
  }
}
