<?php

namespace App\Domains\Booking\Read\Actions;

use App\Domains\Booking\Repositories\Interface\ResourceRepositoryInterface;
use App\Domains\Core\Actions\Action;

class IndexResourcesAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'resource.index';
  }
  
  public function __construct(
    private readonly ResourceRepositoryInterface $repository,
  ) {}

  public function execute(int $projectId)
  {
    return $this->run(function () use ($projectId) {
      return $this->repository->listByProject($projectId);
    });
  }
}
