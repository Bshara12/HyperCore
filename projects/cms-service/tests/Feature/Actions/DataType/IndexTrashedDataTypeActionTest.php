<?php

namespace Tests\Feature\Actions\Read\DataType;

use App\Domains\CMS\Read\Actions\DataType\IndexTrashedDataType;
use App\Domains\CMS\Read\Repositories\DataType\DataTypeRepositoryRead;
use App\Models\DataType;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTrashedDataTypeActionTest extends TestCase
{
  use RefreshDatabase;

  /** @test */
  public function it_returns_only_trashed_data_types_for_project()
  {
    $project = Project::factory()->create();

    $trashed1 = DataType::factory()->create(['project_id' => $project->id]);
    $trashed2 = DataType::factory()->create(['project_id' => $project->id]);
    $active   = DataType::factory()->create(['project_id' => $project->id]);

    // soft delete اثنين فقط
    $trashed1->delete();
    $trashed2->delete();

    $repo = new DataTypeRepositoryRead();
    $action = new IndexTrashedDataType($repo);

    $result = $action->execute($project->id);

    $this->assertCount(2, $result);
    $this->assertTrue($result->contains($trashed1));
    $this->assertTrue($result->contains($trashed2));
    $this->assertFalse($result->contains($active));
  }

  /** @test */
  public function it_returns_empty_list_if_no_trashed_items_exist()
  {
    $project = Project::factory()->create();

    DataType::factory()->count(3)->create([
      'project_id' => $project->id,
    ]);

    $repo = new DataTypeRepositoryRead();
    $action = new IndexTrashedDataType($repo);

    $result = $action->execute($project->id);

    $this->assertCount(0, $result);
  }

  /** @test */
  public function it_calls_repository_trashed_method()
  {
    $mockRepo = \Mockery::mock(DataTypeRepositoryRead::class);

    $mockRepo->shouldReceive('trashed')
      ->once()
      ->with(7)
      ->andReturn(collect([]));

    // override circuit breaker
    $action = new class($mockRepo) extends IndexTrashedDataType {
      protected function runThroughCircuitBreaker(callable $callback)
      {
        return $callback();
      }
      protected function run(callable $callback)
      {
        return $callback();
      }
    };

    $result = $action->execute(7);

    $this->assertEquals(0, $result->count());
  }
}
