<?php

namespace Tests\Unit\Domains\Booking\Read\Actions;

use App\Domains\Booking\Read\Actions\IndexResourcesAction;
use App\Domains\Booking\Repositories\Interface\ResourceRepositoryInterface;
use App\Domains\Booking\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Collection as EloquentCollection; // <--- تأكد من هذا السطر
use Mockery;

uses(RefreshDatabase::class);

test('it caches and returns resources for a regular user', function () {
  $projectId = 1;
  $user = [
    'id' => 50,
    'roles' => [['name' => 'user']]
  ];

  // استخدام Eloquent Collection بدلاً من collect()
  $mockData = new EloquentCollection([['id' => 1, 'name' => 'Resource A']]);

  $repository = Mockery::mock(ResourceRepositoryInterface::class);

  $repository->shouldReceive('listForUser')
    ->once()
    ->with($projectId, 50)
    ->andReturn($mockData);

  $action = new IndexResourcesAction($repository);

  $action->execute($projectId, $user);
  $result = $action->execute($projectId, $user);

  expect($result)->toBe($mockData);
  expect(Cache::has(CacheKeys::resourcesForUser($projectId, 50)))->toBeTrue();
});

test('it caches and returns resources for an admin user (project-wide)', function () {
  $projectId = 1;
  $admin = [
    'id' => 1,
    'roles' => [['name' => 'admin']]
  ];

  // استخدام Eloquent Collection هنا أيضاً
  $mockData = new EloquentCollection([['id' => 1, 'name' => 'All Resources']]);

  $repository = Mockery::mock(ResourceRepositoryInterface::class);

  $repository->shouldReceive('listByProject')
    ->once()
    ->with($projectId)
    ->andReturn($mockData);

  $action = new IndexResourcesAction($repository);

  $action->execute($projectId, $admin);
  $result = $action->execute($projectId, $admin);

  expect($result)->toBe($mockData);
  expect(Cache::has(CacheKeys::resources($projectId)))->toBeTrue();
});
