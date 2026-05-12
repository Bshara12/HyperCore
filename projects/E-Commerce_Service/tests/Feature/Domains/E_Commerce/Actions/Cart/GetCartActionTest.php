<?php

use App\Domains\E_Commerce\Actions\Cart\GetCartAction;
use App\Domains\E_Commerce\Actions\Pricing\EnrichEntriesWithPricesAction;
use App\Domains\E_Commerce\Actions\Pricing\FetchEntriesByIdsAction;
use App\Domains\E_Commerce\Repositories\Interfaces\Cart\CartRepositoryInterface;
use App\Services\CMS\CMSApiClient;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Support\Facades\Cache;

it('returns empty structure when cart has no items', function () {
  $projectId = 1;
  $userId = 10;

  $cartRepo = Mockery::mock(CartRepositoryInterface::class);
  $fetchEntries = Mockery::mock(FetchEntriesByIdsAction::class);
  $enrichPrices = Mockery::mock(EnrichEntriesWithPricesAction::class);
  $cms = Mockery::mock(CMSApiClient::class);

  $mockCart = new Cart(['id' => 1]);
  $mockCart->setRelation('items', collect()); // سلة فارغة

  $cartRepo->shouldReceive('getOrCreate')->once()->andReturn($mockCart);
  $cartRepo->shouldReceive('loadItems')->once()->andReturn($mockCart);

  $action = new GetCartAction($cartRepo, $fetchEntries, $enrichPrices, $cms);
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
    ['id' => 101, 'final_price' => 100, 'original_price' => 120],
    ['id' => 102, 'final_price' => 50],
    ['id' => 103, 'final_price' => 30]
  ];
  $mockStock = [
    101 => ['available' => 5],    // متاح (أكثر من المطلوب 2)
    102 => ['available' => 3],    // غير كافٍ (أقل من المطلوب 10)
    103 => ['available' => 0],    // نفد
  ];

  // 2. بناء الـ Mocks
  $cartRepo = Mockery::mock(CartRepositoryInterface::class);
  $fetchEntries = Mockery::mock(FetchEntriesByIdsAction::class);
  $enrichPrices = Mockery::mock(EnrichEntriesWithPricesAction::class);
  $cms = Mockery::mock(CMSApiClient::class);

  $cartRepo->shouldReceive('getOrCreate')->andReturn($mockCart);
  $cartRepo->shouldReceive('loadItems')->andReturn($mockCart);

  $fetchEntries->shouldReceive('execute')->once()->andReturn($mockEntries);
  $enrichPrices->shouldReceive('execute')->once()->andReturn($mockEnriched);
  $cms->shouldReceive('getStockStatus')->once()->andReturn($mockStock);

  $action = new GetCartAction($cartRepo, $fetchEntries, $enrichPrices, $cms);
  $result = $action->execute($projectId, $userId);

  // 3. التحقق من النتائج ومنطق الـ resolveStockStatus
  expect($result['cart_id'])->toBe(1);
  expect($result['items'])->toHaveCount(3);

  // فحص المنتج الأول (متوفر)
  expect($result['items'][0]['stock_status'])->toBe('available');
  expect($result['items'][0]['subtotal'])->toBe(200); // 100 * 2

  // فحص المنتج الثاني (غير كافٍ)
  expect($result['items'][1]['stock_status'])->toBe('insufficient');

  // فحص المنتج الثالث (نفد)
  expect($result['items'][2]['stock_status'])->toBe('out_of_stock');

  // التحقق من الإجمالي
  expect($result['total'])->toBe(730); // (100*2) + (50*10) + (30*1)
  expect($result['total_items'])->toBe(13); // 2 + 10 + 1
});

it('resolves stock as available when stock info is null', function () {
  // اختبار حالة الـ null في الـ Private Method لتغطية الـ 100%
  $projectId = 1;
  $userId = 10;
  $mockCart = new Cart(['id' => 1]);
  $item = new CartItem(['id' => 10, 'item_id' => 999, 'quantity' => 1]);
  $mockCart->setRelation('items', collect([$item]));

  $cartRepo = Mockery::mock(CartRepositoryInterface::class);
  $fetchEntries = Mockery::mock(FetchEntriesByIdsAction::class);
  $enrichPrices = Mockery::mock(EnrichEntriesWithPricesAction::class);
  $cms = Mockery::mock(CMSApiClient::class);

  $cartRepo->shouldReceive('getOrCreate')->andReturn($mockCart);
  $cartRepo->shouldReceive('loadItems')->andReturn($mockCart);
  $fetchEntries->shouldReceive('execute')->andReturn([['id' => 999]]);
  $enrichPrices->shouldReceive('execute')->andReturn([['id' => 999, 'final_price' => 10]]);
  $cms->shouldReceive('getStockStatus')->andReturn([999 => null]); // الستوك غير موجود

  $action = new GetCartAction($cartRepo, $fetchEntries, $enrichPrices, $cms);
  $result = $action->execute($projectId, $userId);

  expect($result['items'][0]['stock_status'])->toBe('available');
});
