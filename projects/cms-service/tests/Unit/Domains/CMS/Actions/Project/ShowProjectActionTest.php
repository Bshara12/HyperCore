<?php

namespace Tests\Unit\Domains\CMS\Actions\Project;

use App\Domains\CMS\Actions\Project\ShowProjectAction;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Domains\Core\Services\CircuitBreakerService;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;
use Mockery;

beforeEach(function () {
  // محاكاة الـ Circuit Breaker لأننا نستخدم $this->run() في الكلاس الأساسي
  $this->mock(CircuitBreakerService::class, function ($mock) {
    $mock->shouldReceive('canProceed')
      ->once()
      ->with('project.show') // التحقق من الاسم الصحيح
      ->andReturn(true);
    $mock->shouldReceive('reportSuccess')->andReturn(true);
  });
});

afterEach(function () {
  Mockery::close();
});

test('it retrieves project from cache or repository', function () {
  // 1. التعديل هنا: إنشاء كائن وتعريف الـ id يدوياً
  $project = new Project();
  $project->id = 123; // هذا يضمن أن القيمة موجودة بغض النظر عن الـ fillable

  // 2. Mock للـ Repository
  $repoMock = Mockery::mock(ProjectRepositoryInterface::class);

  // 3. اختبار الـ Cache
  Cache::shouldReceive('remember')
    ->once()
    ->with(
      CacheKeys::project($project->id), // الآن $project->id تساوي 123 ولن تكون null
      CacheKeys::TTL_LONG,
      Mockery::type('callable')
    )
    ->andReturnUsing(function ($key, $ttl, $callback) use ($repoMock, $project) {
      $repoMock->shouldReceive('find')->once()->with($project)->andReturn($project);
      return $callback();
    });

  // 4. التنفيذ
  $action = new ShowProjectAction($repoMock);
  $result = $action->execute($project);

  // 5. التأكيدات
  expect($result)->toBe($project);
});
