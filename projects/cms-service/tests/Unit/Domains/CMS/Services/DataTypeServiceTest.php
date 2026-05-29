<?php

use App\Domains\CMS\Actions\DataType\CreateDataTypeAction;
use App\Domains\CMS\Actions\DataType\DeleteDataTypeAction;
use App\Domains\CMS\Actions\DataType\ForceDeleteAction;
use App\Domains\CMS\Actions\DataType\RestoreDataTypeAction;
use App\Domains\CMS\Actions\DataType\UpdateDataTypeAction;
use App\Domains\CMS\DTOs\DataType\CreateDataTypeDTO;
use App\Domains\CMS\DTOs\DataType\UpdateDataTypeDTO;
use App\Domains\CMS\Repositories\Interface\DataTypeRepositoryInterface;
use App\Domains\CMS\Services\DataTypeService;
use App\Models\DataType;

beforeEach(function () {
  // تجهيز الـ Mocks لكافة الـ Actions والـ Repository
  $this->createAction      = Mockery::mock(CreateDataTypeAction::class);
  $this->updateAction      = Mockery::mock(UpdateDataTypeAction::class);
  $this->deleteAction      = Mockery::mock(DeleteDataTypeAction::class);
  $this->restoreAction     = Mockery::mock(RestoreDataTypeAction::class);
  $this->forceDeleteAction = Mockery::mock(ForceDeleteAction::class);
  $this->repository        = Mockery::mock(DataTypeRepositoryInterface::class);

  // حقن الـ Mocks داخل الخدمة
  $this->service = new DataTypeService(
    $this->createAction,
    $this->updateAction,
    $this->deleteAction,
    $this->restoreAction,
    $this->forceDeleteAction,
    $this->repository
  );
});

afterEach(function () {
  Mockery::close();
});

test('it creates a new data type', function () {
  $dto = new CreateDataTypeDTO(project_id: 1, name: 'Test Type', slug: 'test-type');
  $mockedType = (new DataType())->forceFill(['id' => 1]);

  $this->createAction->shouldReceive('execute')
    ->once()
    ->with($dto)
    ->andReturn($mockedType);

  $result = $this->service->create($dto);
  expect($result->id)->toBe(1);
});

test('it updates an existing data type', function () {
  $dataType = (new DataType())->forceFill(['id' => 1]);
  $dto = new UpdateDataTypeDTO(name: 'Updated Name', slug: 'updated-name');

  $this->updateAction->shouldReceive('execute')
    ->once()
    ->with($dataType, $dto)
    ->andReturn($dataType);

  $result = $this->service->update($dataType, $dto);
  expect($result)->toBe($dataType);
});

test('it deletes a data type', function () {
  $dataType = (new DataType())->forceFill(['id' => 1]);

  $this->deleteAction->shouldReceive('execute')
    ->once()
    ->with($dataType);

  $this->service->delete($dataType);
});

test('it restores a deleted data type', function () {
  $this->restoreAction->shouldReceive('execute')
    ->once()
    ->with(1);

  $this->service->restore(1);
});

test('it force deletes a data type', function () {
  $this->forceDeleteAction->shouldReceive('execute')
    ->once()
    ->with(1);

  $this->service->forceDelete(1);
});
