<?php

namespace App\Domains\E_Commerce\Actions\Offers;

use App\Domains\E_Commerce\Support\CacheKeys;
use App\Domains\Core\Actions\Action;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use App\Events\SystemLogEvent;
use Illuminate\Support\Facades\Cache;

class UpdateOfferAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'offer.updateOffer';
  }

  public function __construct(
    protected OfferRepositoryInterface $repository
  ) {}

  public function execute(string $collectionId, $dto)
  {
    return $this->run(function () use ($collectionId, $dto) {

      $offer = $this->repository->update($collectionId, $dto->offerData);

      Cache::forget(CacheKeys::offer((int) $collectionId));
      Cache::forget(CacheKeys::offers($offer->project_id));
      event(new SystemLogEvent(
        module: 'ecommerce',
        eventType: 'update_offer',
        userId: null,
        entityType: 'offer',
        entityId: $offer->id ?? null
      ));
      return $offer;
    });
  }
}
