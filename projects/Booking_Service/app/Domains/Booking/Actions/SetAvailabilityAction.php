<?php

namespace App\Domains\Booking\Actions;

use App\Domains\Booking\DTOs\AvailabilityDTO;
use App\Domains\Booking\Repositories\Interface\ResourceRepositoryInterface;
use App\Domains\Core\Actions\Action;
use App\Models\Resource;

class SetAvailabilityAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'resource.setAvailability';
  }
  
  public function __construct(
    private readonly ResourceRepositoryInterface $repository,
  ) {}

  public function execute(Resource $resource, array $availabilities): void
  {
    $dtos = array_map(
      fn(array $item) => AvailabilityDTO::fromArray($item, $resource->id),
      $availabilities
    );

    $this->run(function () use ($resource, $dtos) {
      $this->repository->setAvailabilities($resource, $dtos);
    });
  }
}
