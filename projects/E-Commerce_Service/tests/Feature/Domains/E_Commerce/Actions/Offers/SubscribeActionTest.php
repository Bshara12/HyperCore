<?php

use App\Domains\E_Commerce\Actions\Offers\SubscribeAction;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use App\Domains\E_Commerce\DTOs\Offers\SubscribeDTO;
use App\Services\CMS\CMSApiClient;
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

it('subscribes a user to an offer by fetching collection id from CMS', function () {
  $slug = 'exclusive-newsletter-offer';
  $collectionId = 456;

  $dto = new SubscribeDTO(
    collectionSlug: $slug,
    code: 'WELCOME2026',
    user_id: 10,
    project_id: 1
  );

  $repository = Mockery::mock(OfferRepositoryInterface::class);
  $cmsClient = Mockery::mock(CMSApiClient::class);

  $cmsClient->shouldReceive('getCollectionBySlug')
    ->once()
    ->with($slug)
    ->andReturn(['id' => $collectionId]);

  // التأكد من أن التابع subscribe استُدعي فعلاً بالبارامترات الصحيحة
  $repository->shouldReceive('subscribe')
    ->once()
    ->with($collectionId, $dto)
    ->andReturn(true);

  $action = new SubscribeAction($repository, $cmsClient);

  $action->execute($dto);
});

it('defines the correct circuit breaker service name for subscription', function () {
  $repository = Mockery::mock(OfferRepositoryInterface::class);
  $cmsClient = Mockery::mock(CMSApiClient::class);

  $action = new class($repository, $cmsClient) extends SubscribeAction {
    public function getServiceName(): string
    {
      return $this->circuitServiceName();
    }
  };

  expect($action->getServiceName())->toBe('offer.subscribe');
});
