<?php

namespace Tests\Feature\CMS\Services\Versioning;

use Tests\TestCase;
use Mockery;
use App\Models\DataEntry;
use App\Models\DataEntryVersion;
use App\Models\DataEntryValue;
use App\Models\DataTypeField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domains\CMS\Services\Versioning\VersionRestoreService;
use App\Domains\CMS\Services\Versioning\VersionCreator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use PHPUnit\Framework\Attributes\Test;

class VersionRestoreServiceTest extends TestCase
{
  use RefreshDatabase;
  protected function setUp(): void
  {
    parent::setUp();

    \Illuminate\Database\Eloquent\Factories\Factory::guessFactoryNamesUsing(function ($modelName) {
      return 'Database\\Factories\\' . class_basename($modelName) . 'Factory';
    });
  }

  #[Test]
  public function it_restores_version_successfully()
  {
    $entry = DataEntry::factory()->create([
      'status' => 'draft'
    ]);

    $field = DataTypeField::factory()->create();

    // create old values (will be soft deleted)
    DataEntryValue::factory()->count(2)->create([
      'data_entry_id' => $entry->id,
      'data_type_field_id' => $field->id,
    ]);

    $snapshot = [
      'entry' => [
        'status' => 'published',
        'scheduled_at' => null,
        'published_at' => now(),
      ],
      'values' => [
        [
          'data_type_field_id' => $field->id,
          'language' => 'en',
          'value' => 'Restored Value'
        ]
      ]
    ];

    $version = DataEntryVersion::factory()->create([
      'data_entry_id' => $entry->id,
      'snapshot' => $snapshot
    ]);

    $versionCreator = Mockery::mock(VersionCreator::class);
    $versionCreator->shouldReceive('create')
      ->once()
      ->withArgs(function ($argEntry, $userId) use ($entry) {
        return $argEntry->id === $entry->id && $userId === 5;
      });

    $service = new VersionRestoreService($versionCreator);

    $service->restore($version->id, 5);

    // ✅ Assert only one active (non-deleted) value exists
    $this->assertEquals(
      1,
      DataEntryValue::where('data_entry_id', $entry->id)
        ->whereNull('deleted_at')
        ->count()
    );

    // Assert restored value exists
    $this->assertDatabaseHas('data_entry_values', [
      'data_entry_id' => $entry->id,
      'language' => 'en',
      'value' => 'Restored Value'
    ]);

    // Assert entry updated
    $this->assertDatabaseHas('data_entries', [
      'id' => $entry->id,
      'status' => 'published'
    ]);
  }

  #[Test]
  public function it_throws_exception_if_version_not_found()
  {
    $this->expectException(ModelNotFoundException::class);

    $versionCreator = Mockery::mock(VersionCreator::class);

    $service = new VersionRestoreService($versionCreator);

    $service->restore(999);
  }

  #[Test]
  public function it_throws_exception_if_entry_not_found()
  {
    $this->expectException(ModelNotFoundException::class);

    $entry = DataEntry::factory()->create();
    $field = DataTypeField::factory()->create();

    $snapshot = [
      'entry' => [
        'status' => 'published',
        'scheduled_at' => null,
        'published_at' => null,
      ],
      'values' => [
        [
          'data_type_field_id' => $field->id,
          'language' => 'en',
          'value' => 'Some Value'
        ]
      ]
    ];

    $version = DataEntryVersion::factory()->create([
      'data_entry_id' => $entry->id,
      'snapshot' => $snapshot
    ]);

    // delete entry AFTER version exists
    $entry->delete();

    $versionCreator = Mockery::mock(VersionCreator::class);

    $service = new VersionRestoreService($versionCreator);

    $service->restore($version->id);
  }

  #[Test]
  public function it_rolls_back_transaction_if_version_creator_fails()
  {
    $entry = DataEntry::factory()->create([
      'status' => 'draft'
    ]);

    $field = DataTypeField::factory()->create();

    $snapshot = [
      'entry' => [
        'status' => 'published',
        'scheduled_at' => null,
        'published_at' => null,
      ],
      'values' => [
        [
          'data_type_field_id' => $field->id,
          'language' => 'en',
          'value' => 'Rollback Test'
        ]
      ]
    ];

    $version = DataEntryVersion::factory()->create([
      'data_entry_id' => $entry->id,
      'snapshot' => $snapshot
    ]);

    $versionCreator = Mockery::mock(VersionCreator::class);
    $versionCreator->shouldReceive('create')
      ->once()
      ->andThrow(new \Exception('Fail'));

    $service = new VersionRestoreService($versionCreator);

    try {
      $service->restore($version->id);
    } catch (\Exception $e) {
      // ignore
    }

    // Ensure rollback happened (status unchanged)
    $this->assertDatabaseHas('data_entries', [
      'id' => $entry->id,
      'status' => 'draft'
    ]);
  }

  protected function tearDown(): void
  {
    Mockery::close();
    parent::tearDown();
  }
}
