<?php

namespace Tests\Unit\CMS\Services;

use Tests\TestCase;
use Mockery;
use Illuminate\Http\UploadedFile;
use App\Domains\CMS\Services\FileUploadService;
use PHPUnit\Framework\Attributes\Test;

class FileUploadServiceTest extends TestCase
{
  protected function tearDown(): void
  {
    Mockery::close();
    parent::tearDown();
  }

  #[Test]

  public function it_stores_file_in_correct_path_and_returns_path()
  {
    $file = Mockery::mock(UploadedFile::class);

    $projectId = 10;
    $dataTypeId = 5;
    $fieldId = 3;

    $expectedPath = "projects/10/data-types/5/fields/3";
    $storedPath = "$expectedPath/abc123.jpg";

    $file->shouldReceive('store')
      ->once()
      ->with($expectedPath, 'public')
      ->andReturn($storedPath);

    $service = new FileUploadService();

    $result = $service->upload($file, $projectId, $dataTypeId, $fieldId);

    $this->assertEquals($storedPath, $result);
  }

  #[Test]

  public function it_handles_different_ids_correctly()
  {
    $file = Mockery::mock(UploadedFile::class);

    $projectId = 99;
    $dataTypeId = 44;
    $fieldId = 7;

    $expectedPath = "projects/99/data-types/44/fields/7";
    $storedPath = "$expectedPath/file.png";

    $file->shouldReceive('store')
      ->once()
      ->with($expectedPath, 'public')
      ->andReturn($storedPath);

    $service = new FileUploadService();

    $result = $service->upload($file, $projectId, $dataTypeId, $fieldId);

    $this->assertSame($storedPath, $result);
  }

  #[Test]

  public function it_returns_the_exact_value_from_store()
  {
    $file = Mockery::mock(UploadedFile::class);

    $file->shouldReceive('store')
      ->once()
      ->andReturn('some/random/path.jpg');

    $service = new FileUploadService();

    $result = $service->upload($file, 1, 1, 1);

    $this->assertEquals('some/random/path.jpg', $result);
  }
}
