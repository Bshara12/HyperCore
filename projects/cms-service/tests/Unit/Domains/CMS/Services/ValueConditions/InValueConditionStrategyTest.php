<?php

use App\Domains\CMS\Services\ValueConditions\InValueConditionStrategy;
use App\Domains\CMS\Repositories\Interface\DataEntryValueRepository;

beforeEach(function () {
  $this->repository = Mockery::mock(DataEntryValueRepository::class);
  $this->strategy = new InValueConditionStrategy($this->repository);
});

test('it passes the correct parameters and casts input to array', function () {
  $field = 'category_id';
  $value = '1'; // قيمة نصية ستتحول إلى ['1']
  $expectedIds = [10, 20];

  // التحقق من أن الاستراتيجية تقوم بتحويل القيمة إلى مصفوفة قبل تمريرها
  $this->repository->shouldReceive('pluckEntryIdsByFieldIn')
    ->once()
    ->with($field, ['1'])
    ->andReturn($expectedIds);

  $result = $this->strategy->apply($field, $value, 1, 1);

  expect($result)->toBe($expectedIds);
});

test('it handles array input without extra casting issues', function () {
  $field = 'status';
  $value = ['active', 'pending'];
  $expectedIds = [5, 6];

  // التحقق من أن المصفوفة تمر كما هي
  $this->repository->shouldReceive('pluckEntryIdsByFieldIn')
    ->once()
    ->with($field, $value)
    ->andReturn($expectedIds);

  $result = $this->strategy->apply($field, $value, 1, 1);

  expect($result)->toBe($expectedIds);
});
