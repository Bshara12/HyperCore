<?php

use App\Domains\CMS\Services\EntryHierarchy\EntryComposite;

test('it can be initialized with an id', function () {
  $composite = new EntryComposite(1);

  expect($composite->getId())->toBe(1);
});

test('it can add children and retrieve them', function () {
  $parent = new EntryComposite(1);
  $child = new EntryComposite(2);

  $parent->addChild($child);

  expect($parent->getChildren())->toHaveCount(1)
    ->and($parent->getChildren()[0]->getId())->toBe(2);
});

test('it can flatten a nested hierarchy', function () {
  // بناء الشجرة:
  // 1 -> [2, 4]
  // 2 -> [3]

  $root = new EntryComposite(1);
  $childA = new EntryComposite(2);
  $grandChild = new EntryComposite(3);
  $childB = new EntryComposite(4);

  $childA->addChild($grandChild); // 2 -> 3
  $root->addChild($childA);       // 1 -> 2
  $root->addChild($childB);       // 1 -> 4

  // التنفيذ
  $flattened = $root->flatten();

  // التحقق
  // الترتيب المتوقع بناءً على منطق الكود: [Root, ChildA, GrandChild, ChildB]
  expect($flattened)->toHaveCount(4);

  $ids = array_map(fn($item) => $item->getId(), $flattened);
  expect($ids)->toBe([1, 2, 3, 4]);
});

test('it returns only itself when there are no children', function () {
  $item = new EntryComposite(99);

  $flattened = $item->flatten();

  expect($flattened)->toHaveCount(1)
    ->and($flattened[0]->getId())->toBe(99);
});
