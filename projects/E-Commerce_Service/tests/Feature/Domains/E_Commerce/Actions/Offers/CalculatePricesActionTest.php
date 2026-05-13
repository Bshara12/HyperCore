<?php

use App\Domains\E_Commerce\Actions\Offers\CalculatePricesAction;
use App\Domains\E_Commerce\Benefits\BenefitStrategy;
use App\Domains\E_Commerce\Benefits\BenefitStrategyFactory;
use App\Domains\E_Commerce\Benefits\Interfaces\BenefitStrategyInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferPriceRepositoryInterface;
use App\Services\CMS\CMSApiClient;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Fluent;

beforeEach(function () {
  if (!Schema::hasTable('circuit_breakers')) {
    Schema::create('circuit_breakers', function (Blueprint $table) {
      $table->id();
      $table->string('service_name')->unique();
      $table->string('state')->default('closed');
      $table->integer('failure_count')->default(0);
      $table->integer('failure_threshold')->default(5);
      $table->timestamp('opened_at')->nullable();
      $table->timestamp('next_attempt_at')->nullable();
      $table->timestamps();
    });
  }
});

it('calculates prices and handles best price logic correctly', function () {
  // 1. إعداد البيانات
  $data = [
    'offer' => [
      'id' => 1,
      'benefit_type' => 'percentage',
      'benefit_config' => ['value' => 10],
      'is_code_offer' => false
    ],
    'collection' => ['slug' => 'summer-sale']
  ];

  $entries = [
    ['id' => 101, 'price' => 100], // سيتم تحديثه (أرخص من الموجود)
    ['id' => 102, 'price' => 200], // سيتم تجاهله (نفس السعر الحالي)
    ['id' => 103, 'price' => 300], // عرض جديد كلياً
  ];

  // 2. بناء الـ Mocks
  $cms = Mockery::mock(CMSApiClient::class);
  $factory = Mockery::mock(BenefitStrategyFactory::class);
  $strategy = Mockery::mock(BenefitStrategy::class);
  $repository = Mockery::mock(OfferPriceRepositoryInterface::class);

  $factory->shouldReceive('make')->with('percentage')->andReturn($strategy);
  $cms->shouldReceive('getDynamicEntries')->andReturn($entries);

  // محاكاة عملية الحساب (خصم 10%)
  $strategy->shouldReceive('calculate')->andReturnUsing(fn($p) => $p * 0.9);

  // توقعات الـ Repository لكل عنصر:

  // العنصر 101: موجود مسبقاً بسعر 95، الجديد 90 (أرخص) -> احذف القديم ثم قارن مع الأدنى
  $existing101 = new Fluent(['id' => 50, 'final_price' => 95]);
  $repository->shouldReceive('getEntryPrice')->with(101, 1)->andReturn($existing101);
  $repository->shouldReceive('deleteOfferPrice')->with(50)->once();
  $repository->shouldReceive('getLowestPriceItem')->with(101)->andReturn(new Fluent(['final_price' => 92]));
  $repository->shouldReceive('disableItemPrice')->with(101)->once();
  $repository->shouldReceive('enterOfferItem')->once();

  // العنصر 102: موجود مسبقاً بسعر 180، الجديد 180 -> تجاهل (continue)
  $existing102 = new Fluent(['id' => 51, 'final_price' => 180]);
  $repository->shouldReceive('getEntryPrice')->with(102, 1)->andReturn($existing102);

  // العنصر 103: جديد كلياً
  $repository->shouldReceive('getEntryPrice')->with(103, 1)->andReturn(null);
  $repository->shouldReceive('getLowestPriceItem')->with(103)->andReturn(null);
  $repository->shouldReceive('enterOfferItem')->once();

  $action = new CalculatePricesAction($cms, $factory, $repository);
  $result = $action->execute($data);

  expect($result)->toHaveCount(2); // 101 و 103 فقط، 102 عمل continue
});

it('handles code offers without comparing with lowest price', function () {
  $data = [
    'offer' => [
      'id' => 2,
      'benefit_type' => 'fixed',
      'benefit_config' => ['value' => 50],
      'is_code_offer' => true
    ],
    'collection' => ['slug' => 'code-sale']
  ];

  $cms = Mockery::mock(CMSApiClient::class);
  $factory = Mockery::mock(BenefitStrategyFactory::class);
  $strategy = Mockery::mock(BenefitStrategy::class);
  $repository = Mockery::mock(OfferPriceRepositoryInterface::class);

  $factory->shouldReceive('make')->andReturn($strategy);
  $cms->shouldReceive('getDynamicEntries')->andReturn([['id' => 200, 'price' => 500]]);
  $strategy->shouldReceive('calculate')->andReturn(450);

  $repository->shouldReceive('getEntryPrice')->andReturn(null);

  // في عروض الكود، يتم الإدخال مباشرة بدون getLowestPriceItem
  $repository->shouldReceive('enterOfferItem')->once()->with(Mockery::on(function ($entry) {
    return $entry['is_code_price'] === true && $entry['is_applied'] === true;
  }));

  $action = new CalculatePricesAction($cms, $factory, $repository);
  $action->execute($data);
});

it('keeps current offer applied if new price is higher than lowest price', function () {
  $data = [
    'offer' => ['id' => 3, 'benefit_type' => 'fixed', 'benefit_config' => [], 'is_code_offer' => false],
    'collection' => ['slug' => 'test']
  ];

  $cms = Mockery::mock(CMSApiClient::class);
  $factory = Mockery::mock(BenefitStrategyFactory::class);
  $strategy = Mockery::mock(BenefitStrategy::class);
  $repository = Mockery::mock(OfferPriceRepositoryInterface::class);

  $factory->shouldReceive('make')->andReturn($strategy);
  $cms->shouldReceive('getDynamicEntries')->andReturn([['id' => 300, 'price' => 100]]);
  $strategy->shouldReceive('calculate')->andReturn(90); // السعر الجديد 90

  $repository->shouldReceive('getEntryPrice')->andReturn(null);

  // السعر الأدنى الحالي هو 80، إذن الجديد (90) لن يطبق
  $repository->shouldReceive('getLowestPriceItem')->andReturn(new Fluent(['final_price' => 80]));

  $repository->shouldReceive('enterOfferItem')->once()->with(Mockery::on(function ($entry) {
    return $entry['is_applied'] === false;
  }));

  $action = new CalculatePricesAction($cms, $factory, $repository);
  $action->execute($data);
});
