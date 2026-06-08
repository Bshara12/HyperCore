<?php

use App\Domains\CMS\Services\ValueConditions\ContainsValueConditionStrategy;
use App\Domains\CMS\Repositories\Interface\DataEntryValueRepository;

beforeEach(function () {
  // محاكاة المستودع
  $this->repository = Mockery::mock(DataEntryValueRepository::class);
  $this->strategy = new ContainsValueConditionStrategy($this->repository);
});

test('it wraps the value in wildcards and passes it to the repository', function () {
  $field = 'description';
  $value = 'search-term';
  $expectedWildcardValue = '%search-term%'; // هذا هو الجزء الأهم للتأكد منه
  $expectedIds = [101, 102];

  // التأكد من أن القيمة أصبحت %search-term% قبل إرسالها
  $this->repository->shouldReceive('pluckEntryIdsByFieldLike')
    ->once()
    ->with($field, $expectedWildcardValue)
    ->andReturn($expectedIds);

  $result = $this->strategy->apply($field, $value, 1, 1);

  expect($result)->toBe($expectedIds);
});

test('it handles empty values by wrapping them in wildcards', function () {
  $field = 'name';
  $value = '';
  $expectedWildcardValue = '%%'; // حالة البحث عن أي قيمة تحتوي على أي شيء (أو فراغ)

  $this->repository->shouldReceive('pluckEntryIdsByFieldLike')
    ->once()
    ->with($field, $expectedWildcardValue)
    ->andReturn([]);

  $this->strategy->apply($field, $value, 1, 1);
});
