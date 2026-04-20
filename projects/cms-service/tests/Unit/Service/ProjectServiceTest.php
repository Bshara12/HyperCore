<?php

namespace Tests\Unit\CMS\Services;

use Tests\TestCase;
use Mockery;
use App\Models\Project;
use Illuminate\Support\Collection;
use App\Domains\CMS\Services\ProjectService;
use App\Domains\CMS\DTOs\CreateProjectDTO;
use App\Domains\CMS\DTOs\Project\UpdateProjectDTO;
use App\Domains\CMS\Actions\Project\CreateProjectAction;
use App\Domains\CMS\Actions\Project\ListProjectsAction;
use App\Domains\CMS\Actions\Project\ShowProjectAction;
use App\Domains\CMS\Actions\Project\UpdateProjectAction;
use App\Domains\CMS\DTOs\Project\DeleteProjectAction;
use PHPUnit\Framework\Attributes\Test;

class ProjectServiceTest extends TestCase
{
  protected function tearDown(): void
  {
    Mockery::close();
    parent::tearDown();
  }

  private function makeService(
    $create = null,
    $update = null,
    $show = null,
    $list = null,
    $delete = null
  ) {
    return new ProjectService(
      $create ?? Mockery::mock(CreateProjectAction::class),
      $update ?? Mockery::mock(UpdateProjectAction::class),
      $show ?? Mockery::mock(ShowProjectAction::class),
      $list ?? Mockery::mock(ListProjectsAction::class),
      $delete ?? Mockery::mock(DeleteProjectAction::class),
    );
  }

  #[Test]
  public function it_calls_create_action_and_returns_project()
  {
    $dto = Mockery::mock(CreateProjectDTO::class);
    $project = Mockery::mock(Project::class);

    $createAction = Mockery::mock(CreateProjectAction::class);
    $createAction->shouldReceive('execute')
      ->once()
      ->with($dto)
      ->andReturn($project);

    $service = $this->makeService(create: $createAction);

    $result = $service->create($dto);

    $this->assertSame($project, $result);
  }

  #[Test]
  public function it_calls_update_action_and_returns_project()
  {
    $project = Mockery::mock(Project::class);
    $dto = Mockery::mock(UpdateProjectDTO::class);

    $updateAction = Mockery::mock(UpdateProjectAction::class);
    $updateAction->shouldReceive('execute')
      ->once()
      ->with($project, $dto)
      ->andReturn($project);

    $service = $this->makeService(update: $updateAction);

    $result = $service->update($project, $dto);

    $this->assertSame($project, $result);
  }

  #[Test]
  public function it_calls_show_action_and_returns_project()
  {
    $project = Mockery::mock(Project::class);

    $showAction = Mockery::mock(ShowProjectAction::class);
    $showAction->shouldReceive('execute')
      ->once()
      ->with($project)
      ->andReturn($project);

    $service = $this->makeService(show: $showAction);

    $result = $service->show($project);

    $this->assertSame($project, $result);
  }

  #[Test]
  public function it_calls_list_action_and_returns_collection()
  {
    $collection = new Collection([1, 2, 3]);

    $listAction = Mockery::mock(ListProjectsAction::class);
    $listAction->shouldReceive('execute')
      ->once()
      ->andReturn($collection);

    $service = $this->makeService(list: $listAction);

    $result = $service->list();

    $this->assertSame($collection, $result);
  }

  #[Test]
  public function it_calls_delete_action_once()
  {
    $project = Mockery::mock(Project::class);

    $deleteAction = Mockery::mock(DeleteProjectAction::class);
    $deleteAction->shouldReceive('execute')
      ->once()
      ->with($project);

    $service = $this->makeService(delete: $deleteAction);

    $result = $service->delete($project);

    $this->assertNull($result); // void
  }

  #[Test]
  public function it_propagates_exceptions_from_actions()
  {
    $this->expectException(\Exception::class);

    $project = Mockery::mock(Project::class);

    $deleteAction = Mockery::mock(DeleteProjectAction::class);
    $deleteAction->shouldReceive('execute')
      ->once()
      ->andThrow(new \Exception('Delete failed'));

    $service = $this->makeService(delete: $deleteAction);

    $service->delete($project);
  }
}
