<?php

namespace App\Domains\E_Commerce\Actions\Offers;

use App\Domains\Core\Actions\Action;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;

class UpdateOfferAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'offer.updateOffr';
  }

  public function __construct(
    protected OfferRepositoryInterface $repository
  ) {}

  public function execute(string $collectionId, $dto)
  {
    return $this->run(function () use ($collectionId, $dto) {
      return $this->repository->update($collectionId, $dto->offerData);
    });
  }
}
