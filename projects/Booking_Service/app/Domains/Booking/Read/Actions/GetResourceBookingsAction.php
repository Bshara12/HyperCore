<?php

namespace App\Domains\Booking\Read\Actions;

use App\Domains\Booking\Read\DTOs\GetResourceBookingsDTO;
use App\Domains\Booking\Repositories\Interface\BookingRepositoryInterface;
use App\Domains\Core\Actions\Action;

class GetResourceBookingsAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'resource.getBookings';
  }

  public function __construct(
    private readonly BookingRepositoryInterface $repository,
  ) {}

  public function execute(GetResourceBookingsDTO $dto)
  {
    return $this->run(function () use ($dto) {
      return $this->repository->listByResource(
        resourceId: $dto->resourceId,
        status: $dto->status,
        from: $dto->from,
        to: $dto->to
      );
    });
  }
}
