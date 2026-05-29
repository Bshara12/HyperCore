  <?php

  use App\Domains\CMS\Services\Versioning\SnapshotGenerator;
  use App\Domains\CMS\Services\Versioning\VersionCreator;
  use App\Models\DataEntry;
  use App\Models\DataEntryVersion;
  use Illuminate\Foundation\Testing\RefreshDatabase;

  uses(RefreshDatabase::class);

  beforeEach(function () {
    // محاكاة الـ SnapshotGenerator لأننا لا نريد اختبار توليد اللقطة هنا
    $this->snapshotGenerator = Mockery::mock(SnapshotGenerator::class);
    $this->service = new VersionCreator($this->snapshotGenerator);
  });

  test('it creates version 1 when no previous versions exist', function () {
    $entry = DataEntry::factory()->create();
    $mockSnapshot = ['data' => 'test-snapshot'];

    $this->snapshotGenerator->shouldReceive('generate')
      ->once()
      ->with($entry)
      ->andReturn($mockSnapshot);

    $this->service->create($entry, 1);

    // التأكد من أن الإصدار تم إنشاؤه في قاعدة البيانات
    $this->assertDatabaseHas('data_entry_versions', [
      'data_entry_id' => $entry->id,
      'version_number' => 1,
      'created_by' => 1
    ]);
  });

  test('it increments version number correctly when versions exist', function () {
    $entry = DataEntry::factory()->create();

    // إنشاء إصدار سابق (رقم 1)
    DataEntryVersion::factory()->create([
      'data_entry_id' => $entry->id,
      'version_number' => 1
    ]);

    $this->snapshotGenerator->shouldReceive('generate')
      ->once()
      ->andReturn(['data' => 'new-snapshot']);

    // إنشاء الإصدار الثاني
    $this->service->create($entry);

    // التأكد من وجود الإصدار الثاني
    $this->assertDatabaseHas('data_entry_versions', [
      'data_entry_id' => $entry->id,
      'version_number' => 2
    ]);
  });
