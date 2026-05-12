<?php

use App\Domains\E_Commerce\Read\Actions\Offers\IndexOffersAction;
use App\Domains\E_Commerce\Repositories\Interfaces\Offers\OfferRepositoryInterface;
use App\Services\CMS\CMSApiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

beforeEach(function () {
  // 1. بناء جدول الـ Circuit Breaker بالكامل لتفادي أي QueryException
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

it('fetches offers with collection data and caches them correctly', function () {
  $projectId = 1;

  // 2. إعداد بيانات العروض (نستخدم Array Access متوافق مع Eloquent Model أو Fluent)
  // نستخدم Fluent لجعل المصفوفة تبدو ككائن يدعم $offer['collection']
  $offer1 = new \Illuminate\Support\Fluent(['id' => 1, 'name' => 'Offer 1', 'collection_id' => 101]);
  $offer2 = new \Illuminate\Support\Fluent(['id' => 2, 'name' => 'Offer 2', 'collection_id' => 102]);

  $mockOffers = new EloquentCollection([$offer1, $offer2]);

  $mockCollection = ['id' => 101, 'title' => 'Electronics Collection'];

  // 3. بناء الـ Mocks
  $repository = Mockery::mock(OfferRepositoryInterface::class);
  $cmsClient = Mockery::mock(CMSApiClient::class);

  // توقعات الـ Repository: يجب أن يعيد Eloquent Collection حصراً
  $repository->shouldReceive('getProjectOffers')
    ->once()
    ->with($projectId)
    ->andReturn($mockOffers);

  // توقعات الـ CMS: جلب الكولكشن لكل عرض
  $cmsClient->shouldReceive('getCollectionById')
    ->twice()
    ->andReturn($mockCollection);

  $action = new IndexOffersAction($cmsClient, $repository);

  // 4. التنفيذ الأول (يملأ الكاش ويستدعي الـ Mocks)
  $result1 = $action->execute($projectId);

  // 5. التنفيذ الثاني (يجب أن يأتي من الكاش دون استدعاء الـ Mocks)
  $result2 = $action->execute($projectId);

  // التحقق من صحة البيانات المدمجة
  expect($result1)->toBeInstanceOf(EloquentCollection::class);
  expect($result1[0]['collection'])->toBeArray();
  expect($result1[0]['collection']['title'])->toBe('Electronics Collection');

  // التأكد من أن الكاش يعمل (النتيجة الثانية مطابقة تماماً للأولى)
  expect($result2)->toEqual($result1);
});

it('has the correct circuit service name', function () {
  $repository = Mockery::mock(OfferRepositoryInterface::class);
  $cmsClient = Mockery::mock(CMSApiClient::class);

  $action = new class($cmsClient, $repository) extends IndexOffersAction {
    public function getServiceName(): string
    {
      return $this->circuitServiceName();
    }
  };

  expect($action->getServiceName())->toBe('offer.index');
});
