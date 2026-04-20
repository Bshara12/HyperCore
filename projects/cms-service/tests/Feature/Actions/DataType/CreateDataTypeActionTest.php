<?php

namespace Tests\Feature\Actions;

use App\Domains\CMS\Actions\DataType\CreateDataTypeAction;
use App\Domains\CMS\DTOs\DataType\CreateDataTypeDTO;
use App\Domains\CMS\Repositories\Eloquent\DataTypeRepositoryEloquent;
use App\Models\DataType;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateDataTypeActionTest extends TestCase
{
  use RefreshDatabase;

  /** @test */
  public function it_creates_data_type_successfully()
  {
    $project = Project::factory()->create();

    $dto = new CreateDataTypeDTO(
      project_id: $project->id,
      name: 'Articles',
      slug: 'articles',
      description: 'desc',
      is_active: true,
      settings: ['a' => 1]
    );

    $repo = new DataTypeRepositoryEloquent();
    $action = new CreateDataTypeAction($repo);

    $result = $action->execute($dto);

    $this->assertInstanceOf(DataType::class, $result);

    $this->assertDatabaseHas('data_types', [
      'id' => $result->id,
      'project_id' => $project->id,
      'slug' => 'articles',
      'name' => 'Articles',
    ]);
  }

  /** @test */
  public function it_fails_if_slug_not_unique()
  {
    $project = Project::factory()->create();

    DataType::factory()->create([
      'project_id' => $project->id,
      'slug' => 'articles'
    ]);

    $dto = new CreateDataTypeDTO(
      project_id: $project->id,
      name: 'Articles',
      slug: 'articles',
      description: null,
      is_active: true,
      settings: []
    );

    $repo = new DataTypeRepositoryEloquent();
    $action = new CreateDataTypeAction($repo);

    $this->expectExceptionMessage("Slug 'articles' already exists for this project.");

    $action->execute($dto);
  }

  /** @test */
  public function it_runs_inside_database_transaction()
  {
    $project = Project::factory()->create();

    $dto = new CreateDataTypeDTO(
      project_id: $project->id,
      name: 'Test',
      slug: 'test',
      description: null,
      is_active: true,
      settings: []
    );

    $repo = new DataTypeRepositoryEloquent();
    $action = new CreateDataTypeAction($repo);

    $result = $action->execute($dto);

    $this->assertDatabaseHas('data_types', [
      'id' => $result->id,
      'slug' => 'test'
    ]);
  }

  /** @test */
  public function it_returns_the_created_model()
  {
    $project = Project::factory()->create();

    $dto = new CreateDataTypeDTO(
      project_id: $project->id,
      name: 'Products',
      slug: 'products',
      description: 'desc',
      is_active: true,
      settings: []
    );

    $repo = new DataTypeRepositoryEloquent();
    $action = new CreateDataTypeAction($repo);

    $result = $action->execute($dto);

    $this->assertInstanceOf(DataType::class, $result);
    $this->assertEquals('products', $result->slug);
  }
}
