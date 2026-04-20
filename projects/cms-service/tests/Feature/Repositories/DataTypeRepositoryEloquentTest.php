<?php

namespace Tests\Feature\Repositories;

use App\Domains\CMS\DTOs\DataType\CreateDataTypeDTO;
use App\Domains\CMS\DTOs\DataType\UpdateDataTypeDTO;
use App\Domains\CMS\Repositories\Eloquent\DataTypeRepositoryEloquent;
use App\Models\DataType;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataTypeRepositoryEloquentTest extends TestCase
{
  use RefreshDatabase;

  /** @test */
  public function it_creates_a_data_type()
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
    $result = $repo->create($dto);

    $this->assertDatabaseHas('data_types', [
      'id' => $result->id,
      'project_id' => $project->id,
      'name' => 'Articles',
      'slug' => 'articles',
      'description' => 'desc',
      'is_active' => true,
    ]);

    $this->assertEquals(['a' => 1], $result->settings);
  }

  /** @test */
  public function it_throws_error_if_slug_not_unique()
  {
    $this->expectExceptionMessage("Slug 'articles' already exists for this project.");

    $project = Project::factory()->create();

    DataType::factory()->create([
      'project_id' => $project->id,
      'slug' => 'articles'
    ]);

    $repo = new DataTypeRepositoryEloquent();
    $repo->ensureSlugIsUnique($project->id, 'articles');
  }

  /** @test */
  public function it_finds_by_slug()
  {
    $project = Project::factory()->create();

    $dt = DataType::factory()->create([
      'project_id' => $project->id,
      'slug' => 'articles'
    ]);

    $repo = new DataTypeRepositoryEloquent();
    $result = $repo->findBySlug('articles', $project->id);

    $this->assertNotNull($result);
    $this->assertEquals($dt->id, $result->id);
  }

  /** @test */
  public function it_returns_null_if_slug_not_found()
  {
    $project = Project::factory()->create();

    $repo = new DataTypeRepositoryEloquent();
    $result = $repo->findBySlug('missing', $project->id);

    $this->assertNull($result);
  }

  /** @test */
  public function it_checks_unique_slug_for_update()
  {
    $this->expectExceptionMessage("Slug 'articles' already exists for this project.");

    $project = Project::factory()->create();

    $existing = DataType::factory()->create([
      'project_id' => $project->id,
      'slug' => 'articles'
    ]);

    $current = DataType::factory()->create([
      'project_id' => $project->id,
      'slug' => 'old-slug'
    ]);

    $repo = new DataTypeRepositoryEloquent();
    $repo->ensureSlugIsUniqueForUpdate($project->id, 'articles', $current->id);
  }

  /** @test */
  public function it_allows_same_slug_for_same_data_type()
  {
    $project = Project::factory()->create();

    $dt = DataType::factory()->create([
      'project_id' => $project->id,
      'slug' => 'articles'
    ]);

    $repo = new DataTypeRepositoryEloquent();

    // يجب ألا يرمي خطأ
    $repo->ensureSlugIsUniqueForUpdate($project->id, 'articles', $dt->id);

    $this->assertTrue(true);
  }

  /** @test */
  public function it_updates_a_data_type()
  {
    $dt = DataType::factory()->create([
      'name' => 'Old',
      'slug' => 'old',
      'description' => 'old desc',
      'is_active' => false,
      'settings' => ['x' => 1],
    ]);

    $dto = new UpdateDataTypeDTO(
      name: 'New',
      slug: 'new',
      description: 'new desc',
      is_active: true,
      settings: ['y' => 2]
    );

    $repo = new DataTypeRepositoryEloquent();
    $result = $repo->update($dt, $dto);

    $this->assertEquals('New', $result->name);
    $this->assertEquals('new', $result->slug);
    $this->assertEquals('new desc', $result->description);
    $this->assertTrue($result->is_active);
    $this->assertEquals(['y' => 2], $result->settings);
  }

  /** @test */
  public function it_soft_deletes_a_data_type()
  {
    $dt = DataType::factory()->create();

    $repo = new DataTypeRepositoryEloquent();
    $repo->delete($dt);

    $this->assertSoftDeleted('data_types', ['id' => $dt->id]);
  }

  /** @test */
  public function it_restores_a_soft_deleted_data_type()
  {
    $dt = DataType::factory()->create();
    $dt->delete();

    $repo = new DataTypeRepositoryEloquent();
    $repo->restore($dt->id);

    $this->assertFalse($dt->fresh()->trashed());
  }

  /** @test */
  public function it_force_deletes_a_data_type()
  {
    $dt = DataType::factory()->create();

    $repo = new DataTypeRepositoryEloquent();
    $repo->forceDelete($dt->id);

    $this->assertDatabaseMissing('data_types', ['id' => $dt->id]);
  }
}
