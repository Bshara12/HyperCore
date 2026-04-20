<?php

namespace Tests\Unit\Domains\CMS\Read\DTOs\DataType;

use App\Domains\CMS\Read\DTOs\DataType\ShowDataTypeDTOProperities;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Models\Project;
use Mockery;
use Tests\TestCase;

class ShowDataTypeDTOProperitiesTest extends TestCase
{
  /** @test */
  public function it_creates_dto_from_constructor()
  {
    $dto = new ShowDataTypeDTOProperities(
      project_id: 10,
      slug: 'articles'
    );

    $this->assertEquals(10, $dto->project_id);
    $this->assertEquals('articles', $dto->slug);
  }

  /** @test */
  public function it_creates_dto_from_request()
  {
    // Fake current project
    app()->instance('currentProject', (object)['public_id' => 'abc123']);

    // Mock repository
    $mockRepo = Mockery::mock(ProjectRepositoryInterface::class);
    $mockRepo->shouldReceive('findByKey')
      ->once()
      ->with('abc123')
      ->andReturn((new Project())->forceFill(['id' => 55]));

    app()->instance(ProjectRepositoryInterface::class, $mockRepo);

    $dto = ShowDataTypeDTOProperities::fromRequest('my-slug');

    $this->assertEquals(55, $dto->project_id);
    $this->assertEquals('my-slug', $dto->slug);
  }

  /** @test */
  public function it_throws_error_if_current_project_missing()
  {
    $this->expectException(\Illuminate\Contracts\Container\BindingResolutionException::class);

    // No currentProject in container
    ShowDataTypeDTOProperities::fromRequest('slug');
  }
}
