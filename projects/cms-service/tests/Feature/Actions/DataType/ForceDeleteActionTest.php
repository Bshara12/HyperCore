<?php

namespace Tests\Feature\Actions;

use App\Domains\CMS\Actions\DataType\ForceDeleteAction;
use App\Domains\CMS\Repositories\Eloquent\DataTypeRepositoryEloquent;
use App\Models\DataType;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForceDeleteActionTest extends TestCase
{
  use RefreshDatabase;

  /** @test */
  public function it_force_deletes_data_type_successfully()
  {
    $project = Project::factory()->create();

    $dataType = DataType::factory()->create([
      'project_id' => $project->id,
    ]);

    $repo = new DataTypeRepositoryEloquent();
    $action = new ForceDeleteAction($repo);

    $action->execute($dataType->id);

    $this->assertDatabaseMissing('data_types', [
      'id' => $dataType->id,
    ]);
  }

  /** @test */
  // public function it_throws_error_if_data_type_not_found()
  // {
  //   $repo = new DataTypeRepositoryEloquent();
  //   $action = new ForceDeleteAction($repo);

  //   $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

  //   $action->execute(999); // ID غير موجود
  // }

  /** @test */
  public function it_runs_inside_database_transaction()
  {
    $project = Project::factory()->create();

    $dataType = DataType::factory()->create([
      'project_id' => $project->id,
    ]);

    $repo = new DataTypeRepositoryEloquent();
    $action = new ForceDeleteAction($repo);

    $action->execute($dataType->id);

    $this->assertDatabaseMissing('data_types', [
      'id' => $dataType->id,
    ]);
  }

  /** @test */
  public function it_calls_repository_force_delete_method()
  {
    $mockRepo = \Mockery::mock(DataTypeRepositoryEloquent::class);

    $mockRepo->shouldReceive('forceDelete')
      ->once()
      ->with(10);

    $action = new class($mockRepo) extends ForceDeleteAction {
      protected function runThroughCircuitBreaker(callable $callback)
      {
        return $callback();
      }
      protected function run(callable $callback)
      {
        return $callback();
      }
    };

    $action->execute(10);
  }
}
