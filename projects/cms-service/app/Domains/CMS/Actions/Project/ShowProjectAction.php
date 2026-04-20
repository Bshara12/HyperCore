<?php

namespace App\Domains\CMS\Actions\Project;

use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\Core\Actions\Action;
use App\Models\Project;

class ShowProjectAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'project.show';
  }

  public function __construct(
    private ProjectRepositoryInterface $repository
  ) {}

  public function execute(Project $project): Project
  {
    return $this->run(function () use ($project) {
      return $this->repository->find($project);
    });
  }
}
