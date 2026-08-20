<?php

use App\Domains\E_Commerce\Actions\Offers\UpdateCollectionAction;
use App\Services\CMS\CMSApiClient;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Domains\E_Commerce\DTOs\Offers\UpdateOfferDTO;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;

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

it('updates a collection in CMS using data from DTO', function () {
  $slug = 'summer-collection-2026';
  $updateData = ['title' => 'Updated Summer Sale', 'active' => true];

  // 1. إعداد الـ DTO والبيانات الوهمية
  // execute() يفرض النوع UpdateOfferDTO لذا نبني الـ DTO الحقيقي
  $dto = new UpdateOfferDTO($slug, $updateData, [], 1);

  // 2. بناء الـ Mocks للـ CMS Client والـ Repository
  $cmsClient = Mockery::mock(CMSApiClient::class);
  $repository = Mockery::mock(OfferRepositoryInterface::class);

  $cmsClient->shouldReceive('updateCollection')
    ->once()
    ->with($slug, $updateData)
    ->andReturn(['status' => 'success']);

  $action = new UpdateCollectionAction($cmsClient, $repository);

  // 3. التنفيذ
  $result = $action->execute($dto);

  // 4. التحقق
  expect($result)->toBeArray();
  expect($result['status'])->toBe('success');
});

it('defines the correct circuit breaker service name for updating collections', function () {
  $cmsClient = Mockery::mock(CMSApiClient::class);
  $repository = Mockery::mock(OfferRepositoryInterface::class);

  $action = new class($cmsClient, $repository) extends UpdateCollectionAction {
    public function getServiceName(): string
    {
      return $this->circuitServiceName();
    }
  };

  // لاحظ الـ Typo في الكود الأصلي 'updateCollcetion' لضمان مطابقة الاختبار للكود
  expect($action->getServiceName())->toBe('offer.updateCollcetion');
});
