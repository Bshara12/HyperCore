<?php

namespace App\Domains\CMS\Actions\DataType;

use App\Domains\CMS\Repositories\Interface\DataTypeRepositoryInterface;
use App\Domains\Core\Actions\Action;

class ForceDeleteAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataType.forceDelete';
  }

  public function __construct(
    protected DataTypeRepositoryInterface $repository
  ) {}

  public function execute(int $dataTypeId): void
  {
    $this->run(function () use ($dataTypeId) {
      $this->repository->forceDelete($dataTypeId);
    });
  }
}
