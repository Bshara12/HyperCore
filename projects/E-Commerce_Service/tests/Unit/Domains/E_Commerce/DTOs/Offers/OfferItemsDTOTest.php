<?php

use App\Domains\E_Commerce\DTOs\Offers\OfferItemsDTO;
use App\Domains\E_Commerce\Requests\InsertOfferItemsRequest;
use App\Domains\E_Commerce\Requests\RemoveOfferItemsRequest;

it('creates a dto from insert request correctly', function () {
  // 1. Arrange
  $slug = 'promo-2026';
  $items = [
    ['id' => 1, 'type' => 'product'],
    ['id' => 2, 'type' => 'category']
  ];

  // نقوم بعمل Mock للـ Request لمحاكاة دالة validated()
  $request = Mockery::mock(InsertOfferItemsRequest::class);
  $request->shouldReceive('validated')->once()->andReturn(['items' => $items]);

  // 2. Act
  $dto = OfferItemsDTO::fromInsertRequest($slug, $request);

  // 3. Assert
  expect($dto->collectionSlug)->toBe($slug)
    ->and($dto->items)->toBe($items);
});

it('creates a dto from remove request correctly', function () {
  // 1. Arrange
  $slug = 'clearance-sale';
  $items = [10, 11, 12]; // معرفات العناصر المراد حذفها مثلاً

  $request = Mockery::mock(RemoveOfferItemsRequest::class);
  $request->shouldReceive('validated')->once()->andReturn(['items' => $items]);

  // 2. Act
  $dto = OfferItemsDTO::fromRemoveRequest($slug, $request);

  // 3. Assert
  expect($dto->collectionSlug)->toBe($slug)
    ->and($dto->items)->toBe($items);
});

it('can be instantiated via constructor', function () {
  $dto = new OfferItemsDTO('test-slug', ['item1']);

  expect($dto->collectionSlug)->toBe('test-slug')
    ->and($dto->items)->toBe(['item1']);
});
