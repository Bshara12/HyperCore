<?php

namespace App\Domains\E_Commerce\Read\Actions\Offers;

use App\Domains\Core\Actions\Action;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use App\Services\CMS\CMSApiClient;

class IndexOffersAction extends Action
{

  protected function circuitServiceName(): string
  {
    return 'offer.index';
  }

  public function __construct(
    protected CMSApiClient $cms,
    protected OfferRepositoryInterface $repository
  ) {}

  public function execute($projectId)
  {
    return $this->run(function () use ($projectId) {
      $offers = $this->repository->getProjectOffers($projectId);
      foreach ($offers as $offer) {
        $offer['collection'] = $this->cms->getCollectionById($offer['collection_id']);
      }
      return $offers;
    });
  }
}
