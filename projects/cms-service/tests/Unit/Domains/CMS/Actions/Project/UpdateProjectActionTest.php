<?php

namespace Tests\Unit\Domains\CMS\Actions\Project;

use App\Domains\CMS\Actions\Project\UpdateProjectAction;
use App\Domains\CMS\DTOs\Project\UpdateProjectDTO;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Domains\Core\Services\CircuitBreakerService;
use App\Events\SystemLogEvent;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Mockery;

beforeEach(function () {
  // محاكاة الـ Circuit Breaker
  $this->mock(CircuitBreakerService::class, function ($mock) {
    $mock->shouldReceive('canProceed')
      ->once()
      ->with('project.update')
      ->andReturn(true);
    $mock->shouldReceive('reportSuccess')->andReturn(true);
  });
});

afterEach(function () {
  Mockery::close();
});

test('it updates project, logs event, and clears cache', function () {
  // 1. تجهيز المعطيات
  $project = new Project();
  $project->id = 101;
  $project->owner_id = 55;

  $dto = new UpdateProjectDTO(
    name: 'Updated Project Name',
    supportedLanguages: ['ar', 'en'],
    enabledModules: ['cms']
  );

  $updatedProject = new Project(['name' => 'Updated Project Name']);

  // 2. Mock للـ Repository
  $repoMock = Mockery::mock(ProjectRepositoryInterface::class);
  $repoMock->shouldReceive('update')
    ->once()
    ->with($project, $dto->toArray())
    ->andReturn($updatedProject);

  // 3. تهيئة الـ Fakes
  Event::fake();
  Cache::spy();

  // 4. التنفيذ
  $action = new UpdateProjectAction($repoMock);
  $result = $action->execute($project, $dto);

  // 5. التأكيدات
  // التأكد من إطلاق الحدث
  Event::assertDispatched(SystemLogEvent::class, function ($event) use ($project) {
    return $event->eventType === 'project_updated'
      && $event->entityId === $project->id
      && $event->userId === $project->owner_id;
  });

  // التأكد من مسح الكاش الصحيح (لكل من المشروع المفرد والكل)
  Cache::shouldHaveReceived('forget')->with(CacheKeys::project($project->id));
  Cache::shouldHaveReceived('forget')->with(CacheKeys::allProjects());

  // التأكد من القيمة المرجعة
  expect($result)->toBe($updatedProject);
});
