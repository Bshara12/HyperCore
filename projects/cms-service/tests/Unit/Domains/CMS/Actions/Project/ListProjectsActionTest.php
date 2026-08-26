<?php

namespace Tests\Unit\Domains\CMS\Actions\Project;

use App\Domains\CMS\Actions\Project\ListProjectsAction;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Domains\Core\Services\CircuitBreakerService;
use App\Support\ActingUser;
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

  Cache::flush();
});

afterEach(function () {
  Mockery::close();
});

/** يضع هوية المتصل حيث يقرؤها ActingUser: على خصائص الطلب لا في مدخلاته. */
function actAsProjectLister(?int $id, array $roles = []): void
{
  request()->attributes->set('auth_user', $id === null ? null : [
    'id' => $id,
    'roles' => $roles,
  ]);
}

test('a platform operator gets every project, cached under the global key', function () {
  actAsProjectLister(1, [['name' => ActingUser::ROLE_HYPER_CORE, 'project_id' => null]]);

  $projects = collect(['Project 1', 'Project 2']);

  $repoMock = Mockery::mock(ProjectRepositoryInterface::class);
  $repoMock->shouldReceive('all')->once()->andReturn($projects);
  $repoMock->shouldNotReceive('allForUser');

  $result = (new ListProjectsAction($repoMock))->execute();

  expect($result)->toBeInstanceOf(Collection::class)
    ->and($result)->toHaveCount(2)
    ->and($result)->toBe($projects)
    ->and(Cache::get(CacheKeys::allProjects()))->toBe($projects);
});

/*
| القائمة الكاملة كانت تُخدَّم لكل متصل تحت مفتاح واحد مشترك، وفيها الـ
| public_id الذي تُنطَّق به كل طلبات الـ CMS — أي مسار حيّ إلى بيانات مستأجر
| آخر. فالنطاق هنا ليس تفصيلاً في العرض، والاختبار يحرسه لا يفترضه.
*/
test('an ordinary caller only gets the projects scoped to them', function () {
  actAsProjectLister(55, [['name' => 'editor', 'pivot' => ['project_id' => 7]]]);

  $scoped = collect(['Project 7']);

  $repoMock = Mockery::mock(ProjectRepositoryInterface::class);
  $repoMock->shouldReceive('allForUser')->once()->with(55, [7])->andReturn($scoped);
  $repoMock->shouldNotReceive('all');

  $result = (new ListProjectsAction($repoMock))->execute();

  expect($result)->toBe($scoped)
    ->and(Cache::get(CacheKeys::userProjects(55, [7])))->toBe($scoped)
    ->and(Cache::has(CacheKeys::allProjects()))->toBeFalse();
});

test('an unidentifiable caller gets nothing rather than everything', function () {
  actAsProjectLister(null);

  $repoMock = Mockery::mock(ProjectRepositoryInterface::class);
  $repoMock->shouldNotReceive('all');
  $repoMock->shouldNotReceive('allForUser');

  expect((new ListProjectsAction($repoMock))->execute())->toBeEmpty();
});
