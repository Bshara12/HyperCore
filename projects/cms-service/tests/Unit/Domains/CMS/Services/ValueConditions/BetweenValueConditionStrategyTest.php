<?php

use App\Domains\CMS\Services\ValueConditions\BetweenValueConditionStrategy;
use App\Domains\CMS\Repositories\Interface\DataEntryValueRepository;

beforeEach(function () {
  // 1. نقوم بمحاكاة المستودع لأننا لا نريد الاتصال بقاعدة البيانات
  $this->repository = Mockery::mock(DataEntryValueRepository::class);
  $this->strategy = new BetweenValueConditionStrategy($this->repository);
});

test('it passes the correct parameters to the repository', function () {
  $field = 'price';
  $value = [10, 100]; // النطاق المراد البحث فيه
  $expectedIds = [1, 5, 8];

  // 2. نتوقع أن يتم استدعاء المستودع بالقيم الصحيحة
  $this->repository->shouldReceive('pluckEntryIdsByFieldBetween')
    ->once()
    ->with($field, $value)
    ->andReturn($expectedIds);

  // 3. تنفيذ الدالة (تمرير dummy values لـ projectId و dataTypeId لأنهما غير مستخدمين حالياً في الكود)
  $result = $this->strategy->apply($field, $value, 1, 1);

  expect($result)->toBe($expectedIds);
});

test('it casts non-array values to array before passing to repository', function () {
  $field = 'age';
  $value = '25'; // قيمة غير مصفوفة سيتم تحويلها (array)'25' -> ['25']

  $this->repository->shouldReceive('pluckEntryIdsByFieldBetween')
    ->once()
    ->with($field, ['25']) // التأكد من التحويل
    ->andReturn([1]);

  $this->strategy->apply($field, $value, 1, 1);
});
