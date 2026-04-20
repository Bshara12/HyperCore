<?php

namespace Tests\Feature\Actions;

use App\Domains\CMS\Actions\DataType\RestoreDataTypeAction;
use App\Domains\CMS\Repositories\Eloquent\DataTypeRepositoryEloquent;
use App\Models\DataType;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestoreDataTypeActionTest extends TestCase
{
  use RefreshDatabase;

  /** @test */
  public function it_restores_soft_deleted_data_type_successfully()
  {
    $project = Project::factory()->create();

    $dataType = DataType::factory()->create([
      'project_id' => $project->id,
    ]);

    // soft delete
    $dataType->delete();

    $this->assertSoftDeleted('data_types', [
      'id' => $dataType->id,
    ]);

    $repo = new DataTypeRepositoryEloquent();
    $action = new RestoreDataTypeAction($repo);

    $action->execute($dataType->id);

    $this->assertDatabaseHas('data_types', [
      'id' => $dataType->id,
      'deleted_at' => null,
    ]);
  }

  /** @test */
  // public function it_throws_error_if_data_type_not_found()
  // {
  //   $repo = new DataTypeRepositoryEloquent();
  //   $action = new RestoreDataTypeAction($repo);

  //   $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

  //   $action->execute(999); // ID غير موجود
  // }

  /** @test */
  public function it_calls_repository_restore_method()
  {
    $mockRepo = \Mockery::mock(DataTypeRepositoryEloquent::class);

    $mockRepo->shouldReceive('restore')
      ->once()
      ->with(10);

    $action = new class($mockRepo) extends RestoreDataTypeAction {
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
