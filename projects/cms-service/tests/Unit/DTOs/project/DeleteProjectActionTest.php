<?php

namespace Tests\Unit\CMS\Actions\Project;

use Tests\TestCase;
use Mockery;
use App\Models\Project;
use App\Domains\CMS\DTOs\Project\DeleteProjectAction;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;

class DeleteProjectActionTest extends TestCase
{
  protected function tearDown(): void
  {
    Mockery::close();
    parent::tearDown();
  }
  #[Test]
  public function it_calls_repository_delete_once()
  {
    $project = Mockery::mock(Project::class);

    $repository = Mockery::mock(ProjectRepositoryInterface::class);
    $repository
      ->shouldReceive('delete')
      ->once()
      ->with($project);

    $action = new DeleteProjectAction($repository);

    $result = $action->execute($project);

    $this->assertNull($result); // لأنه void
  }
  #[Test]
  public function it_passes_the_correct_project_to_repository()
  {
    $project = Mockery::mock(Project::class);

    $repository = Mockery::mock(ProjectRepositoryInterface::class);
    $repository
      ->shouldReceive('delete')
      ->once()
      ->withArgs(function ($arg) use ($project) {
        return $arg === $project;
      });

    $action = new DeleteProjectAction($repository);

    $action->execute($project);

    $this->addToAssertionCount(1); // 👈 حل مشكلة Risky
  }

  #[Test]
  public function it_propagates_exception_from_repository()
  {
    $this->expectException(\Exception::class);

    $project = Mockery::mock(Project::class);

    $repository = Mockery::mock(ProjectRepositoryInterface::class);
    $repository
      ->shouldReceive('delete')
      ->once()
      ->andThrow(new \Exception('Delete failed'));

    $action = new DeleteProjectAction($repository);

    $action->execute($project);
  }
}
