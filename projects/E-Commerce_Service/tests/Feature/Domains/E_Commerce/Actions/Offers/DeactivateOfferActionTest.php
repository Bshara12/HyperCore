<?php

use App\Domains\E_Commerce\Actions\Offers\DeactivateOfferAction;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use App\Services\CMS\CMSApiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

beforeEach(function () {
  // بناء جدول الـ Circuit Breaker لضمان استقرار الاختبار
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

it('deactivates an offer and clears cache keys successfully', function () {
  $slug = 'expired-sale-2025';
  $collectionId = 123;

  // 1. إعداد الـ DTO والبيانات الوهمية
  $dto = new \Illuminate\Support\Fluent([
    'collectionSlug' => $slug
  ]);

  $mockCollection = [
    'id' => $collectionId,
    'slug' => $slug,
    'title' => 'Expired Sale'
  ];

  // 2. بناء الـ Mocks
  $repository = Mockery::mock(OfferRepositoryInterface::class);
  $cmsClient = Mockery::mock(CMSApiClient::class);

  // توقعات الـ CMS: جلب البيانات بالـ Slug
  $cmsClient->shouldReceive('getCollectionBySlug')
    ->once()
    ->with($slug)
    ->andReturn($mockCollection);

  // توقعات الـ Repository: استدعاء إيقاف التنشيط بالـ ID
  $repository->shouldReceive('deactivateOffer')
    ->once()
    ->with($collectionId);

  // توقعات الكاش: مسح مفتاحي الـ ID والـ Slug
  Cache::shouldReceive('forget')->twice();

  $action = new DeactivateOfferAction($repository, $cmsClient);

  // 3. التنفيذ
  $result = $action->execute($dto);

  // 4. التحقق
  expect($result)->toBeArray();
  expect($result['id'])->toBe($collectionId);
  expect($result['slug'])->toBe($slug);
});

it('has the correct circuit breaker service name for deactivation', function () {
  $repository = Mockery::mock(OfferRepositoryInterface::class);
  $cmsClient = Mockery::mock(CMSApiClient::class);

  $action = new class($repository, $cmsClient) extends DeactivateOfferAction {
    public function getServiceName(): string
    {
      return $this->circuitServiceName();
    }
  };

  expect($action->getServiceName())->toBe('offer.deactivate');
});
