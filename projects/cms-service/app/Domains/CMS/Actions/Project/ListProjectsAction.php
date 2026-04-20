<?php

namespace App\Domains\CMS\Actions\Project;

use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\Core\Actions\Action;

class ListProjectsAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'project.index';
  }

  public function __construct(
    private ProjectRepositoryInterface $repository
  ) {}

  public function execute(): \Illuminate\Support\Collection
  {
    return $this->run(function () {
      return $this->repository->all();
    });
  }
}
