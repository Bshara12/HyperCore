<?php

namespace Tests\Unit\CMS\DTOs\Project;

use Tests\TestCase;
use App\Domains\CMS\DTOs\Project\UpdateProjectDTO;
use App\Domains\CMS\Requests\UpdateProjectRequest;
use PHPUnit\Framework\Attributes\Test;

class UpdateProjectDTOTest extends TestCase
{
  #[Test]
  public function it_creates_dto_using_constructor_with_all_fields()
  {
    $dto = new UpdateProjectDTO(
      name: 'Updated Name',
      supportedLanguages: ['en', 'ar'],
      enabledModules: ['cms']
    );

    $this->assertEquals('Updated Name', $dto->name);
    $this->assertEquals(['en', 'ar'], $dto->supportedLanguages);
    $this->assertEquals(['cms'], $dto->enabledModules);
  }

  #[Test]
  public function it_creates_dto_using_constructor_with_null_values()
  {
    $dto = new UpdateProjectDTO(
      name: null,
      supportedLanguages: null,
      enabledModules: null
    );

    $this->assertNull($dto->name);
    $this->assertNull($dto->supportedLanguages);
    $this->assertNull($dto->enabledModules);
  }

  #[Test]
  public function it_creates_dto_from_request_with_all_fields()
  {
    $request = new UpdateProjectRequest();

    $request->merge([
      'name' => 'From Request',
      'supported_languages' => ['en'],
      'enabled_modules' => ['booking']
    ]);

    $dto = UpdateProjectDTO::fromRequest($request);

    $this->assertEquals('From Request', $dto->name);
    $this->assertEquals(['en'], $dto->supportedLanguages);
    $this->assertEquals(['booking'], $dto->enabledModules);
  }

  #[Test]
  public function it_creates_dto_from_request_with_partial_fields()
  {
    $request = new UpdateProjectRequest();

    $request->merge([
      'name' => 'Only Name Updated'
    ]);

    $dto = UpdateProjectDTO::fromRequest($request);

    $this->assertEquals('Only Name Updated', $dto->name);
    $this->assertNull($dto->supportedLanguages);
    $this->assertNull($dto->enabledModules);
  }

  #[Test]
  public function to_array_returns_only_non_null_fields()
  {
    $dto = new UpdateProjectDTO(
      name: 'Filtered',
      supportedLanguages: null,
      enabledModules: ['cms']
    );

    $array = $dto->toArray();

    $this->assertArrayHasKey('name', $array);
    $this->assertArrayHasKey('enabled_modules', $array);
    $this->assertArrayNotHasKey('supported_languages', $array);

    $this->assertEquals([
      'name' => 'Filtered',
      'enabled_modules' => ['cms'],
    ], $array);
  }

  #[Test]
  public function to_array_returns_empty_array_if_all_fields_null()
  {
    $dto = new UpdateProjectDTO(
      name: null,
      supportedLanguages: null,
      enabledModules: null
    );

    $this->assertEquals([], $dto->toArray());
  }

  #[Test]
  public function to_array_keeps_empty_array_values()
  {
    $dto = new UpdateProjectDTO(
      name: null,
      supportedLanguages: [],
      enabledModules: []
    );

    $array = $dto->toArray();

    $this->assertArrayHasKey('supported_languages', $array);
    $this->assertArrayHasKey('enabled_modules', $array);

    $this->assertEquals([], $array['supported_languages']);
    $this->assertEquals([], $array['enabled_modules']);
  }
}
