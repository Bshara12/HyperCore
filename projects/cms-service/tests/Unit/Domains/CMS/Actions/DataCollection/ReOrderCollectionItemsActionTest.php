<?php

namespace Tests\Unit\Domains\CMS\Actions\DataCollection;

use App\Domains\CMS\Actions\DataCollection\ReOrderCollectionItemsAction;
use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Models\DataCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;

uses(RefreshDatabase::class);

afterEach(function () {
  Mockery::close();
});

test('it reorders items, clears cache, and returns result', function () {
  // 1. تجهيز الـ DTO
  $dto = new \stdClass();
  $dto->collectionSlug = 'test-collection';
  $dto->items = [1, 2, 3]; // مصفوفة الترتيب الجديد

  // 2. تجهيز الموديل الحقيقي لضمان تطابق النوع
  $collection = new DataCollection();
  $collection->id = 55;

  // 3. تجهيز الـ Mock للـ Repository
  $repoMock = Mockery::mock(DataCollectionRepositoryInterface::class);

  // محاكاة جلب المجموعة
  $repoMock->shouldReceive('getBySlug')
    ->once()
    ->with($dto->collectionSlug)
    ->andReturn($collection);

  // محاكاة عملية إعادة الترتيب وإرجاع نتيجة (مثلاً true)
  $repoMock->shouldReceive('reOrderItems')
    ->once()
    ->with($collection->id, $dto->items)
    ->andReturn(true);

  // 4. تهيئة الـ Cache Spy
  Cache::spy();

  // 5. التنفيذ
  $action = new ReOrderCollectionItemsAction($repoMock);
  $result = $action->execute($dto);

  // 6. التأكيدات
  // التأكد من مسح مفاتيح الكاش المحددة فقط
  Cache::shouldHaveReceived('forget')->with(CacheKeys::collectionItems($collection->id));
  Cache::shouldHaveReceived('forget')->with(CacheKeys::collectionById($collection->id));

  // التأكد من أن الـ Action أعاد النتيجة المتوقعة
  expect($result)->toBe(true);
});
