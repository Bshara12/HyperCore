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

      $cache = Cache::tags(['offers']);

      Cache::forget(CacheKeys::offer($collection['id']));
      Cache::forget(CacheKeys::offerBySlug($dto->collectionSlug));

      // @codeCoverageIgnoreStart
      // إبطال كاش قائمة عروض المشروع لأن إلغاء التفعيل يغير قائمة العروض المتاحة
      if (isset($collection['project_id'])) {
        $cache->forget(CacheKeys::offers($collection['project_id']));
      } elseif (isset($dto->projectId)) {
        $cache->forget(CacheKeys::offers($dto->projectId));
      }
      // @codeCoverageIgnoreEnd
      return $collection;
    });
  }
}
