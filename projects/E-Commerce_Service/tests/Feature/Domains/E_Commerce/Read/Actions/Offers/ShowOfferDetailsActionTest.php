<?php

use App\Domains\E_Commerce\Read\Actions\Offers\ShowOfferDetailsAction;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use App\Models\Offer;
use App\Services\CMS\CMSApiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

beforeEach(function () {
  // بناء جدول الـ Circuit Breaker
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
});

it('fetches offer details by slug and caches the combined result', function () {
  $slug = 'summer-sale-2026';
  $collectionId = 55;

  // 1. إعداد البيانات الوهمية
  $mockCollection = [
    'id' => $collectionId,
    'title' => 'Summer Sale',
    'slug' => $slug
  ];

  // إنشاء كائن موديل حقيقي (بدون حفظه في الداتابيز) لإرضاء الـ Type Hint
  $mockOfferModel = new Offer([
    'id' => 1,
    'discount_percentage' => 20,
    'collection_id' => $collectionId
  ]);

  // 2. بناء الـ Mocks
  $cmsClient = Mockery::mock(CMSApiClient::class);
  $repository = Mockery::mock(OfferRepositoryInterface::class);

  // توقعات الـ CMS
  $cmsClient->shouldReceive('getCollectionBySlug')
    ->once()
    ->with($slug)
    ->andReturn($mockCollection);

  // توقعات الـ Repository: إرجاع كائن من نوع Offer
  $repository->shouldReceive('getOfferDetails')
    ->once()
    ->with($collectionId)
    ->andReturn($mockOfferModel);

  $action = new ShowOfferDetailsAction($cmsClient, $repository);

  // 3. التنفيذ الأول
  $result1 = $action->execute($slug);

  // 4. التنفيذ الثاني
  $result2 = $action->execute($slug);

  // التحقق من صحة النتائج
  expect($result1)->toBeArray();
  expect($result1['offer'])->toBeInstanceOf(Offer::class);
  expect($result1['offer']->discount_percentage)->toBe(20);
  expect($result2)->toEqual($result1);
});

it('uses the correct circuit breaker service name for offer details', function () {
  $cmsClient = Mockery::mock(CMSApiClient::class);
  $repository = Mockery::mock(OfferRepositoryInterface::class);

  $action = new class($cmsClient, $repository) extends ShowOfferDetailsAction {
    public function getServiceName(): string
    {
      return $this->circuitServiceName();
    }
  };

  expect($action->getServiceName())->toBe('offer.showDetails');
});
