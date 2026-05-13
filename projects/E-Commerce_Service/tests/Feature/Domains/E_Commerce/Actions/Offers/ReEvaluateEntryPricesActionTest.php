<?php

use App\Domains\E_Commerce\Actions\Offers\ReEvaluateEntryPricesAction;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use App\Services\CMS\CMSApiClient;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

beforeEach(function () {
  // بناء جدول الـ Circuit Breaker لضمان استقرار بيئة الاختبار
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

it('calls reEvaluate for each entry id provided', function () {
  // 1. إعداد البيانات
  $entries = [
    ['entry_id' => 1001],
    ['entry_id' => 1002],
    ['entry_id' => 1003],
  ];

  // 2. بناء الـ Mocks
  $cms = Mockery::mock(CMSApiClient::class);
  $repository = Mockery::mock(OfferRepositoryInterface::class);

  // توقع استدعاء تابع reEvaluate ثلاث مرات (مرة لكل عنصر)
  $repository->shouldReceive('reEvaluate')
    ->times(3)
    ->with(Mockery::on(function ($id) {
      return in_array($id, [1001, 1002, 1003]);
    }));

  $action = new ReEvaluateEntryPricesAction($cms, $repository);

  // 3. التنفيذ
  $action->execute($entries);

  // التحقق يتم تلقائياً بواسطة Mockery Expectations
});

it('defines the correct circuit breaker service name for re-evaluation', function () {
  $cms = Mockery::mock(CMSApiClient::class);
  $repository = Mockery::mock(OfferRepositoryInterface::class);

  $action = new class($cms, $repository) extends ReEvaluateEntryPricesAction {
    public function getServiceName(): string
    {
      return $this->circuitServiceName();
    }
  };

  expect($action->getServiceName())->toBe('offer.reEvalutePrices');
});
