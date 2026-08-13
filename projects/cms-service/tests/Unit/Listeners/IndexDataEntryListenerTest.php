<?php

namespace Tests\Unit\Listeners;

use App\Listeners\IndexDataEntryListener;
use App\Domains\Search\Actions\IndexDataEntryAction;
use App\Events\DataEntrySavedEvent;
use App\Models\DataEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;

uses(RefreshDatabase::class);

/**
 * ─── Fake Index Data Entry Action ───
 */
class FakeIndexDataEntryAction extends IndexDataEntryAction
{
  public bool $wasExecuted = false;
  public ?DataEntry $passedEntry = null;

  public function __construct() {}

  public function execute(DataEntry $entry): void
  {
    $this->wasExecuted = true;
    $this->passedEntry = $entry;
  }
}

afterEach(function () {
  Mockery::close(); // تنظيف الـ Mocks والـ Spies بعد كل اختبار لمنع التداخل
});

// 1. اختبار حالة الـ published
test('it indexes the entry when status is published', function () {
  Log::spy();

  $entry = DataEntry::factory()->create(['status' => 'published']);
  $event = new DataEntrySavedEvent($entry);

  $fakeAction = new FakeIndexDataEntryAction();
  $listener = new IndexDataEntryListener($fakeAction);

  $listener->handle($event);

  expect($fakeAction->wasExecuted)->toBeTrue();
  expect($fakeAction->passedEntry->id)->toBe($entry->id);

  Log::shouldHaveReceived('info')->with('SearchIndex: received indexing job', Mockery::any());
  Log::shouldHaveReceived('info')->with('SearchIndex: entry indexed successfully', Mockery::any());
});

// 2. اختبار حالة الـ archived
test('it updates status in search index when status is archived without full action execution', function () {
  Log::spy();

  $entry = DataEntry::factory()->create(['status' => 'archived']);

  DB::table('search_indices')->insert([
    'entry_id'     => $entry->id,
    'data_type_id' => 1,
    'project_id'   => 1,
    'language'     => 'en',
    'title'        => 'Test Entry',
    'status'       => 'published',
    'created_at'   => now(),
    'updated_at'   => now(),
  ]);

  $event = new DataEntrySavedEvent($entry);

  $fakeAction = new FakeIndexDataEntryAction();
  $listener = new IndexDataEntryListener($fakeAction);

  $listener->handle($event);

  expect($fakeAction->wasExecuted)->toBeFalse();

  Log::shouldHaveReceived('info')->with('SearchIndex: received indexing job', Mockery::any());
  Log::shouldHaveReceived('info')->with('SearchIndex: entry archived in index', Mockery::any());
});

// 3. اختبار حالات التجاهل (Draft, Scheduled)
test('it skips indexing when status is draft or scheduled', function (string $status) {
  Log::spy();

  $entry = DataEntry::factory()->create(['status' => $status]);
  $event = new DataEntrySavedEvent($entry);

  $fakeAction = new FakeIndexDataEntryAction();
  $listener = new IndexDataEntryListener($fakeAction);

  $listener->handle($event);

  expect($fakeAction->wasExecuted)->toBeFalse();

  Log::shouldHaveReceived('info')->with('SearchIndex: received indexing job', Mockery::any());
  Log::shouldHaveReceived('info')->with('SearchIndex: skipping status', Mockery::any());
})->with(['draft', 'scheduled']);

// 4. اختبار دالة الـ failed باستخدام shouldReceive لمنع تداخل السجلات
test('it logs error details when the listener fails permanently', function () {
  $entry = DataEntry::factory()->create();
  $event = new DataEntrySavedEvent($entry);
  $exception = new \Exception('Elasticsearch cluster is unreachable');

  // تحديد التوقع مسبقاً بدقة لتجنب تداخل الـ spies
  Log::shouldReceive('error')
    ->once()
    ->with(
      'SearchIndex: listener failed permanently',
      Mockery::on(fn($args) => $args['entry_id'] === $entry->id && $args['error'] === 'Elasticsearch cluster is unreachable')
    );

  $fakeAction = new FakeIndexDataEntryAction();
  $listener = new IndexDataEntryListener($fakeAction);

  $listener->failed($event, $exception);
});

// 5. اختبار إعدادات الـ Queue الثابتة
test('it has the correct queue configuration parameters', function () {
  $fakeAction = new FakeIndexDataEntryAction();
  $listener = new IndexDataEntryListener($fakeAction);

  expect($listener->queue)->toBe('search-indexing');
  expect($listener->tries)->toBe(3);
  expect($listener->backoff)->toBe(10);
});
