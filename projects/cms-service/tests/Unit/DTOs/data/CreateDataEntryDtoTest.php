<?php

namespace Tests\Unit\CMS\DTOs\Data;

use Tests\TestCase;
use App\Domains\CMS\Requests\DataEntryRequest;
use App\Domains\CMS\DTOs\Data\CreateDataEntryDto;
use PHPUnit\Framework\Attributes\Test;

class CreateDataEntryDtoTest extends TestCase
{
    #[Test]
    public function it_creates_dto_with_all_fields_from_request()
    {
        $request = new DataEntryRequest([
            'values' => ['title' => 'Test'],
            'seo' => ['meta_title' => 'SEO Title'],
            'relations' => ['categories' => [1, 2]],
            'status' => 'published',
            'scheduled_at' => '2025-01-01 10:00:00',
        ]);

        $dto = CreateDataEntryDto::fromRequest($request);

        $this->assertInstanceOf(CreateDataEntryDto::class, $dto);
        $this->assertEquals(['title' => 'Test'], $dto->values);
        $this->assertEquals(['meta_title' => 'SEO Title'], $dto->seo);
        $this->assertEquals(['categories' => [1, 2]], $dto->relations);
        $this->assertEquals('published', $dto->status);
        $this->assertEquals('2025-01-01 10:00:00', $dto->scheduled_at);
    }

    #[Test]
    public function it_applies_default_values_when_fields_are_missing()
    {
        $request = new DataEntryRequest([]);

        $dto = CreateDataEntryDto::fromRequest($request);

        $this->assertEquals([], $dto->values);
        $this->assertNull($dto->seo);
        $this->assertNull($dto->relations);
        $this->assertEquals('draft', $dto->status);
        $this->assertNull($dto->scheduled_at);
    }

    #[Test]
    public function it_allows_null_values_for_optional_fields()
    {
        $request = new DataEntryRequest([
            'seo' => null,
            'relations' => null,
            'scheduled_at' => null,
        ]);

        $dto = CreateDataEntryDto::fromRequest($request);

        $this->assertNull($dto->seo);
        $this->assertNull($dto->relations);
        $this->assertNull($dto->scheduled_at);
    }

    #[Test]
    public function it_accepts_empty_arrays_for_values_relations_and_seo()
    {
        $request = new DataEntryRequest([
            'values' => [],
            'seo' => [],
            'relations' => [],
        ]);

        $dto = CreateDataEntryDto::fromRequest($request);

        $this->assertEquals([], $dto->values);
        $this->assertEquals([], $dto->seo);
        $this->assertEquals([], $dto->relations);
    }
}