<?php

use App\Domains\E_Commerce\Actions\Order\CalculateCartPricingAction;
use App\Domains\E_Commerce\Actions\Pricing\EnrichEntriesWithPricesAction;
use App\Domains\E_Commerce\Actions\Pricing\FetchEntriesByIdsAction;
use Illuminate\Support\Fluent;

it('calculates cart pricing correctly by fetching and enriching entry data', function () {
  // 1. إعداد بيانات السلة الوهمية
  $cartItem1 = new Fluent(['item_id' => 101, 'quantity' => 2]);
  $cartItem2 = new Fluent(['item_id' => 102, 'quantity' => 1]);

  $cart = new Fluent([
    'items' => collect([$cartItem1, $cartItem2])
  ]);

  // 2. إعداد البيانات العائدة من الـ Actions المعتمد عليها (Dependencies)
  $fetchedEntries = [
    ['id' => 101, 'slug' => 'product-a'],
    ['id' => 102, 'slug' => 'product-b']
  ];

  $enrichedEntries = [
    [
      'id' => 101,
      'slug' => 'product-a',
      'final_price' => 50,
      0 => 'Product A Title', // لتغطية $entry[0]
      3 => 10 // لتغطية $entry[3] (العدد مثلاً)
    ],
    [
      'id' => 102,
      'slug' => 'product-b',
      'final_price' => 100,
      // نختبر حالة غياب العناوين $entry[0] و $entry[3] لتغطية الـ null coalescing
    ]
  ];

  // 3. بناء الـ Mocks للـ Actions
  $fetchAction = Mockery::mock(FetchEntriesByIdsAction::class);
  $enrichAction = Mockery::mock(EnrichEntriesWithPricesAction::class);

  $fetchAction->shouldReceive('execute')
    ->once()
    ->with([101, 102])
    ->andReturn($fetchedEntries);

  $enrichAction->shouldReceive('execute')
    ->once()
    ->with($fetchedEntries)
    ->andReturn($enrichedEntries);

  $action = new CalculateCartPricingAction($fetchAction, $enrichAction);

  // 4. التنفيذ
  $result = $action->execute($cart);

  // 5. التحقق من الحسابات
  // المنتج الأول: 50 * 2 = 100
  // المنتج الثاني: 100 * 1 = 100
  // المجموع الكلي: 200
  expect($result['total'])->toBe(200);
  expect($result['items'])->toHaveCount(2);

  // التحقق من تفاصيل المنتج الأول
  expect($result['items'][0])->toMatchArray([
    'product_id' => 101,
    'title' => 'Product A Title',
    'price' => 50,
    'total' => 100,
    'count' => 10
  ]);

  // التحقق من تفاصيل المنتج الثاني (حالة الـ Default Values)
  expect($result['items'][1]['title'])->toBe('N/A');
  expect($result['items'][1]['count'])->toBeNull();
});

it('handles empty carts gracefully', function () {
  $cart = new Fluent(['items' => collect([])]);

  $fetchAction = Mockery::mock(FetchEntriesByIdsAction::class);
  $enrichAction = Mockery::mock(EnrichEntriesWithPricesAction::class);

  $fetchAction->shouldReceive('execute')->with([])->andReturn([]);
  $enrichAction->shouldReceive('execute')->with([])->andReturn([]);

  $action = new CalculateCartPricingAction($fetchAction, $enrichAction);
  $result = $action->execute($cart);

  expect($result['total'])->toBe(0);
  expect($result['items'])->toBeEmpty();
});
