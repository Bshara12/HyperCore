<?php

namespace App\Domains\CMS\DTOs\Project;

use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Models\Project;

class DeleteProjectAction
{
  public function __construct(
    private ProjectRepositoryInterface $repository
  ) {}

  public function execute(Project $project): void
  {
    $this->repository->delete($project);
  }
}
