<?php

namespace Tests\Unit\Domains\CMS\DTOs\DataType;

use App\Domains\CMS\DTOs\DataType\CreateDataTypeDTO;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\CMS\Requests\CreateDataTypeRequest;
use App\Models\Project;
use Mockery;
use Tests\TestCase;

class CreateDataTypeDTOTest extends TestCase
{
  /** @test */
  public function it_creates_dto_from_constructor()
  {
    $dto = new CreateDataTypeDTO(
      project_id: '10',
      name: 'Articles',
      slug: 'articles',
      description: 'desc',
      is_active: true,
      settings: ['a' => 1]
    );

    $this->assertEquals('10', $dto->project_id);
    $this->assertEquals('Articles', $dto->name);
    $this->assertEquals('articles', $dto->slug);
    $this->assertEquals('desc', $dto->description);
    $this->assertTrue($dto->is_active);
    $this->assertEquals(['a' => 1], $dto->settings);
  }

  /** @test */
  public function it_creates_dto_from_request_with_provided_slug()
  {
    app()->instance('currentProject', (object)['public_id' => 'abc123']);

    $mockRepo = Mockery::mock(ProjectRepositoryInterface::class);
    $mockRepo->shouldReceive('findByKey')
      ->once()
      ->with('abc123')
      ->andReturn((new Project())->forceFill(['id' => 55]));

    app()->instance(ProjectRepositoryInterface::class, $mockRepo);

    $request = CreateDataTypeRequest::create('/', 'POST', [
      'name' => 'My Type',
      'slug' => 'my-type',
      'description' => 'desc',
      'is_active' => false,
      'settings' => ['x' => 1],
    ]);

    $dto = CreateDataTypeDTO::fromRequest($request);

    $this->assertEquals(55, $dto->project_id);
    $this->assertEquals('My Type', $dto->name);
    $this->assertEquals('my-type', $dto->slug);
    $this->assertEquals('desc', $dto->description);
    $this->assertFalse($dto->is_active);
    $this->assertEquals(['x' => 1], $dto->settings);
  }

  /** @test */
  public function it_generates_slug_from_name_if_not_provided()
  {
    app()->instance('currentProject', (object)['public_id' => 'xyz']);

    $mockRepo = Mockery::mock(ProjectRepositoryInterface::class);
    $mockRepo->shouldReceive('findByKey')
      ->once()
      ->with('xyz')
      ->andReturn((new Project())->forceFill(['id' => 77]));

    app()->instance(ProjectRepositoryInterface::class, $mockRepo);

    $request = CreateDataTypeRequest::create('/', 'POST', [
      'name' => 'My Custom Type',
    ]);

    $dto = CreateDataTypeDTO::fromRequest($request);

    $this->assertEquals(77, $dto->project_id);
    $this->assertEquals('my-custom-type', $dto->slug);
  }

  /** @test */
  public function it_sets_default_is_active_to_true()
  {
    app()->instance('currentProject', (object)['public_id' => 'p1']);

    $mockRepo = Mockery::mock(ProjectRepositoryInterface::class);
    $mockRepo->shouldReceive('findByKey')
      ->once()
      ->with('p1')
      ->andReturn((new Project())->forceFill(['id' => 99]));

    app()->instance(ProjectRepositoryInterface::class, $mockRepo);

    $request = CreateDataTypeRequest::create('/', 'POST', [
      'name' => 'Test',
      'slug' => 'test',
    ]);

    $dto = CreateDataTypeDTO::fromRequest($request);

    $this->assertTrue($dto->is_active);
  }

  /** @test */
  public function it_allows_null_description_and_settings()
  {
    app()->instance('currentProject', (object)['public_id' => 'p2']);

    $mockRepo = Mockery::mock(ProjectRepositoryInterface::class);
    $mockRepo->shouldReceive('findByKey')
      ->once()
      ->with('p2')
      ->andReturn((new Project())->forceFill(['id' => 101]));

    app()->instance(ProjectRepositoryInterface::class, $mockRepo);

    $request = CreateDataTypeRequest::create('/', 'POST', [
      'name' => 'Test',
      'slug' => 'test',
      'description' => null,
      'settings' => null,
    ]);

    $dto = CreateDataTypeDTO::fromRequest($request);

    $this->assertNull($dto->description);
    $this->assertNull($dto->settings);
  }
}
