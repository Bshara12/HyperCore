<?php

use App\Domains\E_Commerce\Actions\Offers\DeleteOfferAction;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use App\Services\CMS\CMSApiClient;
use App\Models\Offer;
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

it('deletes an offer and clears all related cache keys including project offers', function () {
  $slug = 'black-friday-sale';
  $collectionId = 500;
  $projectId = 1;

  // 1. إعداد البيانات والموديلات الوهمية
  $mockCollection = [
    'id' => $collectionId,
    'slug' => $slug
  ];

  // نحتاج لموديل العرض للحصول على project_id قبل الحذف
  $mockOffer = new Offer([
    'id' => 10,
    'collection_id' => $collectionId,
    'project_id' => $projectId
  ]);

  // 2. بناء الـ Mocks
  $repository = Mockery::mock(OfferRepositoryInterface::class);
  $cmsClient = Mockery::mock(CMSApiClient::class);

  // توقعات الـ CMS
  $cmsClient->shouldReceive('getCollectionBySlug')
    ->once()
    ->with($slug)
    ->andReturn($mockCollection);

  // توقعات الـ Repository
  $repository->shouldReceive('findByCollectionId')
    ->once()
    ->with($collectionId)
    ->andReturn($mockOffer);

  $repository->shouldReceive('deleteOfferByCollectionId')
    ->once()
    ->with($collectionId);

  // توقعات الكاش: يجب استدعاء forget 3 مرات
  Cache::shouldReceive('forget')->times(3);

  $action = new DeleteOfferAction($repository, $cmsClient);

  // 3. التنفيذ
  $action->execute($slug);

  // الاختبار يمر بنجاح إذا لم تحدث استثناءات وتم استدعاء الـ Mocks بالعدد المطلوب
});

it('has the correct circuit breaker service name for deletion', function () {
  $repository = Mockery::mock(OfferRepositoryInterface::class);
  $cmsClient = Mockery::mock(CMSApiClient::class);

  $action = new class($repository, $cmsClient) extends DeleteOfferAction {
    public function getServiceName(): string
    {
      return $this->circuitServiceName();
    }
  };

  expect($action->getServiceName())->toBe('offer.delete');
});
