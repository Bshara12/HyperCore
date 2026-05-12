<?php

namespace Tests\Unit\Domains\Booking\Read\Actions;

use App\Domains\Booking\Read\Actions\ShowResourceAction;
use App\Domains\Booking\Repositories\Interface\ResourceRepositoryInterface;
use App\Domains\Booking\Support\CacheKeys;
use App\Models\Resource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

test('it returns resource from repository and caches it', function () {
  $resourceId = 10;

  // الحل 1: تعيين المعرف بشكل صريح
  $mockResource = new Resource();
  $mockResource->id = $resourceId;
  $mockResource->name = 'Meeting Room';

  // أو الحل 2: استخدام forceFill إذا كنت تفضل سطر واحد
  // $mockResource = (new Resource())->forceFill(['id' => $resourceId, 'name' => 'Meeting Room']);

  $repository = Mockery::mock(ResourceRepositoryInterface::class);

  $repository->shouldReceive('findById')
    ->once()
    ->with($resourceId)
    ->andReturn($mockResource);

  $action = new ShowResourceAction($repository);

  $result1 = $action->execute($resourceId);
  $result2 = $action->execute($resourceId);

  // الآن لن يكون null
  expect($result1->id)->toBe($resourceId);
  expect($result2->id)->toBe($resourceId);

  $expectedKey = CacheKeys::resource($resourceId);
  expect(Cache::has($expectedKey))->toBeTrue();
});

test('it returns null if resource not found', function () {
    $resourceId = 999;

    $repository = Mockery::mock(ResourceRepositoryInterface::class);
    
    // نكتفي بالتأكد من أن المستودع يُستدعى 
    // إذا كنت تريد التأكد من الكاش مع null، يجب أن يكون الاختبار استدعاءً واحداً
    $repository->shouldReceive('findById')
        ->once() 
        ->with($resourceId)
        ->andReturn(null);

    $action = new ShowResourceAction($repository);

    // التنفيذ مرة واحدة فقط
    $result = $action->execute($resourceId);

    expect($result)->toBeNull();
    
    // ملاحظة تقنية: لارافيل في درايفر 'file' و 'database' لا يخزن null كقيمة صالحة 
    // في Cache::remember، لذا الاستدعاء الثاني سيحاول دائماً الاتصال بالقاعدة.
    // إذا كنت تريد تخزين الـ null، يجب تعديل الـ Action ليرجع كائن فارغ أو استخدام Cache::put يدوياً.
});