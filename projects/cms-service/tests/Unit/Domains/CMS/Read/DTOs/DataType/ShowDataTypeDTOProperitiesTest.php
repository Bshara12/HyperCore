<?php

use App\Domains\CMS\Read\DTOs\DataType\ShowDataTypeDTOProperities;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use Illuminate\Support\Facades\App;

test('it creates DTO correctly by fetching project ID from repository', function () {
  // 1. محاكاة الـ currentProject
  $mockProject = new class {
    public $public_id = 'test-proj-123';
  };
  App::instance('currentProject', $mockProject);

  // 2. محاكاة الـ Repository
  // التغيير هنا: استخدم كائن من نوع المودل الحقيقي بدلاً من stdClass
  $project = new \App\Models\Project();
  $project->id = 77;

  $repoMock = Mockery::mock(ProjectRepositoryInterface::class);
  $repoMock->shouldReceive('findByKey')
    ->with('test-proj-123')
    ->once()
    ->andReturn($project); // الآن النوع صحيح ومطابق

  App::instance(ProjectRepositoryInterface::class, $repoMock);

  // 3. التنفيذ
  $dto = \App\Domains\CMS\Read\DTOs\DataType\ShowDataTypeDTOProperities::fromRequest('my-slug');

  // 4. التحقق
  expect($dto->project_id)->toBe(77)
    ->and($dto->slug)->toBe('my-slug');
});
