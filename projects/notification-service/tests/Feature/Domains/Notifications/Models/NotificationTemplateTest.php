<?php

namespace Tests\Feature\Domains\Notifications\Models;

use App\Models\Domains\Notifications\Models\NotificationTemplate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;

// محاكاة كلاس الـ Notification في حال عدم وجوده بذات الـ Namespace لتجنب التعارض
if (!class_exists(\App\Models\Domains\Notifications\Models\Notification::class)) {
  class_alias(\Illuminate\Database\Eloquent\Model::class, \App\Models\Domains\Notifications\Models\Notification::class);
}

beforeEach(function () {
  // 1. إنشاء جدول القوالب وجدول الإشعارات المرتبط بها في الذاكرة
  Schema::create('notification_templates', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('project_id')->nullable();
    $table->string('key');
    $table->string('channel');
    $table->string('locale');
    $table->integer('version')->default(1);
    $table->string('subject_template')->nullable();
    $table->text('body_template');
    $table->text('variables_schema')->nullable();
    $table->text('defaults')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
  });

  Schema::create('notifications', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('template_id');
    $table->string('status');
    $table->timestamps();
  });
});

afterEach(function () {
  Schema::dropIfExists('notifications');
  Schema::dropIfExists('notification_templates');
});

// --------------------------------------------------------------------
// الاختبار الأول: فحص الـ ULID والـ Fillable والـ Casts بالكامل
// --------------------------------------------------------------------
it('correctly casts template fields and generates a ULID on creation', function () {
  $template = NotificationTemplate::create([
    'project_id' => 'proj_123',
    'key' => 'welcome_email',
    'channel' => 'email',
    'locale' => 'ar',
    'version' => '2', // نص سيتحول تلقائياً إلى Integer
    'subject_template' => 'مرحباً بك في منصتنا',
    'body_template' => 'أهلاً خيو {name}، يسعدنا انضمامك.',
    'variables_schema' => ['name' => 'string', 'required' => true], // فحص الـ Array Cast
    'defaults' => ['name' => 'المستخدم'],                         // فحص الـ Array Cast للقيم الافتراضية
    'is_active' => '1',                                            // نص سيتحول تلقائياً إلى Boolean
  ]);

  // 1. التحقق من توليد الـ ULID تلقائياً عند الحفظ وثبات بنيته
  expect($template->id)->not->toBeNull()
    ->and(Str::isUlid($template->id))->toBeTrue();

  // 2. التحقق من سلامة الـ Boolean والـ Integer Cast
  expect($template->is_active)->toBeTrue()
    ->and($template->version)->toBe(2);

  // 3. التحقق من سلامة الـ Array Cast للحقول التي تخزن كـ JSON
  expect($template->variables_schema)->toBeArray()
    ->toHaveKey('name')
    ->and($template->defaults)->toBeArray()
    ->and($template->defaults['name'])->toBe('المستخدم');

  // 4. التحقق من بقية الحقول النصية للتأكد من الـ Fillable بالكامل
  expect($template->project_id)->toBe('proj_123')
    ->and($template->key)->toBe('welcome_email')
    ->and($template->channel)->toBe('email')
    ->and($template->locale)->toBe('ar')
    ->and($template->subject_template)->toBe('مرحباً بك في منصتنا')
    ->and($template->body_template)->toBe('أهلاً خيو {name}، يسعدنا انضمامك.');
});

// --------------------------------------------------------------------
// الاختبار الثاني: فحص علاقة الـ HasMany (notifications)
// --------------------------------------------------------------------
it('has a valid relationship with notifications', function () {
  $template = NotificationTemplate::create([
    'key' => 'order_shipped',
    'channel' => 'sms',
    'locale' => 'en',
    'body_template' => 'Your order {id} has been shipped.',
  ]);

  // إنشاء إشعارات مرتبطة بهذا القالب برمجياً في قاعدة البيانات
  \Illuminate\Support\Facades\DB::table('notifications')->insert([
    [
      'id' => (string) Str::ulid(),
      'template_id' => $template->id,
      'status' => 'sent',
      'created_at' => now(),
      'updated_at' => now(),
    ],
    [
      'id' => (string) Str::ulid(),
      'template_id' => $template->id,
      'status' => 'pending',
      'created_at' => now(),
      'updated_at' => now(),
    ]
  ]);

  // الفحص البرمجي للعلاقة والتأكد من جلب الإشعارات التابعة للقالب
  expect($template->notifications)->toHaveCount(2)
    ->and($template->notifications->first()->template_id)->toBe($template->id);
});
