<?php

namespace App\Domains\CMS\Read\Actions\DataType;

use App\Domains\CMS\Read\Repositories\DataType\DataTypeRepositoryRead;
use App\Domains\Core\Actions\Action;

class IndexTrashedDataType extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataType.indexTrashed';
  }

  public function __construct(
    protected DataTypeRepositoryRead $repository
  ) {}

  public function execute(int $projectId)
  {
    return $this->run(function () use ($projectId) {
      return $this->repository->trashed($projectId);
    });
  }
}
