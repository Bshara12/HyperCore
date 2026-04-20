<?php

namespace App\Domains\Auth\Repository\Interface;

interface ProjectUserRepositoryInterface
{
  public function exists(int $userId, string $projectKey): bool;
}
