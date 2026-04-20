<?php

namespace App\Domains\E_Commerce\Actions\ReturnRequest;

use App\Domains\E_Commerce\DTOs\ReturnRequest\GetReturnRequestsDTO;
use App\Domains\E_Commerce\Repositories\Interfaces\ReturnRequest\ReturnRequestRepositoryInterface;

class GetReturnRequestsAction
{
  public function __construct(
    protected ReturnRequestRepositoryInterface $repo
  ) {}

  public function execute(GetReturnRequestsDTO $dto)
  {
    return $this->repo->getByProject($dto->project_id);
  }
}