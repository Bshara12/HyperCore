<?php

namespace App\Domains\Booking\Read\Actions;

use App\Domains\Booking\Repositories\Interface\ResourceRepositoryInterface;
use App\Domains\Core\Actions\Action;
use App\Models\Resource;

class ShowResourceAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'resource.show';
  }

  public function __construct(
    private readonly ResourceRepositoryInterface $repository,
  ) {}

  public function execute(int $id): ?Resource
  {
    return $this->run(function () use ($id) {
      return $this->repository->findById($id);
    });
  }
}
