<?php

namespace App\Domains\E_Commerce\Services;

use App\Domains\E_Commerce\Actions\ReturnRequest\CreateReturnRequestAction;
use App\Domains\E_Commerce\Actions\ReturnRequest\GetReturnRequestsAction;
use App\Domains\E_Commerce\Actions\ReturnRequest\UpdateReturnRequestAction;
use App\Domains\E_Commerce\DTOs\ReturnRequest\CreateReturnRequestDTO;
use App\Domains\E_Commerce\DTOs\ReturnRequest\GetReturnRequestsDTO;
use App\Domains\E_Commerce\DTOs\ReturnRequest\UpdateReturnRequestDTO;

class ReturnRequestService
{
    public function __construct(
        protected CreateReturnRequestAction $createAction,
        protected UpdateReturnRequestAction $updateAction,
        protected GetReturnRequestsAction $getaction
    ) {}

    public function create(CreateReturnRequestDTO $dto)
    {
        return $this->createAction->execute($dto);
    }

    public function update(UpdateReturnRequestDTO $dto)
    {
        return $this->updateAction->execute($dto);
    }

    public function getAll(GetReturnRequestsDTO $dto)
    {
        return $this->getaction->execute($dto);
    }
}
