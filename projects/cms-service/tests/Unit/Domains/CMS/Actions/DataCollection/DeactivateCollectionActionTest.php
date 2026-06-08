<?php

namespace Tests\Unit\Domains\CMS\Actions\DataCollection;

use App\Domains\CMS\Actions\DataCollection\DeactivateCollectionAction;
use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Events\SystemLogEvent;
use App\Domains\CMS\DTOs\DataCollection\DeactivateCollectionDTO; // 1. أضف هذا الاستيراد
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Mockery;

uses(RefreshDatabase::class);

afterEach(function () {
    Mockery::close();
});

test('it deactivates collection, clears cache, and dispatches system log event', function () {
    // 1. إنشاء نسخة حقيقية من الـ DTO بدلاً من stdClass
    $dto = new DeactivateCollectionDTO(
        project_id: 99,
        slug: 'test-collection',
        is_active: false
    );

    // 2. تجهيز الـ Mock للـ Repository
    $repoMock = Mockery::mock(DataCollectionRepositoryInterface::class);
    
    // الآن الـ mock يتوقع الـ DTO الصحيح
    $repoMock->shouldReceive('deactivate')
        ->once()
        ->with($dto); // تطابق تام

    Event::fake();
    
    Cache::shouldReceive('forget')
        ->once()
        ->with(CacheKeys::collection($dto->project_id, $dto->slug));
        
    Cache::shouldReceive('forget')
        ->once()
        ->with(CacheKeys::collections($dto->project_id));

    // 3. التنفيذ
    $action = new DeactivateCollectionAction($repoMock);
    $action->execute($dto);

    // 4. التأكيدات
    Event::assertDispatched(SystemLogEvent::class, function ($event) use ($dto) {
        return $event->module === 'cms' 
            && $event->eventType === 'deactivate_collection'
            && $event->entityId === $dto->slug;
    });
});