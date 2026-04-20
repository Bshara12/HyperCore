<?php

namespace Tests\Unit\Domains\CMS\DTOs\DataType;

use App\Domains\CMS\DTOs\DataType\UpdateDataTypeDTO;
use App\Domains\CMS\Requests\UpdateDataTypeRequest;
use Tests\TestCase;

class UpdateDataTypeDTOTest extends TestCase
{
  /** @test */
  public function it_creates_dto_from_constructor()
  {
    $dto = new UpdateDataTypeDTO(
      name: 'Test Name',
      slug: 'test-slug',
      description: 'desc',
      is_active: false,
      settings: ['a' => 1]
    );

    $this->assertEquals('Test Name', $dto->name);
    $this->assertEquals('test-slug', $dto->slug);
    $this->assertEquals('desc', $dto->description);
    $this->assertFalse($dto->is_active);
    $this->assertEquals(['a' => 1], $dto->settings);
  }

  /** @test */
  public function it_creates_dto_from_request()
  {
    $request = UpdateDataTypeRequest::create('/', 'POST', [
      'name' => 'Updated Name',
      'slug' => 'updated-slug',
      'description' => 'updated desc',
      'is_active' => true,
      'settings' => ['x' => 2]
    ]);

    $dto = UpdateDataTypeDTO::fromRequest($request);

    $this->assertEquals('Updated Name', $dto->name);
    $this->assertEquals('updated-slug', $dto->slug);
    $this->assertEquals('updated desc', $dto->description);
    $this->assertTrue($dto->is_active);
    $this->assertEquals(['x' => 2], $dto->settings);
  }

  /** @test */
  public function it_sets_default_values_when_missing()
  {
    $request = UpdateDataTypeRequest::create('/', 'POST', [
      'name' => 'Name',
      'slug' => 'slug',
    ]);

    $dto = UpdateDataTypeDTO::fromRequest($request);

    $this->assertEquals('Name', $dto->name);
    $this->assertEquals('slug', $dto->slug);
    $this->assertNull($dto->description);
    $this->assertTrue($dto->is_active); // default
    $this->assertEquals([], $dto->settings); // default
  }

  /** @test */
  public function it_parses_boolean_values_correctly()
  {
    $request = UpdateDataTypeRequest::create('/', 'POST', [
      'name' => 'Name',
      'slug' => 'slug',
      'is_active' => '0'
    ]);

    $dto = UpdateDataTypeDTO::fromRequest($request);

    $this->assertFalse($dto->is_active);
  }

  /** @test */
  public function it_handles_null_settings_as_empty_array()
  {
    $request = UpdateDataTypeRequest::create('/', 'POST', [
      'name' => 'Name',
      'slug' => 'slug',
      'settings' => null
    ]);

    $dto = UpdateDataTypeDTO::fromRequest($request);

    $this->assertEquals([], $dto->settings);
  }
}
