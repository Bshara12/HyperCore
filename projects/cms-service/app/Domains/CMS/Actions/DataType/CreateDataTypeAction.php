<?php

namespace App\Domains\CMS\Actions\DataType;

use App\Domains\CMS\DTOs\DataType\CreateDataTypeDTO;
use App\Domains\CMS\Repositories\Interface\DataTypeRepositoryInterface;
use App\Domains\Core\Actions\Action;

class CreateDataTypeAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataType.create';
  }

  public function __construct(
    protected DataTypeRepositoryInterface $repository
  ) {}

  public function execute(CreateDataTypeDTO $dto)
  {
    return $this->run(function () use ($dto) {
      $this->repository->ensureSlugIsUnique(
        projectId: $dto->project_id,
        slug: $dto->slug
      );

      $dataType = $this->repository->create($dto);

      return $dataType;
    });
  }
}
