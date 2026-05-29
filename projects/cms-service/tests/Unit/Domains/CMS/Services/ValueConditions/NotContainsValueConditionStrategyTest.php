<?php

use App\Domains\CMS\Services\ValueConditions\NotContainsValueConditionStrategy;
use App\Domains\CMS\Repositories\Interface\DataEntryValueRepository;
use App\Domains\CMS\Repositories\Interface\DataEntryRepositoryInterface;

beforeEach(function () {
  // محاكاة كلا المستودعين
  $this->valueRepo = Mockery::mock(DataEntryValueRepository::class);
  $this->entryRepo = Mockery::mock(DataEntryRepositoryInterface::class);

  $this->strategy = new NotContainsValueConditionStrategy(
    $this->valueRepo,
    $this->entryRepo
  );
});

test('it identifies bad ids and excludes them from the project entries', function () {
  $field = 'description';
  $value = 'archived';
  $projectId = 10;
  $dataTypeId = 5;

  $badIds = [1, 2]; // المعرفات التي تحتوي على القيمة
  $remainingIds = [3, 4, 5]; // المعرفات التي لا تحتوي على القيمة

  // 1. نتوقع استدعاء البحث عن الـ badIds (مع علامات النسبة المئوية)
  $this->valueRepo->shouldReceive('pluckEntryIdsByFieldLike')
    ->once()
    ->with($field, '%archived%')
    ->andReturn($badIds);

  // 2. نتوقع استدعاء الـ entryRepo مع تمرير الـ badIds للاستبعاد
  $this->entryRepo->shouldReceive('pluckIdsForProjectTypeExcluding')
    ->once()
    ->with($projectId, $dataTypeId, $badIds)
    ->andReturn($remainingIds);

  $result = $this->strategy->apply($field, $value, $projectId, $dataTypeId);

  expect($result)->toBe($remainingIds);
});

test('it handles cases where no matches are found', function () {
  // إذا لم يجد البحث أي نتائج، يجب تمرير مصفوفة فارغة للاستبعاد
  $this->valueRepo->shouldReceive('pluckEntryIdsByFieldLike')
    ->andReturn([]);

  $this->entryRepo->shouldReceive('pluckIdsForProjectTypeExcluding')
    ->with(1, 1, [])
    ->andReturn([1, 2, 3]);

  $result = $this->strategy->apply('field', 'nothing', 1, 1);

  expect($result)->toBe([1, 2, 3]);
});
