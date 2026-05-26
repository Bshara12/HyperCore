<?php

namespace Tests\Feature\Domains\Notifications\Models;

use App\Models\Domains\Notifications\Models\NotificationSubscription;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;

beforeEach(function () {
  // 1. إنشاء جدول الاشتراكات في الذاكرة لتشغيل الاختبار بشكل معزول وسريع
  Schema::create('notification_subscriptions', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('project_id')->nullable();
    $table->string('subscriber_type');
    $table->string('subscriber_id');
    $table->string('topic_key');
    $table->text('channel_mask')->nullable();
    $table->text('filters')->nullable();
    $table->boolean('active')->default(true);
    $table->timestamps();
  });
});

afterEach(function () {
  Schema::dropIfExists('notification_subscriptions');
});

// --------------------------------------------------------------------
// الاختبار: فحص الـ ULID والـ Fillable والـ Casts بالكامل
// --------------------------------------------------------------------
it('correctly casts subscription fields and generates a ULID on creation', function () {
  $subscription = NotificationSubscription::create([
    'project_id' => 'proj_xyz',
    'subscriber_type' => 'App\Models\User',
    'subscriber_id' => '99',
    'topic_key' => 'newsletter',
    'channel_mask' => ['email', 'sms'],                    // فحص الـ Array Cast للقنوات
    'filters' => ['categories' => ['tech', 'business']],     // فحص الـ Array Cast للفلاتر
    'active' => '1',                                        // نص سيتحول تلقائياً إلى Boolean
  ]);

  // 1. التحقق من توليد المعرف الفريد ULID تلقائياً عند الحفظ وثبات بنيته
  expect($subscription->id)->not->toBeNull()
    ->and(Str::isUlid($subscription->id))->toBeTrue();

  // 2. التحقق من سلامة الـ Boolean Cast
  expect($subscription->active)->toBeTrue();

  // 3. التحقق من سلامة الـ Array Cast للحقول التي تخزن كـ JSON
  expect($subscription->channel_mask)->toBeArray()
    ->toHaveCount(2)
    ->toContain('sms')
    ->and($subscription->filters)->toBeArray()
    ->toHaveKey('categories')
    ->and($subscription->filters['categories'])->toContain('tech');

  // 4. التحقق من بقية الحقول النصية للتأكد من الـ Fillable بالكامل
  expect($subscription->project_id)->toBe('proj_xyz')
    ->and($subscription->subscriber_type)->toBe('App\Models\User')
    ->and($subscription->subscriber_id)->toBe('99')
    ->and($subscription->topic_key)->toBe('newsletter');
});
