<?php

namespace Tests\Unit\Listeners;

use App\Listeners\CreateVersionListener;
use App\Domains\CMS\Services\Versioning\VersionCreator;
use App\Events\EntryChanged;
use App\Models\DataEntry;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * ─── Fake Version Creator ───
 */
class FakeVersionCreator extends VersionCreator
{
  public bool $wasCalled = false;
  public ?DataEntry $passedEntry = null;
  public ?int $passedUserId = null;

  public function __construct() {}

  public function create(DataEntry $entry, ?int $userId = null): void
  {
    $this->wasCalled = true;
    $this->passedEntry = $entry;
    $this->passedUserId = $userId;
  }
}

// ─── كود الاختبار المستقر ───────────────────────────────────────────

test('it loads values relation and triggers version creator using real objects', function () {
  // 🔥 حذف الجدول أولاً إن وجد لتجنب خطأ "already exists" ثم إعادة بنائه لضمان هيكل سليم للفاكتوري
  Schema::dropIfExists('users');

  Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email');
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->timestamps();
  });

  // 1. تجهيز كائن حقيقي من الـ DataEntry باستخدام الفاكتوري
  $entry = DataEntry::factory()->create();

  // 2. إنشاء الـ Event وتمرير البيانات الحقيقية له
  $userId = 55;
  $event = new EntryChanged($entry, $userId);

  // 3. إنشاء كائن الـ Fake وحقنه في الـ Listener
  $fakeVersionCreator = new FakeVersionCreator();
  $listener = new CreateVersionListener($fakeVersionCreator);

  // التأكد قبل التشغيل أن العلاقة غير محملة مسبقاً
  expect($entry->relationLoaded('values'))->toBeFalse();

  // 4. تنفيذ الـ Listener
  $listener->handle($event);

  // 5. التأكيدات الصارمة (Assertions)
  // أ. التأكد من تحميل علاقة الـ values فعلياً
  expect($entry->relationLoaded('values'))->toBeTrue();

  // ب. التأكد من استدعاء خدمة VersionCreator بالبيانات الدقيقة
  expect($fakeVersionCreator->wasCalled)->toBeTrue();
  expect($fakeVersionCreator->passedUserId)->toBe($userId);
  expect($fakeVersionCreator->passedEntry->id)->toBe($entry->id);
});
