<?php

namespace App\Domains\Booking\Actions;

use App\Domains\Booking\DTOs\ResourceDTO;
use App\Domains\Booking\Repositories\Interface\ResourceRepositoryInterface;
use App\Domains\Core\Actions\Action;
use App\Models\Resource;

class UpdateResourceAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'resource.update';
  }
  
  public function __construct(
    private readonly ResourceRepositoryInterface $repository,
  ) {}

  public function execute(Resource $resource, ResourceDTO $dto): Resource
  {
    return $this->run(function () use ($resource, $dto) {
      return $this->repository->update($resource, $dto);
    });
  }
}
