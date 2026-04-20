<?php

namespace App\Domains\CMS\Read\Repositories;

interface EntryProjectReadRepositoryInterface
{
  public function queryByProject(int $projectId, array $filters);
}
