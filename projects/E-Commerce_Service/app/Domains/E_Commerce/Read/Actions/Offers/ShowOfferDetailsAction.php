<?php

namespace App\Domains\E_Commerce\Read\Actions\Offers;

use App\Domains\Core\Actions\Action;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use App\Services\CMS\CMSApiClient;

class ShowOfferDetailsAction extends Action
{

  protected function circuitServiceName(): string
  {
    return 'offer.showDetails';
  }

  public function __construct(
    protected CMSApiClient $cms,
    protected OfferRepositoryInterface $repository
  ) {}

  public function execute($collectionSlug)
  {
    return $this->run(function () use ($collectionSlug) {
      $data['collection'] = $this->cms->getCollectionBySlug($collectionSlug);
      $data['offer'] = $this->repository->getOfferDetails($data['collection']['id']);
      return $data;
    });
  }
}
