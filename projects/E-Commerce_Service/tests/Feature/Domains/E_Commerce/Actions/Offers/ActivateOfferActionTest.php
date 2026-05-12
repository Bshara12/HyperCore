<?php

use App\Domains\E_Commerce\Actions\Offers\ActivateOfferAction;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use App\Services\CMS\CMSApiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

beforeEach(function () {
  // بناء جدول الـ Circuit Breaker
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

it('activates an offer and clears relevant cache keys', function () {
  $slug = 'mega-sale-2026';
  $collectionId = 99;

  // 1. إعداد الـ DTO والبيانات الوهمية
  $dto = new \Illuminate\Support\Fluent([
    'collectionSlug' => $slug
  ]);

  $mockCollection = [
    'id' => $collectionId,
    'slug' => $slug,
    'title' => 'Mega Sale'
  ];

  // 2. بناء الـ Mocks
  $repository = Mockery::mock(OfferRepositoryInterface::class);
  $cmsClient = Mockery::mock(CMSApiClient::class);

  // توقعات الـ CMS
  $cmsClient->shouldReceive('getCollectionBySlug')
    ->once()
    ->with($slug)
    ->andReturn($mockCollection);

  // توقعات الـ Repository
  $repository->shouldReceive('activateOffer')
    ->once()
    ->with($collectionId);

  // توقعات الكاش (يجب مسح مفتاحين)
  Cache::shouldReceive('forget')->twice();

  $action = new ActivateOfferAction($repository, $cmsClient);

  // 3. التنفيذ
  $result = $action->execute($dto);

  // 4. التحقق
  expect($result)->toBeArray();
  expect($result['id'])->toBe($collectionId);
});

it('uses the correct circuit service name for activation', function () {
  $repository = Mockery::mock(OfferRepositoryInterface::class);
  $cmsClient = Mockery::mock(CMSApiClient::class);

  $action = new class($repository, $cmsClient) extends ActivateOfferAction {
    public function getServiceName(): string
    {
      return $this->circuitServiceName();
    }
  };

  expect($action->getServiceName())->toBe('offer.activate');
});
