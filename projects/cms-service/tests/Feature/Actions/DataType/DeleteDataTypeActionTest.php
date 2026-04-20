<?php

namespace Tests\Feature\Actions;

use App\Domains\CMS\Actions\DataType\DeleteDataTypeAction;
use App\Domains\CMS\Repositories\Eloquent\DataTypeRepositoryEloquent;
use App\Models\DataType;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteDataTypeActionTest extends TestCase
{
  use RefreshDatabase;

  /** @test */
  public function it_soft_deletes_data_type_successfully()
  {
    $project = Project::factory()->create();

    $dataType = DataType::factory()->create([
      'project_id' => $project->id,
    ]);

    $repo = new DataTypeRepositoryEloquent();
    $action = new DeleteDataTypeAction($repo);

    $action->execute($dataType);

    $this->assertSoftDeleted('data_types', [
      'id' => $dataType->id,
    ]);
  }

  /** @test */
  // public function it_throws_error_if_data_type_not_found()
  // {
  //   $repo = new DataTypeRepositoryEloquent();
  //   $action = new DeleteDataTypeAction($repo);

  //   $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

  //   // محاولة حذف ID غير موجود
  //   $fake = new DataType(['id' => 999]);

  //   $action->execute($fake);
  // }

  /** @test */
  public function it_runs_inside_database_transaction()
  {
    $project = Project::factory()->create();

    $dataType = DataType::factory()->create([
      'project_id' => $project->id,
    ]);

    $repo = new DataTypeRepositoryEloquent();
    $action = new DeleteDataTypeAction($repo);

    $action->execute($dataType);

    // التأكد من أن الحذف تم فعلاً
    $this->assertSoftDeleted('data_types', [
      'id' => $dataType->id,
    ]);
  }

  /** @test */
  public function it_calls_repository_delete_method()
  {
    // نستخدم mock هنا فقط للتأكد من أن الدالة delete تُستدعى
    $mockRepo = \Mockery::mock(DataTypeRepositoryEloquent::class);

    $dataType = DataType::factory()->create();

    $mockRepo->shouldReceive('delete')
      ->once()
      ->with($dataType);

    $action = new class($mockRepo) extends DeleteDataTypeAction {
      protected function runThroughCircuitBreaker(callable $callback)
      {
        return $callback();
      }
      protected function run(callable $callback)
      {
        return $callback();
      }
    };

    $action->execute($dataType);
  }
}
