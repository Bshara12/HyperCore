<?php

namespace Tests\Unit\CMS\Services\Versioning;

use Tests\TestCase;
use Mockery;
use Illuminate\Support\Facades\DB;
use App\Models\DataEntry;
use App\Models\DataEntryVersion;
use App\Domains\CMS\Services\Versioning\VersionCreator;
use App\Domains\CMS\Services\Versioning\SnapshotGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class VersionCreatorTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function it_creates_first_version_when_no_previous_versions_exist()
  {
    $entry = DataEntry::factory()->create();
    $user = \App\Models\User::factory()->create();

    $snapshotGenerator = Mockery::mock(SnapshotGenerator::class);
    $snapshotGenerator->shouldReceive('generate')
      ->once()
      ->andReturn(['fake' => 'snapshot']);

    $service = new VersionCreator($snapshotGenerator);

    $service->create($entry, $user->id);

    $this->assertDatabaseHas('data_entry_versions', [
      'data_entry_id' => $entry->id,
      'version_number' => 1,
      'created_by' => $user->id,
    ]);
  }
}
