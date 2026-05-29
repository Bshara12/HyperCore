<?php

namespace Tests\Unit\Domains\CMS\Actions\Project;

use App\Domains\CMS\Actions\Project\CreateProjectAction;
use App\Domains\CMS\DTOs\CreateProjectDTO;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Events\SystemLogEvent;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event; // ✅ تأكد من وجود الـ Facade
use Mockery;

beforeEach(function () {
  // 🔥 الحل السحري: تزييف الأحداث لكل الاختبارات في هذا الملف لمنع اتصال RabbitMQ
  Event::fake();

  // محاكاة الـ Circuit Breaker كما تعودنا في اختباراتك السابقة
  $this->mock(\App\Domains\Core\Services\CircuitBreakerService::class, function ($mock) {
    $mock->shouldReceive('canProceed')->andReturn(true)->byDefault();
    $mock->shouldReceive('reportSuccess')->andReturn(true)->byDefault();
    $mock->shouldReceive('reportFailure')->andReturn(true)->byDefault();
  });
});

afterEach(function () {
  Mockery::close();
});

test('it creates project, logs event, and clears cache', function () {
  // 1. تجهيز المعطيات
  $dto = new CreateProjectDTO(
    name: 'My New Project',
    ownerId: 1,
    supportedLanguages: ['ar', 'en'],
    enabledModules: ['cms', 'crm']
  );

  $project = new Project(['name' => 'My New Project']);

  // 2. Mock الـ Repository
  $repoMock = Mockery::mock(ProjectRepositoryInterface::class);

  // التأكد من أن الـ Action أضاف الـ public_id والـ slug قبل الإرسال للـ repo
  $repoMock->shouldReceive('create')
    ->once()
    ->with(Mockery::on(function ($data) {
      return isset($data['public_id']) &&
        $data['slug'] === 'my-new-project' &&
        $data['name'] === 'My New Project';
    }))
    ->andReturn($project);

  // 3. تهيئة الـ Spy للكاش (تم نقل Event::fake للأعلى)
  Cache::spy();

  // 4. التنفيذ
  $action = new CreateProjectAction($repoMock);
  $result = $action->execute($dto);

  // 5. التأكيدات
  expect($result)->toBe($project);

  // التأكد من مسح الكاش الصحيح
  Cache::shouldHaveReceived('forget')->with(CacheKeys::allProjects());

  // التأكد من إطلاق الحدث (SystemLogEvent)
  Event::assertDispatched(SystemLogEvent::class, function ($event) use ($dto) {
    return $event->eventType === 'audit' &&
      $event->userId === $dto->ownerId &&
      $event->entityType === 'project';
  });
});

test('it uses the correct circuit breaker service name', function () {
    // 1. إعادة تعريف الـ Mock لنتأكد من الـ argument الذي يمرر للدالة
    $this->mock(\App\Domains\Core\Services\CircuitBreakerService::class, function ($mock) {
        $mock->shouldReceive('canProceed')
            ->once()
            ->with('project.create') // هنا نتحقق من أن الاسم هو 'project.create'
            ->andReturn(true);
        
        $mock->shouldReceive('reportSuccess')->andReturn(true);
    });

    // 2. إعداد الـ DTO والـ Repository كما في الاختبار السابق
    $dto = new CreateProjectDTO(
        name: 'Test Project',
        ownerId: 1,
        supportedLanguages: ['ar'],
        enabledModules: []
    );
    
    $repoMock = Mockery::mock(ProjectRepositoryInterface::class);
    $repoMock->shouldReceive('create')->once()->andReturn(new Project());

    // 3. التنفيذ (سيعمل الآن بنجاح لأن الأحداث مزيفة تلقائياً)
    $action = new CreateProjectAction($repoMock);
    $action->execute($dto);
});