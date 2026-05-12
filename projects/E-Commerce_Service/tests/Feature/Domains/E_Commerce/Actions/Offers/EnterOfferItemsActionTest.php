<?php

use App\Domains\E_Commerce\Actions\Offers\EnterOfferItemsAction;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferPriceRepositoryInterface;
use App\Events\SystemLogEvent;
use Illuminate\Support\Facades\Event;
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

it('processes entries correctly based on code offer and best price logic', function () {
  Event::fake();
  $repository = Mockery::mock(OfferPriceRepositoryInterface::class);

  // تجهيز البيانات لعدة حالات
  $entries = [
    ['entry_id' => 101, 'final_price' => 80], // حالة: أرخص من الموجود
    ['entry_id' => 102, 'final_price' => 150], // حالة: أغلى من الموجود
    ['entry_id' => 103, 'final_price' => 200], // حالة: لا يوجد سعر سابق
  ];

  // 1. توقعات العنصر 101 (Better Price)
  $repository->shouldReceive('getLowestPriceItem')->with(101)
    ->once()->andReturn(new Fluent(['final_price' => 100]));
  $repository->shouldReceive('disableItemPrice')->with(101)->once();
  $repository->shouldReceive('enterOfferItem')->once()->with(Mockery::on(fn($e) => $e['is_applied'] === true));

  // 2. توقعات العنصر 102 (Worse Price)
  $repository->shouldReceive('getLowestPriceItem')->with(102)
    ->once()->andReturn(new Fluent(['final_price' => 100]));
  $repository->shouldReceive('enterOfferItem')->once()->with(Mockery::on(fn($e) => $e['is_applied'] === false));

  // 3. توقعات العنصر 103 (No existing price)
  $repository->shouldReceive('getLowestPriceItem')->with(103)
    ->once()->andReturn(null);
  $repository->shouldReceive('enterOfferItem')->once()->with(Mockery::on(fn($e) => $e['is_applied'] === true));

  $action = new EnterOfferItemsAction($repository);
  $action->execute($entries, false);

  // التحقق من إطلاق الحدث (يتم إطلاقه في الحالات التي ليست Code Offer)
  Event::assertDispatched(SystemLogEvent::class);
});

it('handles code offers directly without price comparison', function () {
  Event::fake();
  $repository = Mockery::mock(OfferPriceRepositoryInterface::class);

  $entries = [
    ['entry_id' => 200, 'final_price' => 50, 'applied_offer_id' => 5]
  ];

  // في عروض الكود، يتم الإدخال مباشرة مع وسم code_price
  $repository->shouldReceive('enterOfferItem')
    ->once()
    ->with(Mockery::on(function ($entry) {
      return $entry['is_applied'] === true && $entry['is_code_price'] === true;
    }));

  $action = new EnterOfferItemsAction($repository);
  $action->execute($entries, true); // isCodeOffer = true

  // ملاحظة: في الكود الخاص بك، الـ Event يقع خارج الـ if الخاص بالـ Code Offer 
  // لكنه يأتي بعد الـ continue، لذا لن يُطلق في حالة الـ Code Offer.
  Event::assertNotDispatched(SystemLogEvent::class);
});

it('returns the correct circuit breaker service name', function () {
  $repository = Mockery::mock(OfferPriceRepositoryInterface::class);
  $action = new class($repository) extends EnterOfferItemsAction {
    public function getServiceName(): string
    {
      return $this->circuitServiceName();
    }
  };
  expect($action->getServiceName())->toBe('offer.enterItems');
});
