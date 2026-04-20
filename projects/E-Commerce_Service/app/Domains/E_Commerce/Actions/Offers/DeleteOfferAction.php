<?php

namespace App\Domains\E_Commerce\Actions\Offers;

use App\Domains\Core\Actions\Action;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use App\Services\CMS\CMSApiClient;

class DeleteOfferAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'offer.delete';
  }

  public function __construct(
    protected OfferRepositoryInterface $repository,
    protected CMSApiClient $cms
  ) {}

  public function execute(string $collectionSlug)
  {
    return $this->run(function () use ($collectionSlug) {
      $collection = $this->cms->getCollectionBySlug($collectionSlug);
      $this->repository->deleteOfferByCollectionId($collection['id']);
      // $this->cms->deactivationCollection($collectionSlug, false);
    });
  }
}
