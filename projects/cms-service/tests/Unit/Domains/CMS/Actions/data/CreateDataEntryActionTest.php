<?php

namespace Tests\Unit\Domains\CMS\Actions\data;

use App\Domains\CMS\Actions\data\CreateDataEntryAction;
use App\Domains\CMS\Repositories\Interface\DataEntryRepositoryInterface;
use App\Domains\Subscription\Services\DomainEventService;
use App\Events\SystemLogEvent;
use App\Models\DataType;
use Illuminate\Support\Facades\Event;
use Mockery;

test('it creates data entry, dispatches domain event and logs system event', function () {
  // 1. Arrange: تجهيز الـ Mocks و الـ Model
  $repo = Mockery::mock(DataEntryRepositoryInterface::class);
  $domainEventService = Mockery::mock(DomainEventService::class);

  // إيقاف التنفيذ الفعلي للأحداث (Events)
  Event::fake();

  // الحل: قم بإنشاء كائن حقيقي من الموديل بدلاً من الـ Mock
  $dataType = new DataType();
  $dataType->id = 10;
  $dataType->slug = 'article';

  $projectId = 1;
  $userId = 99;
  $slug = 'my-first-post';
  $expectedResult = ['id' => 1, 'slug' => 'my-first-post'];

  // التوقعات (Expectations)
  // توقع استدعاء الـ DomainEventService
  $domainEventService->shouldReceive('dispatch')
    ->once()
    ->with($userId, $projectId, 'article.create');

  // توقع استدعاء الـ Repository للإنشاء
  $repo->shouldReceive('create')
    ->once()
    ->with([
      'project_id' => $projectId,
      'data_type_id' => 10,
      'slug' => $slug,
      'status' => 'draft',
      'created_by' => $userId,
    ])
    ->andReturn($expectedResult);

  // 2. Act: التنفيذ
  $action = new CreateDataEntryAction($repo, $domainEventService);
  $result = $action->execute($projectId, $dataType, $slug, $userId);

  // 3. Assert: التأكيد
  expect($result)->toBe($expectedResult);

  // التأكد من أن SystemLogEvent تم إطلاقه
  Event::assertDispatched(SystemLogEvent::class, function ($event) use ($userId) {
    return $event->module === 'cms' &&
      $event->eventType === 'create_data' &&
      $event->userId === $userId;
  });
});
