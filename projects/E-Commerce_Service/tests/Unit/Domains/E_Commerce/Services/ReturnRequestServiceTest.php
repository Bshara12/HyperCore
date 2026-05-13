<?php

namespace Tests\Unit\Domains\E_Commerce\Services;

use App\Domains\E_Commerce\Services\ReturnRequestService;
use App\Domains\E_Commerce\Actions\ReturnRequest\CreateReturnRequestAction;
use App\Domains\E_Commerce\Actions\ReturnRequest\GetReturnRequestsAction;
use App\Domains\E_Commerce\Actions\ReturnRequest\UpdateReturnRequestAction;
use App\Domains\E_Commerce\DTOs\ReturnRequest\CreateReturnRequestDTO;
use App\Domains\E_Commerce\DTOs\ReturnRequest\GetReturnRequestsDTO;
use App\Domains\E_Commerce\DTOs\ReturnRequest\UpdateReturnRequestDTO;
use Mockery;

beforeEach(function () {
  $this->createAction = Mockery::mock(CreateReturnRequestAction::class);
  $this->updateAction = Mockery::mock(UpdateReturnRequestAction::class);
  $this->getAction = Mockery::mock(GetReturnRequestsAction::class);

  $this->service = new ReturnRequestService(
    $this->createAction,
    $this->updateAction,
    $this->getAction
  );
});

afterEach(function () {
  Mockery::close();
});

it('calls create action with correct DTO', function () {
  $dto = Mockery::mock(CreateReturnRequestDTO::class);
  $this->createAction->shouldReceive('execute')
    ->once()
    ->with($dto)
    ->andReturn(['id' => 1]);

  $result = $this->service->create($dto);

  expect($result)->toBe(['id' => 1]);
});

it('calls update action with correct DTO', function () {
  $dto = Mockery::mock(UpdateReturnRequestDTO::class);
  $this->updateAction->shouldReceive('execute')
    ->once()
    ->with($dto)
    ->andReturn(true);

  $result = $this->service->update($dto);

  expect($result)->toBeTrue();
});

it('calls getAll action with correct DTO', function () {
  $dto = Mockery::mock(GetReturnRequestsDTO::class);
  $this->getAction->shouldReceive('execute')
    ->once()
    ->with($dto)
    ->andReturn(['data' => []]);

  $result = $this->service->getAll($dto);

  expect($result)->toBe(['data' => []]);
});
