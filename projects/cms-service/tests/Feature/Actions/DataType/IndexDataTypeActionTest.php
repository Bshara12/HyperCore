<?php

namespace Tests\Feature\Actions\Read\DataType;

use App\Domains\CMS\Read\Actions\DataType\IndexDataTypeAction;
use App\Domains\CMS\Read\Repositories\DataType\DataTypeRepositoryRead;
use App\Models\DataType;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexDataTypeActionTest extends TestCase
{
  use RefreshDatabase;

  /** @test */
  public function it_returns_list_of_data_types_for_project()
  {
    $project = Project::factory()->create();

    $dt1 = DataType::factory()->create(['project_id' => $project->id]);
    $dt2 = DataType::factory()->create(['project_id' => $project->id]);

    $repo = new DataTypeRepositoryRead();
    $action = new IndexDataTypeAction($repo);

    $result = $action->execute($project->id);

    $this->assertCount(2, $result);
    $this->assertTrue($result->contains($dt1));
    $this->assertTrue($result->contains($dt2));
  }

  /** @test */
  public function it_returns_empty_list_if_no_data_types_exist()
  {
    $project = Project::factory()->create();

    $repo = new DataTypeRepositoryRead();
    $action = new IndexDataTypeAction($repo);

    $result = $action->execute($project->id);

    $this->assertCount(0, $result);
  }

  /** @test */
  public function it_calls_repository_list_method()
  {
    $mockRepo = \Mockery::mock(DataTypeRepositoryRead::class);

    $mockRepo->shouldReceive('list')
      ->once()
      ->with(5)
      ->andReturn(collect([]));

    // override circuit breaker
    $action = new class($mockRepo) extends IndexDataTypeAction {
      protected function runThroughCircuitBreaker(callable $callback)
      {
        return $callback();
      }
      protected function run(callable $callback)
      {
        return $callback();
      }
    };

    $result = $action->execute(5);

    $this->assertEquals(0, $result->count());
  }
}
