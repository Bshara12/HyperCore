<?php

namespace App\Domains\E_Commerce\Actions\Offers;

use App\Domains\Core\Actions\Action;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use App\Services\CMS\CMSApiClient;

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

  public function execute($dto)
  {
    $this->run(function () use ($dto) {
      $collectionId = $this->cms->getCollectionBySlug($dto->collectionSlug)['id'];

      // return $this->repository->subscribe($collectionId, $dto);
      $this->repository->subscribe($collectionId, $dto);
    });
  }
}
