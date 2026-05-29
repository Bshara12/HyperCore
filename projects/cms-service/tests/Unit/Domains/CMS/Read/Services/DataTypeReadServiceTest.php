<?php

namespace Tests\Unit\Domains\CMS\Read\Services;

use App\Domains\CMS\Read\Actions\DataType\IndexDataTypeAction;
use App\Domains\CMS\Read\Actions\DataType\IndexTrashedDataType;
use App\Domains\CMS\Read\Actions\DataType\ShowDataTypeAction;
use App\Domains\CMS\Read\DTOs\DataType\ShowDataTypeDTOProperities;
use App\Domains\CMS\Read\Services\DataTypeReadService;
use Mockery;

beforeEach(function () {
  // إنشاء Mocks للأكشنات الثلاثة
  $this->showAction = Mockery::mock(ShowDataTypeAction::class);
  $this->indexAction = Mockery::mock(IndexDataTypeAction::class);
  $this->indexTrashedAction = Mockery::mock(IndexTrashedDataType::class);

  // حقنها في الخدمة
  $this->service = new DataTypeReadService(
    $this->showAction,
    $this->indexAction,
    $this->indexTrashedAction
  );
});

afterEach(function () {
  Mockery::close();
});

test('it finds a data type by slug using the provided DTO', function () {
  // إنشاء DTO بسيط مباشرة دون الحاجة لـ fromRequest التي تستدعي قاعدة البيانات
  $dto = new ShowDataTypeDTOProperities(project_id: 1, slug: 'test-slug');
  $expectedResult = ['id' => 1, 'name' => 'Test Data Type'];

  $this->showAction->shouldReceive('execute')
    ->once()
    ->with($dto)
    ->andReturn($expectedResult);

  $result = $this->service->findBySlug($dto);

  expect($result)->toBe($expectedResult);
});

test('it lists data types based on the current project from app container', function () {
  // محاكاة الكائن الذي يتم استدعاؤه بـ app('currentProject')
  $mockProject = (object) ['id' => 77];
  app()->instance('currentProject', $mockProject);

  $expectedResult = ['type1', 'type2'];

  $this->indexAction->shouldReceive('execute')
    ->once()
    ->with(77) // التأكد من تمرير الـ ID المستخرج من الكائن المحاكي
    ->andReturn($expectedResult);

  $result = $this->service->list();

  expect($result)->toBe($expectedResult);
});

test('it lists trashed data types for a given project id', function () {
  $projectId = 99;
  $expectedResult = ['trashed_type_1'];

  $this->indexTrashedAction->shouldReceive('execute')
    ->once()
    ->with($projectId)
    ->andReturn($expectedResult);

  $result = $this->service->trashed($projectId);

  expect($result)->toBe($expectedResult);
});
