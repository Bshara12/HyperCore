<?php

namespace App\Domains\E_Commerce\Actions\Offers;

use App\Domains\Core\Actions\Action;
use App\Domains\E_Commerce\DTOs\Offers\UpdateOfferDTO;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use App\Domains\E_Commerce\Support\CacheKeys;
use App\Services\CMS\CMSApiClient;
use Illuminate\Support\Facades\Cache;

class UpdateCollectionAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'offer.updateCollcetion';
  }

  public function __construct(
    protected CMSApiClient $cms,
    protected OfferRepositoryInterface $repository
  ) {}

  public function execute(UpdateOfferDTO $dto)
  {
    return $this->run(function () use ($dto) {
      $updatedCollection = $this->cms->updateCollection($dto->collectionSlug, $dto->collectionData);

      // 🟢 تفريغ الكاش المتعلق بالـ Collection والعروض
      $cache = Cache::tags(['offers']);

      // تفريغ الكاش عبر الـ Slug القديم/الحالي
      if (isset($dto->collectionSlug)) {
        $cache->forget(CacheKeys::offerBySlug($dto->collectionSlug));
      }

      // تفريغ الكاش بالـ ID والـ Slug الجديد والمشروع من الاستجابة
      if (is_array($updatedCollection) && isset($updatedCollection['id'])) {
        $cache->forget(CacheKeys::offer($updatedCollection['id']));

        if (isset($updatedCollection['slug'])) {
          $cache->forget(CacheKeys::offerBySlug($updatedCollection['slug']));
        }

        if (isset($updatedCollection['project_id'])) {
          $cache->forget(CacheKeys::offers($updatedCollection['project_id']));
        }
      } elseif (isset($dto->projectId)) {
        $cache->forget(CacheKeys::offers($dto->projectId));
      }

      return $updatedCollection;
    });
  }
}
