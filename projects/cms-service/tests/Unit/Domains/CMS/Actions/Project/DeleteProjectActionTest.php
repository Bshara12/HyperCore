<?php

namespace Tests\Unit\Domains\CMS\Actions\Project;

use App\Domains\CMS\Actions\Project\DeleteProjectAction;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Events\SystemLogEvent;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Mockery;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

afterEach(function () {
  Mockery::close();
});

test('it deletes project and logs the audit event', function () {
  // 1. تجهيز الـ Project (نحتاج ID و owner_id للحدث)
  $project = new Project();
  $project->id = 101;
  $project->owner_id = 55;

  // 2. Mock للـ Repository
  $repoMock = Mockery::mock(ProjectRepositoryInterface::class);

  // نتوقع أن دالة الحذف ستُستدعى مرة واحدة مع الكائن $project
  $repoMock->shouldReceive('delete')
    ->once()
    ->with($project);

  // 3. تزييف الـ Event وتسخين مفاتيح الكاش التي يُنتظر إبطالها
  Event::fake();
  Cache::flush();
  Cache::put(CacheKeys::project($project->id), 'stale');
  $versionBefore = CacheKeys::projectListVersion();

  // 4. التنفيذ
  $action = new DeleteProjectAction($repoMock);
  $action->execute($project);

  // 5. التأكيدات
  // التأكد من أن الحدث تم إطلاقه بالبيانات الصحيحة
  Event::assertDispatched(SystemLogEvent::class, function ($event) use ($project) {
    return $event->eventType === 'audit'
      && $event->entityId === $project->id
      && $event->userId === $project->owner_id
      && $event->entityType === 'delete project';
  });

  /*
   | مفاتيح المشروع نفسه تُمسح واحداً واحداً، أمّا القوائم فتُقاعَد برفع الختم:
   | مفتاح لكل مستخدم يعني تعداد من يملك مدخلاً ساخناً، وهو متعذّر.
   */
  expect(Cache::has(CacheKeys::project($project->id)))->toBeFalse()
    ->and(CacheKeys::projectListVersion())->toBeGreaterThan($versionBefore);
});
