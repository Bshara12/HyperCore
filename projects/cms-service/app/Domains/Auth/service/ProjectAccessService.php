<?php

namespace App\Domains\Auth\Service;

use App\Domains\Auth\Action\CheckProjectAccessAction;
use App\Domains\Auth\DTOs\CheckProjectAccessDto;

class ProjectAccessService
{
  public function __construct(
    private CheckProjectAccessAction $action
  ) {}

  public function check(CheckProjectAccessDto $dto): bool
  {
    return $this->action->execute($dto);
  }
}
