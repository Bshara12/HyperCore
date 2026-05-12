<?php

use App\Domains\E_Commerce\Actions\Offers\InsertOfferItemsAction;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use App\Services\CMS\CMSApiClient;
use App\Models\Offer;
use App\Events\SystemLogEvent;
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

it('inserts items successfully and clears cache', function () {
  Event::fake();
  Cache::shouldReceive('forget')->twice();

  $dto = new Fluent([
    'collectionSlug' => 'summer-sale',
    'items' => [101, 102]
  ]);

  $mockCollection = ['id' => 500, 'slug' => 'summer-sale'];
  $mockOffer = new Offer(['id' => 1, 'collection_id' => 500]);

  $cms = Mockery::mock(CMSApiClient::class);
  $repository = Mockery::mock(OfferRepositoryInterface::class);

  // 1. محاكاة نجاح إضافة العناصر
  $cms->shouldReceive('addCollectionItems')
    ->once()
    ->with('summer-sale', [101, 102])
    ->andReturn("Items added successfully");

  // 2. محاكاة جلب البيانات لتنظيف الكاش
  $cms->shouldReceive('getCollectionBySlug')
    ->once()
    ->with('summer-sale')
    ->andReturn($mockCollection);

  $repository->shouldReceive('findByCollectionId')
    ->once()
    ->with(500)
    ->andReturn($mockOffer);

  $action = new InsertOfferItemsAction($cms, $repository);
  $result = $action->execute($dto);

  // 3. التحقق
  expect($result['message'])->toBe("Items added successfully");
  expect($result['collection']['id'])->toBe(500);

  Event::assertDispatched(SystemLogEvent::class, function ($event) {
    return $event->eventType === 'isert_offer_item' && $event->entityId === 500;
  });
});

it('does nothing if CMS response is not success message', function () {
  Event::fake();

  $dto = new Fluent([
    'collectionSlug' => 'summer-sale',
    'items' => []
  ]);

  $cms = Mockery::mock(CMSApiClient::class);
  $repository = Mockery::mock(OfferRepositoryInterface::class);

  // محاكاة استجابة مختلفة من الـ CMS
  $cms->shouldReceive('addCollectionItems')->andReturn("Failed to add items");

  $action = new InsertOfferItemsAction($cms, $repository);
  $result = $action->execute($dto);

  // التحقق من عدم الدخول في كتلة الـ if (النتيجة null والحدث لم يُطلق)
  expect($result)->toBeNull();
  Event::assertNotDispatched(SystemLogEvent::class);
});

it('returns the correct circuit breaker service name', function () {
  $cms = Mockery::mock(CMSApiClient::class);
  $repository = Mockery::mock(OfferRepositoryInterface::class);

  $action = new class($cms, $repository) extends InsertOfferItemsAction {
    public function getServiceName(): string
    {
      return $this->circuitServiceName();
    }
  };

  expect($action->getServiceName())->toBe('offer.insertItems');
});
