<?php

namespace Tests\Unit\CMS\Services\Versioning;

use Tests\TestCase;
use Mockery;
use App\Models\DataEntry;
use App\Models\DataEntryValue;
use App\Domains\CMS\Services\Versioning\SnapshotGenerator;
use PHPUnit\Framework\Attributes\Test;

class SnapshotGeneratorTest extends TestCase
{
  protected function tearDown(): void
  {
    Mockery::close();
    parent::tearDown();
  }

  #[Test]
  public function it_generates_snapshot_with_entry_and_values()
  {
    // Create real DataEntry model (NO MOCK)
    $entry = new DataEntry();
    $entry->id = 10;
    $entry->status = 'draft';
    $entry->scheduled_at = '2025-01-01 10:00:00';
    $entry->published_at = null;
    $entry->data_type_id = 3;
    $entry->project_id = 7;

    // Fake values
    $value1 = new DataEntryValue([
      'data_type_field_id' => 1,
      'language' => 'en',
      'value' => 'Hello',
    ]);

    $value2 = new DataEntryValue([
      'data_type_field_id' => 2,
      'language' => 'ar',
      'value' => 'مرحبا',
    ]);

    // Attach relation normally
    $entry->setRelation('values', collect([$value1, $value2]));

    // Now mock ONLY loadMissing()
    $entry = Mockery::mock($entry)->makePartial();
    $entry->shouldAllowMockingProtectedMethods();
    $entry->shouldReceive('loadMissing')
      ->once()
      ->with(['values'])
      ->andReturnSelf();

    $service = new SnapshotGenerator();

    $snapshot = $service->generate($entry);

    // Assertions
    $this->assertEquals(10, $snapshot['entry']['id']);
    $this->assertEquals('draft', $snapshot['entry']['status']);
    $this->assertEquals('2025-01-01 10:00:00', $snapshot['entry']['scheduled_at']);
    $this->assertNull($snapshot['entry']['published_at']);
    $this->assertEquals(3, $snapshot['entry']['data_type_id']);
    $this->assertEquals(7, $snapshot['entry']['project_id']);

    $this->assertCount(2, $snapshot['values']);
    $this->assertEquals('Hello', $snapshot['values'][0]['value']);
    $this->assertEquals('مرحبا', $snapshot['values'][1]['value']);
  }

  #[Test]

  public function it_returns_empty_values_if_entry_has_no_values()
  {
    $entry = new DataEntry();
    $entry->id = 1;
    $entry->status = 'draft';
    $entry->scheduled_at = null;
    $entry->published_at = null;
    $entry->data_type_id = 2;
    $entry->project_id = 3;

    $entry->setRelation('values', collect([]));

    $entry = Mockery::mock($entry)->makePartial();
    $entry->shouldAllowMockingProtectedMethods();
    $entry->shouldReceive('loadMissing')
      ->once()
      ->with(['values'])
      ->andReturnSelf();

    $service = new SnapshotGenerator();

    $snapshot = $service->generate($entry);

    $this->assertEquals([], $snapshot['values']);
  }
}
