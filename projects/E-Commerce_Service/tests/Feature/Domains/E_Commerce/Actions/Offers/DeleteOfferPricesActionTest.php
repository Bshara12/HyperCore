<?php

use App\Domains\E_Commerce\Actions\Offers\DeleteOfferPricesAction;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferPriceRepositoryInterface;
use App\Events\SystemLogEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

beforeEach(function () {
  // بناء جدول الـ Circuit Breaker لتجنب أخطاء قاعدة البيانات في بيئة الاختبار
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

it('deletes offer prices and dispatches system log event', function () {
  // 1. إعداد التزييف (Fakes)
  Event::fake();
  $offerId = 123;

  // 2. بناء الـ Mock للـ Repository
  $repository = Mockery::mock(OfferPriceRepositoryInterface::class);

  $repository->shouldReceive('deleteOfferPricesForOffer')
    ->once()
    ->with($offerId);

  $action = new DeleteOfferPricesAction($repository);

  // 3. التنفيذ
  $action->execute($offerId);

  // 4. التحقق من إطلاق الحدث بالبيانات الصحيحة
  Event::assertDispatched(SystemLogEvent::class, function ($event) use ($offerId) {
    return $event->module === 'ecommerce' &&
      $event->eventType === 'delete_offer_price' &&
      $event->userId === null &&
      $event->entityType === 'offer' &&
      $event->entityId === $offerId;
  });
});

it('defines the correct circuit breaker service name for deleting prices', function () {
  $repository = Mockery::mock(OfferPriceRepositoryInterface::class);

  $action = new class($repository) extends DeleteOfferPricesAction {
    public function getServiceName(): string
    {
      return $this->circuitServiceName();
    }
  };

  expect($action->getServiceName())->toBe('offer.deletePrices');
});
