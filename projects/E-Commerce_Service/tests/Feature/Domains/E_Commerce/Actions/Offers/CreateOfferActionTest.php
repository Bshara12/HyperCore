<?php

use App\Domains\E_Commerce\Actions\Offers\CreateOfferAction;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use App\Services\CMS\CMSApiClient;
use App\Models\Offer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

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

it('creates an offer successfully and forgets cache', function () {
  Cache::shouldReceive('forget')->once();

  $dto = Mockery::mock();
  $dto->project_id = 1;
  $dto->shouldReceive('CollectionToArray')->andReturn(['name' => 'Summer Sale']);
  $dto->shouldReceive('OfferToArray')->andReturn(['discount' => 20]);

  $mockCollection = ['id' => 101, 'name' => 'Summer Sale'];
  $mockOfferModel = new Offer(['id' => 1, 'discount' => 20, 'collection_id' => 101]);

  $cmsClient = Mockery::mock(CMSApiClient::class);
  $repository = Mockery::mock(OfferRepositoryInterface::class);

  // استخدمنا any() أو atLeast() لأن الـ Circuit Breaker قد يتدخل
  $cmsClient->shouldReceive('createCollection')
    ->atLeast()->once()
    ->andReturn(['data' => $mockCollection]);

  $repository->shouldReceive('create')
    ->once()
    ->andReturn($mockOfferModel);

  $action = new CreateOfferAction($cmsClient, $repository);
  $result = $action->execute($dto);

  expect($result['collection']['id'])->toBe(101);
});

it('throws an exception if CMS collection creation fails', function () {
  $dto = Mockery::mock();
  $dto->shouldReceive('CollectionToArray')->andReturn([]);

  $cmsClient = Mockery::mock(CMSApiClient::class);
  $repository = Mockery::mock(OfferRepositoryInterface::class);

  // هنا المشكلة: الـ Circuit Breaker يعيد المحاولة عند الفشل
  // لذا نجعل الـ Mock يقبل استدعاءات متعددة (3 مرات كما ظهر في الخطأ)
  $cmsClient->shouldReceive('createCollection')
    ->times(3)
    ->andReturn(['error' => 'Invalid data']);

  $action = new CreateOfferAction($cmsClient, $repository);

  expect(fn() => $action->execute($dto))
    ->toThrow(\Exception::class, "Failed to create collection in CMS");
});

it('defines the correct circuit breaker name for creation', function () {
  $cmsClient = Mockery::mock(CMSApiClient::class);
  $repository = Mockery::mock(OfferRepositoryInterface::class);

  $action = new class($cmsClient, $repository) extends CreateOfferAction {
    public function getServiceName(): string
    {
      return $this->circuitServiceName();
    }
  };

  expect($action->getServiceName())->toBe('offer.create');
});
