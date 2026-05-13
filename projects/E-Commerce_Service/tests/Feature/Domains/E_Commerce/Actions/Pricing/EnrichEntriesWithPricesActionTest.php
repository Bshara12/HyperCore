<?php

use App\Domains\E_Commerce\Actions\Pricing\EnrichEntriesWithPricesAction;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferPriceRepositoryInterface;
use Illuminate\Support\Collection;

beforeEach(function () {
  // عمل Mock للـ Repository
  $this->offerRepo = Mockery::mock(OfferPriceRepositoryInterface::class);
  $this->action = new EnrichEntriesWithPricesAction($this->offerRepo);
});

it('enriches entries with the best price between auto and user offers', function () {
  // 1. البيانات المدخلة (Entries)
  $entries = [
    [
      'id' => 1,
      'slug' => 'product-1',
      'values' => ['price' => 100] // سعر أصلي 100
    ]
  ];

  // 2. محاكاة العروض التلقائية (مثلاً سعر 90)
  $autoPrices = collect([
    1 => (object)[
      'final_price' => 90,
      'applied_offer_id' => 'auto-offer-123'
    ]
  ]);

  // 3. محاكاة عروض المستخدم (مثلاً سعر 80 - هو الأفضل)
  $userPrices = collect([
    1 => (object)[
      'final_price' => 80,
      'applied_offer_id' => 'user-offer-456'
    ]
  ]);

  // إعداد التوقعات للـ Repository
  $this->offerRepo->shouldReceive('getAutomaticPrices')->once()->andReturn($autoPrices);
  $this->offerRepo->shouldReceive('getUserPrices')->once()->andReturn($userPrices);

  // تنفيذ الـ Action
  $result = $this->action->execute($entries);

  // التحققات (Assertions)
  expect($result)->toBeArray()->toHaveCount(1);
  expect($result[0]['final_price'])->toBe(80.0); // اختار السعر الأقل
  expect($result[0]['is_offer_applied'])->toBeTrue();
  expect($result[0]['applied_offer_id'])->toBe('user-offer-456');
  expect($result[0]['original_price'])->toBe(100.0);
});

it('returns original price when no offers exist', function () {
  $entries = [['id' => 2, 'slug' => 'p2', 'values' => ['price' => 50]]];

  $this->offerRepo->shouldReceive('getAutomaticPrices')->andReturn(collect());
  $this->offerRepo->shouldReceive('getUserPrices')->andReturn(collect());

  $result = $this->action->execute($entries);

  expect($result[0]['final_price'])->toBe(50.0);
  expect($result[0]['is_offer_applied'])->toBeFalse();
});

it('normalizes values when provided in EAV format (array of objects)', function () {
  // 1. بيانات بتنسيق الـ CMS
  $entries = [
    [
      'id' => 10,
      'slug' => 'dynamic-product',
      'values' => [
        ['data_type_field_id' => 2, 'value' => 150],
        ['data_type_field_id' => 5, 'value' => 'Product Name'],
      ]
    ]
  ];

  $this->offerRepo->shouldReceive('getAutomaticPrices')->andReturn(collect());
  $this->offerRepo->shouldReceive('getUserPrices')->andReturn(collect());

  $result = $this->action->execute($entries);

  // التحقق من النتيجة النهائية مباشرة
  // بما أن الـ spread operator (...$values) قد يعيد ترتيب المفاتيح الرقمية
  // سنفحص الحقول التي تم استخلاصها
  expect($result[0]['original_price'])->toBe(150.0);
  expect($result[0]['final_price'])->toBe(150.0);

  // للتحقق من أن القيم الديناميكية موجودة فعلاً في المصفوفة
  expect($result[0])->toContain(150);
  expect($result[0])->toContain('Product Name');
});
