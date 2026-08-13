<?php

namespace App\Domains\E_Commerce\Actions\Offers;

use App\Domains\Core\Actions\Action;
use App\Domains\E_Commerce\DTOs\Offers\SubscribeDTO;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use App\Domains\E_Commerce\Support\CacheKeys;
use App\Services\CMS\CMSApiClient;
use Illuminate\Support\Facades\Cache;

class SubscribeAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'offer.subscribe';
  }

  public function __construct(
    protected OfferRepositoryInterface $repository,
    protected CMSApiClient $cms
  ) {}

  public function execute(SubscribeDTO $dto)
  {
    $this->run(function () use ($dto) {
      $collection = $this->cms->getCollectionBySlug($dto->collectionSlug);
      $collectionId = $collection['id'];

      // return $this->repository->subscribe($collectionId, $dto);
      $this->repository->subscribe($collectionId, $dto);

      // 🟢 إبطال كاش العرض المعتمد على الـ ID والـ Slug داخل Tag العروض
      $cache = Cache::tags(['offers']);

      $cache->forget(CacheKeys::offer($collectionId));
      $cache->forget(CacheKeys::offerBySlug($dto->collectionSlug));

      if (isset($collection['project_id'])) {
        $cache->forget(CacheKeys::offers($collection['project_id']));
      } elseif (isset($dto->project_id)) {
        $cache->forget(CacheKeys::offers($dto->project_id));
      }
    });
  }
}