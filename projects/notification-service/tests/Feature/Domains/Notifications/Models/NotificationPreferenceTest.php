<?php

namespace Tests\Feature\Domains\Notifications\Models;

use App\Models\Domains\Notifications\Models\NotificationPreference;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;

beforeEach(function () {
  // 1. إنشاء جدول التفضيلات في الذاكرة لتشغيل الاختبار بشكل معزول
  Schema::create('notification_preferences', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('project_id')->nullable();
    $table->string('recipient_type');
    $table->string('recipient_id');
    $table->string('topic_key');
    $table->string('channel');
    $table->boolean('enabled')->default(true);
    $table->timestamp('mute_until')->nullable();
    $table->text('quiet_hours')->nullable();
    $table->string('delivery_mode')->nullable();
    $table->string('locale')->nullable();
    $table->text('metadata')->nullable();
    $table->timestamps();
  });
});

afterEach(function () {
  Schema::dropIfExists('notification_preferences');
});

// --------------------------------------------------------------------
// الاختبار: فحص الـ ULID والـ Fillable والـ Casts بالكامل
// --------------------------------------------------------------------
it('correctly casts preference fields and generates a ULID on creation', function () {
  $preference = NotificationPreference::create([
    'project_id' => 'proj_abc',
    'recipient_type' => 'App\Models\User',
    'recipient_id' => '1',
    'topic_key' => 'order_updates',
    'channel' => 'email',
    'enabled' => '1', // نمرر نصاً أو رقماً للتأكد من تحويله إلى Boolean
    'mute_until' => '2026-05-20 00:00:00', // نص سيتحول إلى Datetime
    'quiet_hours' => [
      'start' => '22:00',
      'end' => '08:00'
    ], // مصفوفة ستتحول إلى JSON في قاعدة البيانات وتعود كمصفوفة
    'delivery_mode' => 'instant',
    'locale' => 'ar',
    'metadata' => ['device' => 'Samsung S24 Ultra'],
  ]);

  // 1. التحقق من توليد المعرف الفريد ULID تلقائياً عند الحفظ
  expect($preference->id)->not->toBeNull()
    ->and(Str::isUlid($preference->id))->toBeTrue();

  // 2. التحقق من سلامة الـ Boolean Cast
  expect($preference->enabled)->toBeTrue();

  // 3. التحقق من سلامة الـ Datetime Cast
  // 3. التحقق من سلامة الـ Datetime Cast
  expect($preference->mute_until)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
    ->and($preference->mute_until->format('Y-m-d H:i:s'))->toBe('2026-05-20 00:00:00');

  // 4. التحقق من سلامة الـ Array Cast حقول الـ JSON
  expect($preference->quiet_hours)->toBeArray()
    ->and($preference->quiet_hours['start'])->toBe('22:00')
    ->and($preference->metadata)->toBeArray()
    ->and($preference->metadata['device'])->toBe('Samsung S24 Ultra');

  // 5. التحقق من حفظ واسترجاع باقي الحقول العادية للتأكد من الـ Fillable
  expect($preference->project_id)->toBe('proj_abc')
    ->and($preference->topic_key)->toBe('order_updates')
    ->and($preference->channel)->toBe('email')
    ->and($preference->locale)->toBe('ar');
});
