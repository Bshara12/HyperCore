<?php

namespace App\Domains\Booking\Actions;

use App\Domains\Booking\DTOs\ResourceDTO;
use App\Domains\Booking\Repositories\Interface\ResourceRepositoryInterface;
use App\Domains\Core\Actions\Action;
use App\Models\Resource;

class CreateResourceAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'resource.create';
  }

  public function __construct(
    private readonly ResourceRepositoryInterface $repository,
  ) {}

  public function execute(ResourceDTO $dto): Resource
  {
    return $this->run(function () use ($dto) {
      return $this->repository->create($dto);
    });
  }
}
