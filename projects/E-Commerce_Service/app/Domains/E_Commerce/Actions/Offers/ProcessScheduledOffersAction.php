<?php

namespace App\Domains\E_Commerce\Actions\Offers;

use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use Carbon\Carbon;

class ProcessScheduledOffersAction
{
  public function __construct(
    protected OfferRepositoryInterface $repository
  ) {}

  public function execute(): array
  {
    $now = Carbon::now();

    $activatedOffers = $this->repository->getAndActivateDueOffers($now);
    $deactivatedOffers = $this->repository->getAndDeactivateExpiredOffers($now);

    return [
      'activated' => $activatedOffers,
      'deactivated' => $deactivatedOffers,
    ];
  }
}
