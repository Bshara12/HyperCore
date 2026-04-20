<?php

namespace Tests\Unit\CMS\DTOs\Read;

use Tests\TestCase;
use App\Domains\CMS\Read\DTOs\EntryDetailDTO;
use PHPUnit\Framework\Attributes\Test;

class EntryDetailDTOTest extends TestCase
{
  #[Test]
  public function it_creates_dto_with_correct_values()
  {
    $dto = new EntryDetailDTO(
      id: 10,
      status: 'published',
      values: ['title' => 'Hello'],
      seo: ['meta_title' => 'SEO Title']
    );

    $this->assertInstanceOf(EntryDetailDTO::class, $dto);
    $this->assertEquals(10, $dto->id);
    $this->assertEquals('published', $dto->status);
    $this->assertEquals(['title' => 'Hello'], $dto->values);
    $this->assertEquals(['meta_title' => 'SEO Title'], $dto->seo);
  }

  #[Test]
  public function it_requires_all_fields_to_be_passed()
  {
    $this->expectException(\TypeError::class);

    // Wrapped to avoid Intelephense warning
    (function () {
      new EntryDetailDTO();
    })();
  }

  #[Test]
  public function it_accepts_empty_arrays_for_values_and_seo()
  {
    $dto = new EntryDetailDTO(
      id: 1,
      status: 'draft',
      values: [],
      seo: []
    );

    $this->assertEquals([], $dto->values);
    $this->assertEquals([], $dto->seo);
  }

  #[Test]
  public function it_accepts_any_valid_scalar_for_status()
  {
    $dto = new EntryDetailDTO(
      id: 1,
      status: 'archived',
      values: ['x' => 1],
      seo: ['y' => 2]
    );

    $this->assertEquals('archived', $dto->status);
  }
}
