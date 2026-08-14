<?php

namespace App\Domains\E_Commerce\Actions\Offers;

use App\Domains\Core\Actions\Action;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use App\Domains\E_Commerce\Support\CacheKeys;
use App\Services\CMS\CMSApiClient;
use Illuminate\Support\Facades\Cache;

class ActivateOfferAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'offer.activate';
  }

  public function __construct(
    protected OfferRepositoryInterface $repository,
    protected CMSApiClient $cms
  ) {}

  public function execute($dto)
  {
    return $this->run(function () use ($dto) {

      $collection = $this->cms->getCollectionBySlug($dto->collectionSlug);
      $this->repository->activateOffer($collection['id']);

      // 🟢 استخدام Cache::tags(['offers']) للوصول للـ Keyspace الصحيح
      $cache = Cache::tags(['offers']);

      $cache->forget(CacheKeys::offer($collection['id']));
      $cache->forget(CacheKeys::offerBySlug($dto->collectionSlug));
      
      // إبطال كاش قائمة عروض المشروع لأن تفعيل عرض يغير قائمة العروض النشطة
      if (isset($collection['project_id'])) {
        $cache->forget(CacheKeys::offers($collection['project_id']));
      } elseif (isset($dto->projectId)) {
        $cache->forget(CacheKeys::offers($dto->projectId));
      }

      return $collection;
    });
  }
}
