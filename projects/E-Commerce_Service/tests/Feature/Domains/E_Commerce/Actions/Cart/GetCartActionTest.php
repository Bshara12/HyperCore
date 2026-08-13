<?php

use App\Domains\E_Commerce\Actions\Cart\GetCartAction;
use App\Domains\E_Commerce\Actions\Pricing\EnrichEntriesWithPricesAction;
use App\Domains\E_Commerce\Actions\Pricing\FetchEntriesByIdsAction;
use App\Domains\E_Commerce\Repositories\Interfaces\Cart\CartRepositoryInterface;
use App\Domains\E_Commerce\Support\StockStatusResolver;
use App\Models\Cart;
use App\Models\CartItem;

it('returns empty structure when cart has no items', function () {
  $projectId = 1;
  $userId = 10;

  $cartRepo = Mockery::mock(CartRepositoryInterface::class);
  $fetchEntries = Mockery::mock(FetchEntriesByIdsAction::class);
  $enrichPrices = Mockery::mock(EnrichEntriesWithPricesAction::class);
  $stockResolver = Mockery::mock(StockStatusResolver::class);

  $mockCart = new Cart(['id' => 1]);
  $mockCart->setRelation('items', collect()); // سلة فارغة

  $cartRepo->shouldReceive('getOrCreate')->once()->andReturn($mockCart);
  $cartRepo->shouldReceive('loadItems')->once()->andReturn($mockCart);

  $action = new GetCartAction($cartRepo, $fetchEntries, $enrichPrices, $stockResolver);
  $result = $action->execute($projectId, $userId);

  expect($result['items'])->toBeEmpty();
  expect($result['total'])->toBe(0);
});

it('enriches cart items with prices and stock status correctly', function () {
  $projectId = 1;
  $userId = 10;

  // 1. إعداد البيانات الوهمية
  $mockCart = new Cart(['id' => 1]);
  $item1 = new CartItem(['id' => 10, 'item_id' => 101, 'quantity' => 2]); // متوفر
  $item2 = new CartItem(['id' => 11, 'item_id' => 102, 'quantity' => 10]); // غير كافٍ
  $item3 = new CartItem(['id' => 12, 'item_id' => 103, 'quantity' => 1]); // نفد

  $mockCart->setRelation('items', collect([$item1, $item2, $item3]));

  $mockEntries = [['id' => 101], ['id' => 102], ['id' => 103]];
  $mockEnriched = [
    ['id' => 101, 'final_price' => 100, 'original_price' => 120, 'available_stock' => 5],
    ['id' => 102, 'final_price' => 50, 'available_stock' => 3],
    ['id' => 103, 'final_price' => 30, 'available_stock' => 0],
  ];

  // 2. بناء الـ Mocks
  $cartRepo = Mockery::mock(CartRepositoryInterface::class);
  $fetchEntries = Mockery::mock(FetchEntriesByIdsAction::class);
  $enrichPrices = Mockery::mock(EnrichEntriesWithPricesAction::class);
  $stockResolver = Mockery::mock(StockStatusResolver::class);

  $cartRepo->shouldReceive('getOrCreate')->andReturn($mockCart);
  $cartRepo->shouldReceive('loadItems')->andReturn($mockCart);

  $fetchEntries->shouldReceive('execute')->once()->andReturn($mockEntries);
  $enrichPrices->shouldReceive('execute')->once()->andReturn($mockEnriched);

  // توقعات الـ StockResolver
  $stockResolver->shouldReceive('resolve')->with(5, 2)->once()->andReturn('available');
  $stockResolver->shouldReceive('resolve')->with(3, 10)->once()->andReturn('insufficient');
  $stockResolver->shouldReceive('resolve')->with(0, 1)->once()->andReturn('out_of_stock');

  $action = new GetCartAction($cartRepo, $fetchEntries, $enrichPrices, $stockResolver);
  $result = $action->execute($projectId, $userId);

  // 3. التحقق من النتائج
  expect($result['cart_id'])->toBe(1);
  expect($result['items'])->toHaveCount(3);

  // فحص المنتج الأول
  expect($result['items'][0]['stock_status'])->toBe('available');
  expect($result['items'][0]['subtotal'])->toBe(200);

  // فحص المنتج الثاني
  expect($result['items'][1]['stock_status'])->toBe('insufficient');

  // فحص المنتج الثالث
  expect($result['items'][2]['stock_status'])->toBe('out_of_stock');

  // الإجمالي
  expect($result['total'])->toBe(730);
  expect($result['total_items'])->toBe(13);
});

it('resolves stock status when stock info is null', function () {
  $projectId = 1;
  $userId = 10;
  $mockCart = new Cart(['id' => 1]);
  $item = new CartItem(['id' => 10, 'item_id' => 999, 'quantity' => 1]);
  $mockCart->setRelation('items', collect([$item]));

  $cartRepo = Mockery::mock(CartRepositoryInterface::class);
  $fetchEntries = Mockery::mock(FetchEntriesByIdsAction::class);
  $enrichPrices = Mockery::mock(EnrichEntriesWithPricesAction::class);
  $stockResolver = Mockery::mock(StockStatusResolver::class);

  $cartRepo->shouldReceive('getOrCreate')->andReturn($mockCart);
  $cartRepo->shouldReceive('loadItems')->andReturn($mockCart);
  $fetchEntries->shouldReceive('execute')->andReturn([['id' => 999]]);
  $enrichPrices->shouldReceive('execute')->andReturn([['id' => 999, 'final_price' => 10, 'available_stock' => null]]);

  $stockResolver->shouldReceive('resolve')->with(null, 1)->once()->andReturn('available');

  $action = new GetCartAction($cartRepo, $fetchEntries, $enrichPrices, $stockResolver);
  $result = $action->execute($projectId, $userId);

  expect($result['items'][0]['stock_status'])->toBe('available');
});
