<?php

namespace App\Domains\Booking\Actions;

use App\Domains\Booking\Repositories\Interface\ResourceRepositoryInterface;
use App\Domains\Core\Actions\Action;
use App\Models\Resource;

class DeleteResourceAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'resource.delete';
  }

  public function __construct(
    private readonly ResourceRepositoryInterface $repository,
  ) {}

  public function execute(Resource $resource)
  {
    $this->run(function () use ($resource) {
      $this->repository->delete($resource);
    });
  }
}
