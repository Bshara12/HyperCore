<?php

namespace App\Domains\E_Commerce\Actions\Offers;

use App\Domains\Core\Actions\Action;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use App\Events\SystemLogEvent;
use App\Services\CMS\CMSApiClient;

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
      // $this->repository->deleteOfferByCollectionId($collection['id']);
      // $this->cms->deactivationCollection($dto->collectionSlug, $dto->is_active);

      event(new SystemLogEvent(
        module: 'ecommerce',
        eventType: 'deactive_offer',
        userId: null,
        entityType: 'offer',
        entityId: $collection['id']??null
      ));
    });
  }
}
