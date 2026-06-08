<?php

namespace Tests\Unit\Listeners;

use App\Listeners\IndexDataEntryListener;
use App\Domains\Search\Actions\IndexDataEntryAction;
use App\Events\DataEntrySavedEvent;
use App\Models\DataEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;

uses(RefreshDatabase::class);

/**
 * ─── Fake Index Data Entry Action ───
 * كلاس صافي يرث الأكشن الأصلي ليعزل بيئة الاختبار عن منطق الفهرسة المعقد
 */
class FakeIndexDataEntryAction extends IndexDataEntryAction
{
  public bool $wasExecuted = false;
  public ?DataEntry $passedEntry = null;

  // تفريغ الـ Constructor لمنع طلب أي Dependencies خارجية
  public function __construct() {}

  public function execute(DataEntry $entry): void
  {
    $this->wasExecuted = true;
    $this->passedEntry = $entry;
  }
}

// ─── كود الاختبارات والتغطية الشاملة ───────────────────────────────────────

// 1. اختبار الحالات التي يجب عمل فهرسة (Index) لها فعلياً
test('it indexes the entry when status is published or archived', function (string $status) {
  // استخدام الـ Spy المدمج كحالة ضرورة قصوى لفحص أسطر الـ Log::info دون الكتابة في ملفات حقيقية
  Log::spy();

  $entry = DataEntry::factory()->create(['status' => $status]);
  $event = new DataEntrySavedEvent($entry);

  $fakeAction = new FakeIndexDataEntryAction();
  $listener = new IndexDataEntryListener($fakeAction);

  $listener->handle($event);

  // التأكد من تنفيذ الأكشن وتمرير الموديل الصحيح له
  expect($fakeAction->wasExecuted)->toBeTrue();
  expect($fakeAction->passedEntry->id)->toBe($entry->id);

  // التأكد من طباعة الـ Logs الخاصة بالنجاح
  Log::shouldHaveReceived('info')->with('SearchIndex: received indexing job', Mockery::any());
  Log::shouldHaveReceived('info')->with('SearchIndex: entry indexed successfully', Mockery::any());
})->with(['published', 'archived']);


// 2. اختبار حالات التجاهل (Draft, Scheduled) والتأكد من الخروج المبكر (Return)
test('it skips indexing when status is not published or archived', function (string $status) {
  Log::spy();

  $entry = DataEntry::factory()->create(['status' => $status]);
  $event = new DataEntrySavedEvent($entry);

  $fakeAction = new FakeIndexDataEntryAction();
  $listener = new IndexDataEntryListener($fakeAction);

  $listener->handle($event);

  // التأكد من أن الأكشن لَم يُنفذ نهائياً بسبب الـ Return المبكر
  expect($fakeAction->wasExecuted)->toBeFalse();

  // التأكد من طباعة لوج التخطي وسحب البيانات
  Log::shouldHaveReceived('info')->with('SearchIndex: received indexing job', Mockery::any());
  Log::shouldHaveReceived('info')->with('SearchIndex: skipping non-published entry', Mockery::any());
})->with(['draft', 'scheduled']);


// 3. اختبار دالة الـ failed لتغطية أسطر الـ Log::error عند انهيار الـ Job
test('it logs error details when the listener fails permanently', function () {
  Log::spy();

  $entry = DataEntry::factory()->create();
  $event = new DataEntrySavedEvent($entry);
  $exception = new \Exception('Elasticsearch cluster is unreachable');

  $fakeAction = new FakeIndexDataEntryAction();
  $listener = new IndexDataEntryListener($fakeAction);

  // استدعاء دالة الفشل مباشرة لمحاكاة خروج الـ Queue عن مساره
  $listener->failed($event, $exception);

  // التأكد من توثيق الخطأ الحادث بدقة داخل الـ Logs
  Log::shouldHaveReceived('error')->once()->with(
    'SearchIndex: listener failed permanently',
    Mockery::on(fn($args) => $args['entry_id'] === $entry->id && $args['error'] === 'Elasticsearch cluster is unreachable')
  );
});


// 4. اختبار خصائص الـ Queue والتأكد من إعداداتها الثابتة
test('it has the correct queue configuration parameters', function () {
  $fakeAction = new FakeIndexDataEntryAction();
  $listener = new IndexDataEntryListener($fakeAction);

  expect($listener->queue)->toBe('search-indexing');
  expect($listener->tries)->toBe(3);
  expect($listener->backoff)->toBe(10);
});
