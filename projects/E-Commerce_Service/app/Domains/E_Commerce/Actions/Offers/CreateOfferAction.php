<?php

namespace App\Domains\E_Commerce\Actions\Offers;

use App\Domains\E_Commerce\Support\CacheKeys;
use App\Domains\Core\Actions\Action;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use App\Services\CMS\CMSApiClient;
use Illuminate\Support\Facades\Cache;

class CreateOfferAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'offer.create';
  }

  public function __construct(
    protected CMSApiClient $cms,
    protected OfferRepositoryInterface $repository
  ) {}

  public function execute($dto)
  {
    return $this->run(function () use ($dto) {

      $response = $this->cms->createCollection($dto->CollectionToArray());

      if (!isset($response['data'])) {
        throw new \Exception("Failed to create collection in CMS");
      }

      $collection = $response['data'];
      $offer = $this->repository->create($collection['id'], $dto->OfferToArray());

      Cache::forget(CacheKeys::offers($dto->project_id));

      return [
        'collection' => $collection,
        'offer'      => $offer,
      ];
    });
  }
}
