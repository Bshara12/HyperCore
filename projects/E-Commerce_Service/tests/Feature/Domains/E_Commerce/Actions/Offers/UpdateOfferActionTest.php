<?php

use App\Domains\E_Commerce\Actions\Offers\UpdateOfferAction;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use App\Events\SystemLogEvent;
use App\Models\Offer;
use Illuminate\Support\Facades\Cache;
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

it('updates an offer, clears cache and dispatches system log event', function () {
  // 1. إعداد البيانات والـ Mocks
  Event::fake();
  Cache::shouldReceive('forget')->twice();

  $collectionId = "500";
  $offerData = ['name' => 'Updated Offer Name', 'discount' => 20];

  $dto = new Fluent([
    'offerData' => $offerData
  ]);

  $mockOffer = new Offer([
    'id' => 1,
    'project_id' => 10,
    'name' => 'Updated Offer Name'
  ]);

  $repository = Mockery::mock(OfferRepositoryInterface::class);

  // 2. توقعات الـ Repository
  $repository->shouldReceive('update')
    ->once()
    ->with($collectionId, $offerData)
    ->andReturn($mockOffer);

  $action = new UpdateOfferAction($repository);

  // 3. التنفيذ
  $result = $action->execute($collectionId, $dto);

  // 4. التحقق
  expect($result)->toBeInstanceOf(Offer::class);
  expect($result->id)->toBe(1);

  // التحقق من إطلاق الحدث بالبيانات الصحيحة
  Event::assertDispatched(SystemLogEvent::class, function ($event) {
    return $event->module === 'ecommerce' &&
      $event->eventType === 'update_offer' &&
      $event->entityId === 1;
  });
});

it('has the correct circuit breaker service name for offer update', function () {
  $repository = Mockery::mock(OfferRepositoryInterface::class);

  $action = new class($repository) extends UpdateOfferAction {
    public function getServiceName(): string
    {
      return $this->circuitServiceName();
    }
  };

  expect($action->getServiceName())->toBe('offer.updateOffer');
});
