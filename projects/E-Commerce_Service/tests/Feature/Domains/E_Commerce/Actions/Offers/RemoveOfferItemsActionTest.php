<?php

use App\Domains\E_Commerce\Actions\Offers\RemoveOfferItemsAction;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferPriceRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use App\Services\CMS\CMSApiClient;
use App\Models\Offer;
use Illuminate\Support\Facades\Cache;
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

it('removes items from CMS and local repository, then clears cache', function () {
  Cache::shouldReceive('forget')->twice();

  // 1. إعداد البيانات والـ DTO
  $dto = new Fluent([
    'collectionSlug' => 'summer-promo',
    'items' => [201, 202]
  ]);

  $mockCollection = ['id' => 88, 'slug' => 'summer-promo'];
  $mockOffer = new Offer(['id' => 10, 'collection_id' => 88]);

  // 2. بناء الـ Mocks
  $cms = Mockery::mock(CMSApiClient::class);
  $offerRepo = Mockery::mock(OfferRepositoryInterface::class);
  $priceRepo = Mockery::mock(OfferPriceRepositoryInterface::class);

  // توقعات الـ CMS
  $cms->shouldReceive('removeCollectionItems')
    ->once()
    ->with('summer-promo', [201, 202])
    ->andReturn("Items removed successfully");

  $cms->shouldReceive('getCollectionBySlug')
    ->once()
    ->with('summer-promo')
    ->andReturn($mockCollection);

  // توقعات الـ Repository
  $offerRepo->shouldReceive('findByCollectionId')
    ->once()
    ->with(88)
    ->andReturn($mockOffer);

  // التأكد من استدعاء الحذف مرتين (مرة لكل ID في الـ items)
  $priceRepo->shouldReceive('deleteOfferPriceForEntryAndProject')
    ->times(2)
    ->with(Mockery::any(), 10);

  $action = new RemoveOfferItemsAction($cms, $offerRepo, $priceRepo);

  // 3. التنفيذ
  $action->execute($dto);

  // التحقق يتم عبر توقعات Mockery
});

it('does nothing if CMS removal message is not successful', function () {
  $dto = new Fluent([
    'collectionSlug' => 'summer-promo',
    'items' => [201]
  ]);

  $cms = Mockery::mock(CMSApiClient::class);
  $offerRepo = Mockery::mock(OfferRepositoryInterface::class);
  $priceRepo = Mockery::mock(OfferPriceRepositoryInterface::class);

  // محاكاة استجابة فاشلة
  $cms->shouldReceive('removeCollectionItems')->andReturn("Error removing items");

  // نتحقق من أن بقية التوابع لم تُستدعى (تغطية عدم الدخول في الـ if)
  $cms->shouldNotReceive('getCollectionBySlug');
  $offerRepo->shouldNotReceive('findByCollectionId');

  $action = new RemoveOfferItemsAction($cms, $offerRepo, $priceRepo);
  $action->execute($dto);
});

it('defines the correct circuit breaker service name for removing items', function () {
  $cms = Mockery::mock(CMSApiClient::class);
  $offerRepo = Mockery::mock(OfferRepositoryInterface::class);
  $priceRepo = Mockery::mock(OfferPriceRepositoryInterface::class);

  $action = new class($cms, $offerRepo, $priceRepo) extends RemoveOfferItemsAction {
    public function getServiceName(): string
    {
      return $this->circuitServiceName();
    }
  };

  expect($action->getServiceName())->toBe('offer.removeItems');
});
