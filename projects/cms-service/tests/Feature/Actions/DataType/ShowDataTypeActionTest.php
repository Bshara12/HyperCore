<?php

namespace Tests\Feature\Actions\Read\DataType;

use App\Domains\CMS\Read\Actions\DataType\ShowDataTypeAction;
use App\Domains\CMS\Read\DTOs\DataType\ShowDataTypeDTOProperities;
use App\Domains\CMS\Read\Repositories\DataType\DataTypeRepositoryRead;
use App\Models\DataType;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowDataTypeActionTest extends TestCase
{
  use RefreshDatabase;

  /** @test */
  public function it_returns_data_type_by_slug_and_project_id()
  {
    $project = Project::factory()->create();

    $dataType = DataType::factory()->create([
      'project_id' => $project->id,
      'slug' => 'articles',
    ]);

    $dto = new ShowDataTypeDTOProperities(
      project_id: $project->id,
      slug: 'articles'
    );

    $repo = new DataTypeRepositoryRead();
    $action = new ShowDataTypeAction($repo);

    $result = $action->execute($dto);

    $this->assertNotNull($result);
    $this->assertEquals($dataType->id, $result->id);
  }

  /** @test */
  public function it_returns_null_if_data_type_not_found()
  {
    $project = Project::factory()->create();

    $dto = new ShowDataTypeDTOProperities(
      project_id: $project->id,
      slug: 'missing-slug'
    );

    $repo = new DataTypeRepositoryRead();
    $action = new ShowDataTypeAction($repo);

    $result = $action->execute($dto);

    $this->assertNull($result);
  }

  /** @test */
  public function it_calls_repository_find_by_slug_method()
  {
    $mockRepo = \Mockery::mock(DataTypeRepositoryRead::class);

    $mockRepo->shouldReceive('findBySlug')
      ->once()
      ->with('test-slug', 5)
      ->andReturn(null);

    // override circuit breaker
    $action = new class($mockRepo) extends ShowDataTypeAction {
      protected function runThroughCircuitBreaker(callable $callback)
      {
        return $callback();
      }
      protected function run(callable $callback)
      {
        return $callback();
      }
    };

    $dto = new ShowDataTypeDTOProperities(
      project_id: 5,
      slug: 'test-slug'
    );

    $result = $action->execute($dto);

    $this->assertNull($result);
  }
}
