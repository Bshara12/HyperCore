<?php

use App\Domains\CMS\Actions\Field\CreateFieldAction;
use App\Domains\CMS\Actions\Field\DeleteFieldAction;
use App\Domains\CMS\Actions\Field\ForceDeleteAction;
use App\Domains\CMS\Actions\Field\RestoreFieldAction;
use App\Domains\CMS\Actions\Field\UpdateFieldAction;
use App\Domains\CMS\DTOs\Field\CreateFieldDTO;
use App\Domains\CMS\Services\FieldService;
use App\Models\DataTypeField;

beforeEach(function () {
  // إنشاء Mocks لجميع الـ Actions التي يعتمد عليها الـ Service
  $this->createAction = Mockery::mock(CreateFieldAction::class);
  $this->updateAction = Mockery::mock(UpdateFieldAction::class);
  $this->deleteAction = Mockery::mock(DeleteFieldAction::class);
  $this->restoreAction = Mockery::mock(RestoreFieldAction::class);
  $this->forceDeleteAction = Mockery::mock(ForceDeleteAction::class);

  $this->service = new FieldService(
    $this->createAction,
    $this->updateAction,
    $this->deleteAction,
    $this->restoreAction,
    $this->forceDeleteAction
  );
});

afterEach(function () {
  Mockery::close();
});

test('it calls create action', function () {
  $dto = Mockery::mock(CreateFieldDTO::class);
  $field = new DataTypeField(); // إنشاء كائن وهمي للإرجاع

  $this->createAction->shouldReceive('execute')
    ->once()
    ->with($dto)
    ->andReturn($field); // إرجاع الكائن بدلاً من true

  $result = $this->service->create($dto);

  expect($result)->toBe($field); // التحقق من أن النتيجة هي نفس الكائن
});

test('it calls update action', function () {
  $field = new DataTypeField();
  $dto = Mockery::mock(CreateFieldDTO::class);
  $updatedField = new DataTypeField(); // كائن يمثل النتيجة المحدثة

  $this->updateAction->shouldReceive('execute')
    ->once()
    ->with($field, $dto)
    ->andReturn($updatedField); // إرجاع الكائن

  $result = $this->service->update($field, $dto);

  expect($result)->toBe($updatedField);
});

test('it calls destroy action', function () {
  $field = new DataTypeField();
  $this->deleteAction->shouldReceive('execute')->once()->with($field);

  $this->service->destroy($field);
});

test('it calls restore action', function () {
  $this->restoreAction->shouldReceive('execute')->once()->with(1);
  $this->service->restore(1);
});

test('it calls forceDelete action', function () {
  $this->forceDeleteAction->shouldReceive('execute')->once()->with(1);
  $this->service->forceDelete(1);
});
