<?php

namespace App\Domains\Booking\Actions;

use App\Domains\Booking\DTOs\CancellationPolicyDTO;
use App\Domains\Booking\Repositories\Interface\ResourceRepositoryInterface;
use App\Domains\Core\Actions\Action;
use App\Models\Resource;

class SetCancellationPolicyAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'resource.setPolicy';
  }
  
  public function __construct(
    private readonly ResourceRepositoryInterface $repository,
  ) {}

  public function execute(Resource $resource, array $policies): void
  {
    $dtos = array_map(
      fn(array $item) => CancellationPolicyDTO::fromArray($item, $resource->id),
      $policies
    );

    $this->run(function () use ($resource, $dtos) {
      $this->repository->setPolicies($resource, $dtos);
    });
  }
}
