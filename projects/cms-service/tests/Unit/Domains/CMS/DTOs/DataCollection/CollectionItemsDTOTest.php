<?php

use App\Domains\CMS\DTOs\DataCollection\CollectionItemsDTO;
use App\Domains\CMS\Requests\InsertCollectionItemsRequest;
use App\Domains\CMS\Requests\RemoveCollectionItemsRequest;
use App\Domains\CMS\Requests\ReOrderCollectionItemsRequest;

test('it creates DTO from InsertCollectionItemsRequest', function () {
  $request = Mockery::mock(InsertCollectionItemsRequest::class);
  $request->shouldReceive('validated')->once()->andReturn(['items' => [1, 2, 3]]);

  $dto = CollectionItemsDTO::fromInsertRequest('my-collection', $request);

  expect($dto->collectionSlug)->toBe('my-collection')
    ->and($dto->items)->toBe([1, 2, 3]);
});

test('it creates DTO from RemoveCollectionItemsRequest', function () {
  $request = Mockery::mock(RemoveCollectionItemsRequest::class);
  $request->shouldReceive('validated')->once()->andReturn(['items' => [4, 5]]);

  $dto = CollectionItemsDTO::fromRemoveRequest('my-collection', $request);

  expect($dto->collectionSlug)->toBe('my-collection')
    ->and($dto->items)->toBe([4, 5]);
});

test('it creates DTO from ReOrderCollectionItemsRequest', function () {
  $request = Mockery::mock(ReOrderCollectionItemsRequest::class);
  $request->shouldReceive('validated')->once()->andReturn(['items' => [3, 2, 1]]);

  $dto = CollectionItemsDTO::fromReOrderRequest('my-collection', $request);

  expect($dto->collectionSlug)->toBe('my-collection')
    ->and($dto->items)->toBe([3, 2, 1]);
});
