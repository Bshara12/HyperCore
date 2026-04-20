<?php

namespace Tests\Unit\CMS\DTOs;

use Tests\TestCase;
use App\Domains\CMS\DTOs\CreateProjectDTO;
use App\Domains\CMS\Requests\CreateProjectRequest;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;

class CreateProjectDTOTest extends TestCase
{
  use WithFaker;

  #[Test]
  public function it_creates_dto_using_constructor()
  {
    $dto = new CreateProjectDTO(
      name: 'Test Project',
      ownerId: 1,
      supportedLanguages: ['en', 'ar'],
      enabledModules: ['cms', 'ecommerce']
    );

    $this->assertEquals('Test Project', $dto->name);
    $this->assertEquals(1, $dto->ownerId);
    $this->assertEquals(['en', 'ar'], $dto->supportedLanguages);
    $this->assertEquals(['cms', 'ecommerce'], $dto->enabledModules);
  }

  #[Test]
  public function it_creates_dto_from_request()
  {
    $request = new CreateProjectRequest();

    $request->merge([
      'name' => 'From Request',
      'owner_id' => 5,
      'supported_languages' => ['en'],
      'enabled_modules' => ['booking']
    ]);

    $dto = CreateProjectDTO::fromRequest($request);

    $this->assertEquals('From Request', $dto->name);
    $this->assertEquals(5, $dto->ownerId);
    $this->assertEquals(['en'], $dto->supportedLanguages);
    $this->assertEquals(['booking'], $dto->enabledModules);
  }

  #[Test]
  public function it_handles_null_optional_fields()
  {
    $dto = new CreateProjectDTO(
      name: 'Null Test',
      ownerId: 2,
      supportedLanguages: null,
      enabledModules: null
    );

    $array = $dto->toArray();

    $this->assertNull($array['supported_languages']);
    $this->assertNull($array['enabled_modules']);
  }

  #[Test]
  public function it_converts_to_array_correctly()
  {
    $dto = new CreateProjectDTO(
      name: 'Array Test',
      ownerId: 3,
      supportedLanguages: ['ar'],
      enabledModules: ['cms']
    );

    $expected = [
      'name' => 'Array Test',
      'owner_id' => 3,
      'supported_languages' => ['ar'],
      'enabled_modules' => ['cms'],
    ];

    $this->assertEquals($expected, $dto->toArray());
  }
}
