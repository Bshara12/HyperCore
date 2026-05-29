<?php

namespace Tests\Unit\Domains\CMS\Actions\Project;

use App\Domains\CMS\Actions\Project\ListProjectsAction;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Domains\Core\Services\CircuitBreakerService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Mockery;

beforeEach(function () {
  // محاكاة الـ Circuit Breaker الأساسي لأن الدالة تستخدم $this->run()
  $this->mock(CircuitBreakerService::class, function ($mock) {
    $mock->shouldReceive('canProceed')
      ->once()
      ->with('project.index') // التأكد من الاسم الصحيح
      ->andReturn(true);
    $mock->shouldReceive('reportSuccess')->andReturn(true);
  });
});

afterEach(function () {
  Mockery::close();
});

test('it returns projects from cache or database', function () {
  // 1. تجهيز البيانات المتوقعة
  $projects = collect(['Project 1', 'Project 2']);

  // 2. Mock للـ Repository
  $repoMock = Mockery::mock(ProjectRepositoryInterface::class);

  // 3. اختبار الـ Cache
  // نستخدم shouldReceive لتوقع استدعاء remember
  Cache::shouldReceive('remember')
    ->once()
    ->with(
      CacheKeys::allProjects(), // المفتاح
      CacheKeys::TTL_LONG,      // المدة
      Mockery::type('callable') // الكلوجر (Closure)
    )
    ->andReturnUsing(function ($key, $ttl, $callback) use ($repoMock, $projects) {
      // هنا نتحقق من أن الـ callback يقوم باستدعاء الـ repository
      $repoMock->shouldReceive('all')->once()->andReturn($projects);
      return $callback(); // تنفيذ الـ Closure وإرجاع النتيجة
    });

  // 4. التنفيذ
  $action = new ListProjectsAction($repoMock);
  $result = $action->execute();

  // 5. التأكيدات
  expect($result)->toBeInstanceOf(Collection::class)
    ->and($result)->toHaveCount(2)
    ->and($result)->toBe($projects);
});
