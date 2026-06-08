<?php

use App\Domains\CMS\Services\ValueConditions\ComparisonValueConditionStrategy;
use App\Domains\CMS\Repositories\Interface\DataEntryValueRepository;

beforeEach(function () {
  $this->repository = Mockery::mock(DataEntryValueRepository::class);
});

test('it passes the correct parameters and operator to the repository', function ($operator) {
  // 1. إنشاء الاستراتيجية بمعامل محدد
  $strategy = new ComparisonValueConditionStrategy($operator, $this->repository);

  $field = 'age';
  $value = 18;
  $expectedIds = [10, 20, 30];

  // 2. التحقق من أن الاستراتيجية تمرر المعامل للـ Repository بشكل صحيح
  $this->repository->shouldReceive('pluckEntryIdsByFieldComparison')
    ->once()
    ->with($field, $operator, $value)
    ->andReturn($expectedIds);

  // 3. التنفيذ
  $result = $strategy->apply($field, $value, 1, 1);

  expect($result)->toBe($expectedIds);
})->with([
  'greater than' => ['>'],
  'less than'    => ['<'],
  'equal'        => ['='],
  'greater or equal' => ['>='],
]);
