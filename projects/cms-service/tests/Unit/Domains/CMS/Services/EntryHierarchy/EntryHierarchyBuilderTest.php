<?php

use App\Domains\CMS\Services\EntryHierarchy\EntryHierarchyBuilder;
use App\Domains\CMS\Repositories\Interface\DataEntryRelationRepository;
use App\Domains\CMS\Services\EntryHierarchy\EntryComponent;

beforeEach(function () {
  $this->repository = Mockery::mock(DataEntryRelationRepository::class);
  $this->builder = new EntryHierarchyBuilder($this->repository);
});

test('it builds a simple hierarchy tree', function () {
  // الهيكل: 1 -> [2]
  $this->repository->shouldReceive('pluckEntryIdsWhereRelatedIs')
    ->with(1)->once()->andReturn([2]);
  $this->repository->shouldReceive('pluckEntryIdsWhereRelatedIs')
    ->with(2)->once()->andReturn([]);

  $components = $this->builder->buildFromRootIds([1]);

  expect($components)->toHaveCount(1)
    ->and($components[0]->getId())->toBe(1)
    ->and($components[0]->getChildren())->toHaveCount(1)
    ->and($components[0]->getChildren()[0]->getId())->toBe(2);
});

test('it handles circular dependencies using visited array', function () {
  // الهيكل: 1 -> [2], 2 -> [1]
  $this->repository->shouldReceive('pluckEntryIdsWhereRelatedIs')
    ->with(1)->once()->andReturn([2]);
  $this->repository->shouldReceive('pluckEntryIdsWhereRelatedIs')
    ->with(2)->once()->andReturn([1]);

  $components = $this->builder->buildFromRootIds([1]);

  // 1. تأكد أن node 1 له طفل (node 2)
  $node1 = $components[0];
  $node2 = $node1->getChildren()[0];
  expect($node2->getId())->toBe(2);

  // 2. تأكد أن node 2 له طفل (هو الـ stub node الذي يمثل 1)
  $stubNode1 = $node2->getChildren()[0];
  expect($stubNode1->getId())->toBe(1);

  // 3. تأكد أن الـ stub node هو الذي لا يملك أطفالاً (هنا الاختبار)
  expect($stubNode1->getChildren())->toBeEmpty();
});

test('it flattens ids correctly including unique children', function () {
  // الهيكل: 1 -> [2, 3], 2 -> [4]
  // المتوقع: [1, 2, 4, 3] (أو أي ترتيب بشرط عدم التكرار)
  $this->repository->shouldReceive('pluckEntryIdsWhereRelatedIs')
    ->with(1)->andReturn([2, 3]);
  $this->repository->shouldReceive('pluckEntryIdsWhereRelatedIs')
    ->with(2)->andReturn([4]);
  $this->repository->shouldReceive('pluckEntryIdsWhereRelatedIs')
    ->with(3)->andReturn([]);
  $this->repository->shouldReceive('pluckEntryIdsWhereRelatedIs')
    ->with(4)->andReturn([]);

  $ids = $this->builder->flattenIds([1]);

  expect($ids)->toHaveCount(4)
    ->and($ids)->toContain(1, 2, 3, 4);
});

test('it handles multiple root ids', function () {
  // الهيكل: [1, 5] (جذران منفصلان)
  $this->repository->shouldReceive('pluckEntryIdsWhereRelatedIs')
    ->with(1)->andReturn([]);
  $this->repository->shouldReceive('pluckEntryIdsWhereRelatedIs')
    ->with(5)->andReturn([]);

  $components = $this->builder->buildFromRootIds([1, 5]);

  expect($components)->toHaveCount(2)
    ->and($components[0]->getId())->toBe(1)
    ->and($components[1]->getId())->toBe(5);
});
