<?php

namespace App\Domains\CMS\Read\Actions\DataType;

use App\Domains\CMS\Read\Repositories\DataType\DataTypeRepositoryRead;
use App\Domains\Core\Actions\Action;

class IndexDataTypeAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataType.index';
  }

  public function __construct(
    protected DataTypeRepositoryRead $repository
  ) {}

  public function execute(int $project_id)
  {
    return $this->run(function () use ($project_id) {
      return $this->repository->list($project_id);
    });
  }
}
