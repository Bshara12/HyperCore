<?php

namespace App\Domains\CMS\Read\Repositories;

interface DataTypeRepositoryInterface
{
  public function getIdBySlugAndProject(string $slug, int $projectId): ?int;
}
