<?php

namespace Tests\Unit\CMS\Services;

use Tests\TestCase;
use App\Domains\CMS\Services\SeoGeneratorService;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

class SeoGeneratorServiceTest extends TestCase
{
  #[Test]
  public function it_generates_seo_for_each_language()
  {
    $service = new SeoGeneratorService();

    $values = [
      'title' => [
        'en' => 'Hello World',
        'ar' => 'مرحبا بالعالم'
      ]
    ];

    $seo = $service->generate($values);

    $this->assertEquals('Hello World', $seo['en']['meta_title']);
    $this->assertEquals(Str::slug('Hello World'), $seo['en']['slug']);

    $this->assertEquals('مرحبا بالعالم', $seo['ar']['meta_title']);
    $this->assertEquals(Str::slug('مرحبا بالعالم'), $seo['ar']['slug']);
  }

  #[Test]

  public function it_uses_first_value_only_for_each_language()
  {
    $service = new SeoGeneratorService();

    $values = [
      'title' => [
        'en' => 'First Title'
      ],
      'subtitle' => [
        'en' => 'Second Title'
      ]
    ];

    $seo = $service->generate($values);

    $this->assertEquals('First Title', $seo['en']['meta_title']);
    $this->assertEquals(Str::slug('First Title'), $seo['en']['slug']);
  }

  #[Test]

  public function it_ignores_non_string_values()
  {
    $service = new SeoGeneratorService();

    $values = [
      'title' => [
        'en' => 123,
        'ar' => null,
        'fr' => ['invalid'],
      ]
    ];

    $seo = $service->generate($values);

    $this->assertEmpty($seo);
  }

  #[Test]

  public function it_returns_empty_array_when_no_valid_strings_exist()
  {
    $service = new SeoGeneratorService();

    $values = [
      'title' => [
        'en' => null,
        'ar' => [],
        'fr' => 55,
      ]
    ];

    $seo = $service->generate($values);

    $this->assertEquals([], $seo);
  }

  #[Test]

  public function it_handles_multiple_fields_and_languages()
  {
    $service = new SeoGeneratorService();

    $values = [
      'title' => [
        'en' => 'Main Title',
        'ar' => 'العنوان الرئيسي'
      ],
      'subtitle' => [
        'en' => 'Ignored Title',
        'ar' => 'عنوان مهمل'
      ]
    ];

    $seo = $service->generate($values);

    $this->assertEquals('Main Title', $seo['en']['meta_title']);
    $this->assertEquals(Str::slug('Main Title'), $seo['en']['slug']);

    $this->assertEquals('العنوان الرئيسي', $seo['ar']['meta_title']);
    $this->assertEquals(Str::slug('العنوان الرئيسي'), $seo['ar']['slug']);
  }
}
