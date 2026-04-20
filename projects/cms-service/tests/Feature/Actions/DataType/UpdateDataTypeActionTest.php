<?php

namespace Tests\Feature\Actions;

use App\Domains\CMS\Actions\DataType\UpdateDataTypeAction;
use App\Domains\CMS\DTOs\DataType\UpdateDataTypeDTO;
use App\Domains\CMS\Repositories\Eloquent\DataTypeRepositoryEloquent;
use App\Models\DataType;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateDataTypeActionTest extends TestCase
{
  use RefreshDatabase;

  /** @test */
  public function it_updates_data_type_successfully()
  {
    $project = Project::factory()->create();

    $dataType = DataType::factory()->create([
      'project_id' => $project->id,
      'name' => 'Old Name',
      'slug' => 'old-slug',
    ]);

    $dto = new UpdateDataTypeDTO(
      name: 'New Name',
      slug: 'new-slug',
      description: 'updated description',
      is_active: false,
      settings: ['x' => 1]
    );

    $repo = new DataTypeRepositoryEloquent();
    $action = new UpdateDataTypeAction($repo);

    $result = $action->execute($dataType, $dto);

    $this->assertEquals('New Name', $result->name);
    $this->assertEquals('new-slug', $result->slug);
    $this->assertEquals('updated description', $result->description);
    $this->assertFalse($result->is_active);
    $this->assertEquals(['x' => 1], $result->settings);
  }

  /** @test */
  public function it_throws_error_if_slug_not_unique_for_update()
  {
    $project = Project::factory()->create();

    $existing = DataType::factory()->create([
      'project_id' => $project->id,
      'slug' => 'articles',
    ]);

    $dataType = DataType::factory()->create([
      'project_id' => $project->id,
      'slug' => 'old-slug',
    ]);

    $dto = new UpdateDataTypeDTO(
      name: 'Test',
      slug: 'articles', // slug مستخدم من قبل عنصر آخر
      description: null,
      is_active: true,
      settings: []
    );

    $repo = new DataTypeRepositoryEloquent();
    $action = new UpdateDataTypeAction($repo);

    $this->expectExceptionMessage("Slug 'articles' already exists for this project.");

    $action->execute($dataType, $dto);
  }

  /** @test */
  public function it_allows_same_slug_for_same_data_type()
  {
    $project = Project::factory()->create();

    $dataType = DataType::factory()->create([
      'project_id' => $project->id,
      'slug' => 'articles',
    ]);

    $dto = new UpdateDataTypeDTO(
      name: 'Updated',
      slug: 'articles', // نفس الـ slug
      description: null,
      is_active: true,
      settings: []
    );

    $repo = new DataTypeRepositoryEloquent();
    $action = new UpdateDataTypeAction($repo);

    $result = $action->execute($dataType, $dto);

    $this->assertEquals('articles', $result->slug);
    $this->assertEquals('Updated', $result->name);
  }

  /** @test */
  /** @test */
  public function it_calls_repository_update_method()
  {
    $mockRepo = \Mockery::mock(DataTypeRepositoryEloquent::class);

    $dataType = (new DataType())->forceFill([
      'id' => 10,
      'project_id' => 5,
      'name' => 'Old',
      'slug' => 'old-slug',
    ]);

    $dto = new UpdateDataTypeDTO(
      name: 'New',
      slug: 'new-slug',
      description: null,
      is_active: true,
      settings: []
    );

    $mockRepo->shouldReceive('ensureSlugIsUniqueForUpdate')
      ->once()
      ->with(5, 'new-slug', 10);

    $mockRepo->shouldReceive('update')
      ->once()
      ->with($dataType, $dto)
      ->andReturn($dataType);

    $action = new class($mockRepo) extends UpdateDataTypeAction {
      protected function runThroughCircuitBreaker(callable $callback)
      {
        return $callback();
      }
      protected function run(callable $callback)
      {
        return $callback();
      }
    };

    $result = $action->execute($dataType, $dto);

    $this->assertSame($dataType, $result);
  }
}
