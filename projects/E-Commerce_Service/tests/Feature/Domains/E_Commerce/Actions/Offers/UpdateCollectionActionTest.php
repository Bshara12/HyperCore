<?php

use App\Domains\E_Commerce\Actions\Offers\UpdateCollectionAction;
use App\Services\CMS\CMSApiClient;
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

it('updates a collection in CMS using data from DTO', function () {
  $slug = 'summer-collection-2026';
  $updateData = ['title' => 'Updated Summer Sale', 'active' => true];

  // 1. إعداد الـ DTO والبيانات الوهمية
  $dto = new Fluent([
    'collectionSlug' => $slug,
    'collectionData' => $updateData
  ]);

  // 2. بناء الـ Mock للـ CMS Client
  $cmsClient = Mockery::mock(CMSApiClient::class);

  $cmsClient->shouldReceive('updateCollection')
    ->once()
    ->with($slug, $updateData)
    ->andReturn(['status' => 'success']);

  $action = new UpdateCollectionAction($cmsClient);

  // 3. التنفيذ
  $result = $action->execute($dto);

  // 4. التحقق
  expect($result)->toBeArray();
  expect($result['status'])->toBe('success');
});

it('defines the correct circuit breaker service name for updating collections', function () {
  $cmsClient = Mockery::mock(CMSApiClient::class);

  $action = new class($cmsClient) extends UpdateCollectionAction {
    public function getServiceName(): string
    {
      return $this->circuitServiceName();
    }
  };

  // لاحظ الـ Typo في الكود الأصلي 'updateCollcetion' لضمان مطابقة الاختبار للكود
  expect($action->getServiceName())->toBe('offer.updateCollcetion');
});
