<?php

use App\Domains\CMS\Services\ValueConditions\InCollectionConditionStrategy;
use App\Domains\CMS\Repositories\Interface\DataEntryValueRepository;

beforeEach(function () {
  $this->repository = Mockery::mock(DataEntryValueRepository::class);
  $this->strategy = new InCollectionConditionStrategy($this->repository);
});

test('it returns an empty array immediately if no collection ids are found', function () {
  $projectId = 1;
  $dataTypeId = 2;
  $value = ['some-value']; // تغيير القيمة هنا لتصبح مصفوفة

  $this->repository->shouldReceive('pluckEntryIdsByFieldInCollection')
    ->once()
    ->with($projectId, $dataTypeId, $value) // سيتطابق الآن مع النوع المتوقع
    ->andReturn([]);

  $this->repository->shouldNotReceive('returnEntryIdsFromCollectionItems');

  $result = $this->strategy->apply('field', $value, $projectId, $dataTypeId);

  expect($result)->toBeEmpty();
});

test('it retrieves entry ids from collection items when collection ids exist', function () {
  $projectId = 1;
  $dataTypeId = 2;
  $value = ['some-value']; // تغيير القيمة هنا أيضاً
  $collectionIds = [10, 20];
  $finalEntryIds = [101, 102, 103];

  $this->repository->shouldReceive('pluckEntryIdsByFieldInCollection')
    ->once()
    ->with($projectId, $dataTypeId, $value)
    ->andReturn($collectionIds);

  $this->repository->shouldReceive('returnEntryIdsFromCollectionItems')
    ->once()
    ->with($collectionIds)
    ->andReturn($finalEntryIds);

  $result = $this->strategy->apply('field', $value, $projectId, $dataTypeId);

  expect($result)->toBe($finalEntryIds);
});
