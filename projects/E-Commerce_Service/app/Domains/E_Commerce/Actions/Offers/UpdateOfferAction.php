<?php

namespace App\Domains\E_Commerce\Actions\Offers;

use App\Domains\Core\Actions\Action;
use App\Domains\E_Commerce\DTOs\Offers\UpdateOfferDTO;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use App\Domains\E_Commerce\Support\CacheKeys;
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

  public function execute(int $collectionId, UpdateOfferDTO $dto)
  {
    return $this->run(function () use ($collectionId, $dto) {

      $offer = $this->repository->update($collectionId, $dto->offerData);

      $cache = Cache::tags(['offers']);

      $cache->forget(CacheKeys::offer((int) $collectionId));

      if (isset($offer->project_id)) {
        $cache->forget(CacheKeys::offers((int) $offer->project_id));
      }

      if ($dto->collectionSlug) {
        $cache->forget(CacheKeys::offerBySlug($dto->collectionSlug));
      }

      event(new SystemLogEvent(
        module: 'ecommerce',
        eventType: 'update_offer',
        userId: null,
        entityType: 'offer',
        entityId: (int) $offer->id
      ));

      return $offer;
    });
  }
}