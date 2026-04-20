<?php

namespace App\Domains\E_Commerce\Actions\Offers;

use App\Domains\Core\Actions\Action;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferPriceRepositoryInterface;

class DeleteOfferPricesAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'offer.deletePrices';
  }

  public function __construct(
    protected OfferPriceRepositoryInterface $repository
  ) {}

  public function execute(int $offerId)
  {
    return $this->run(function () use ($offerId) {
      $this->repository->deleteOfferPricesForOffer($offerId);
    });
  }
}
